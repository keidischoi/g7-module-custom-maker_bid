<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Models\MakerPayment;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\PaymentRules;
use Modules\Custom\MakerBids\Support\SettingsRules;

class PaymentService
{
    private ?bool $kindColumn = null;

    public function enabled(): bool
    {
        return Schema::hasTable('maker_payments');
    }

    /**
     * Create (or return) due payment snapshot(s) when a bid is awarded.
     *
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed>|null
     */
    public function ensureDue(MakerJob $job, MakerBid $bid, array $terms = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $existing = $this->rowsForJob((int) $job->id);
        if ($existing->isNotEmpty()) {
            return $this->presentBundle($existing, 0, true);
        }
        $cfg = $this->settings();
        $destination = PaymentRules::normalizeDestination($cfg['destination'] ?? PaymentRules::DEST_PLATFORM);
        $account = $this->resolveAccount($destination, $bid, $cfg);
        $note = PaymentRules::interpolateNote(
            (string) ($cfg['transfer_note'] ?? ''),
            (int) $job->id,
            (string) $job->title
        );
        $percent = $this->resolveDepositPercent($terms, $bid, $cfg);
        $depositTerms = $this->resolveDepositTerms($terms, $bid);
        $total = max(0, (int) $bid->amount);
        [$depositAmt, $balanceAmt] = PaymentRules::splitAmounts($total, $percent);
        $base = [
            'job_id' => (int) $job->id,
            'bid_id' => (int) $bid->id,
            'payer_user_id' => (int) $job->user_id,
            'payee_user_id' => $destination === PaymentRules::DEST_MAKER ? (int) $bid->user_id : null,
            'method' => PaymentRules::normalizeMethod($cfg['method'] ?? PaymentRules::METHOD_BANK),
            'status' => PaymentRules::STATUS_DUE,
            'destination' => $destination,
            'bank_name' => $account['bank_name'],
            'account_no' => $account['account_no'],
            'account_holder' => $account['account_holder'],
            'transfer_note' => $note !== '' ? $note : ('의뢰 #'.(int) $job->id),
            'instructions' => (string) ($cfg['instructions'] ?? ''),
        ];
        $split = $this->hasKindColumn() && $depositAmt > 0 && $balanceAmt > 0;
        if ($split) {
            $this->createRow($base, PaymentRules::KIND_DEPOSIT, $depositAmt, $percent, $depositTerms);
            $this->createRow($base, PaymentRules::KIND_BALANCE, $balanceAmt, $percent, $depositTerms);
        } else {
            $kind = $this->hasKindColumn()
                ? ($percent >= 100 && $depositAmt > 0 ? PaymentRules::KIND_DEPOSIT : PaymentRules::KIND_FULL)
                : PaymentRules::KIND_FULL;
            $this->createRow($base, $kind, $total, $percent, $depositTerms);
        }

        return $this->presentBundle($this->rowsForJob((int) $job->id), 0, true);
    }

    /**
     * Job detail / workspace bundle. Flattened current row keeps older layouts working.
     *
     * @return array<string, mixed>|null
     */
    public function forJob(int $jobId, int $actorId = 0, bool $isAdmin = false): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $rows = $this->rowsForJob($jobId);
        if ($rows->isEmpty()) {
            return null;
        }
        $visible = $rows->filter(fn (MakerPayment $row) => $this->canSee($row, $actorId, $isAdmin));
        if ($visible->isEmpty()) {
            return null;
        }

        return $this->presentBundle($visible->values(), $actorId, $isAdmin);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMine(int $userId): array
    {
        if (! $this->enabled() || $userId < 1) {
            return [];
        }
        $rows = MakerPayment::query()
            ->where(function ($q) use ($userId) {
                $q->where('payer_user_id', $userId)->orWhere('payee_user_id', $userId);
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->presentRow($row, $userId, false);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdmin(?string $status = null): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $q = MakerPayment::query()->orderByDesc('id')->limit(200);
        if ($status !== null && $status !== '' && PaymentRules::isAllowedStatus(PaymentRules::normalizeStatus($status))) {
            $q->where('status', PaymentRules::normalizeStatus($status));
        }
        $out = [];
        foreach ($q->get() as $row) {
            $out[] = $this->presentRow($row, 0, true);
        }

        return $out;
    }

    /**
     * Owner reports that a bank transfer was sent.
     *
     * @return array<string, mixed>
     */
    public function report(int $actorId, int $jobId, string $depositorName, string $memo = '', string $kind = ''): array
    {
        $row = $this->requireRow($jobId, null, $kind);
        if ((int) $row->payer_user_id !== $actorId) {
            throw new DomainException('의뢰자만 입금을 신고할 수 있습니다.', 403);
        }
        $this->assertBalanceUnlocked($row, $jobId);
        $status = (string) $row->status;
        if ($status === PaymentRules::STATUS_CONFIRMED) {
            throw new DomainException('이미 입금이 확인된 결제입니다.', 422);
        }
        if ($status === PaymentRules::STATUS_REFUNDED) {
            throw new DomainException('환불된 결제는 입금 신고할 수 없습니다.', 422);
        }
        $name = mb_substr(trim($depositorName), 0, 80);
        if ($name === '') {
            throw new DomainException('입금자명을 입력해 주세요.', 422);
        }
        $row->depositor_name = $name;
        $row->memo = mb_substr(trim($memo), 0, 500);
        $row->status = PaymentRules::STATUS_REPORTED;
        $row->reported_at = now();
        $row->save();
        $label = PaymentRules::kindLabel($this->rowKind($row));
        $this->notifyParties($row, 'payment.reported', $label.' 입금이 신고되었습니다.', $name);
        $this->audit($actorId, 'payment.report', $jobId, [
            'depositor_name' => $name,
            'kind' => $this->rowKind($row),
            'payment_id' => (int) $row->id,
        ]);

        return $this->presentBundle($this->rowsForJob($jobId), $actorId, false);
    }

    /**
     * Admin (platform) or winning maker (destination=maker) confirms the transfer.
     *
     * @return array<string, mixed>
     */
    public function confirm(int $actorId, int $jobId, bool $isAdmin, ?int $paymentId = null, string $kind = ''): array
    {
        $row = $this->requireRow($jobId, $paymentId, $kind);
        if (! $this->canConfirm($row, $actorId, $isAdmin)) {
            throw new DomainException('입금을 확인할 권한이 없습니다.', 403);
        }
        $this->assertBalanceUnlocked($row, $jobId);
        $status = (string) $row->status;
        if ($status === PaymentRules::STATUS_CONFIRMED) {
            return $this->presentBundle($this->rowsForJob($jobId), $actorId, $isAdmin);
        }
        if ($status === PaymentRules::STATUS_REFUNDED) {
            throw new DomainException('환불된 결제는 확인할 수 없습니다.', 422);
        }
        if ($status === PaymentRules::STATUS_DUE && ! $isAdmin) {
            throw new DomainException('입금 신고 후에 확인할 수 있습니다.', 422);
        }
        $row->status = PaymentRules::STATUS_CONFIRMED;
        $row->confirmed_at = now();
        $row->confirmed_by = $actorId;
        $row->save();
        $label = PaymentRules::kindLabel($this->rowKind($row));
        $this->notifyParties($row, 'payment.confirmed', $label.' 입금이 확인되었습니다.', PaymentRules::amountLabel($row->amount));
        $this->audit($actorId, 'payment.confirm', $jobId, [
            'status' => PaymentRules::STATUS_CONFIRMED,
            'kind' => $this->rowKind($row),
            'payment_id' => (int) $row->id,
        ]);

        return $this->presentBundle($this->rowsForJob($jobId), $actorId, $isAdmin);
    }

    /**
     * Admin marks a payment as refunded.
     *
     * @return array<string, mixed>
     */
    public function refund(int $actorId, int $jobId, string $note = '', ?int $paymentId = null): array
    {
        $row = $this->requireRow($jobId, $paymentId, '');
        $status = (string) $row->status;
        if ($status === PaymentRules::STATUS_REFUNDED) {
            return $this->presentBundle($this->rowsForJob($jobId), $actorId, true);
        }
        $row->status = PaymentRules::STATUS_REFUNDED;
        $row->refunded_at = now();
        $row->refund_note = mb_substr(trim($note), 0, 500);
        $row->save();
        $label = PaymentRules::kindLabel($this->rowKind($row));
        $this->notifyParties($row, 'payment.refunded', $label.'이(가) 환불 처리되었습니다.', (string) $row->refund_note);
        $this->audit($actorId, 'payment.refund', $jobId, [
            'note' => (string) $row->refund_note,
            'kind' => $this->rowKind($row),
            'payment_id' => (int) $row->id,
        ]);

        return $this->presentBundle($this->rowsForJob($jobId), $actorId, true);
    }

    public function assertCompleteAllowed(MakerJob $job): void
    {
        if (! $this->requireConfirmed()) {
            return;
        }
        if (! $this->enabled()) {
            return;
        }
        $rows = $this->rowsForJob((int) $job->id);
        if ($rows->isEmpty() && $job->awarded_bid_id) {
            $bid = MakerBid::query()->find($job->awarded_bid_id);
            if ($bid) {
                $this->ensureDue($job, $bid);
                $rows = $this->rowsForJob((int) $job->id);
            }
        }
        foreach ($rows as $row) {
            $status = (string) $row->status;
            if ($status === PaymentRules::STATUS_REFUNDED) {
                continue;
            }
            if ($status !== PaymentRules::STATUS_CONFIRMED) {
                throw new DomainException('입금 확인 후에 완료할 수 있습니다. ('.PaymentRules::kindLabel($this->rowKind($row)).')', 422);
            }
        }
    }

    public function isConfirmed(int $jobId): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        $rows = $this->rowsForJob($jobId);
        if ($rows->isEmpty()) {
            return true;
        }
        foreach ($rows as $row) {
            $status = (string) $row->status;
            if ($status === PaymentRules::STATUS_REFUNDED) {
                continue;
            }
            if ($status !== PaymentRules::STATUS_CONFIRMED) {
                return false;
            }
        }

        return true;
    }

    public function requireConfirmed(): bool
    {
        $cfg = $this->settings();

        return SettingsRules::boolish($cfg['require_confirmed'] ?? true);
    }

    /**
     * @param  Collection<int, MakerPayment>|iterable<MakerPayment>  $rows
     * @return array<string, mixed>
     */
    public function presentBundle(iterable $rows, int $actorId, bool $isAdmin): array
    {
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row;
        }
        usort($list, function (MakerPayment $a, MakerPayment $b) {
            $ka = PaymentRules::kindSort($this->rowKind($a));
            $kb = PaymentRules::kindSort($this->rowKind($b));
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return (int) $a->id <=> (int) $b->id;
        });
        $deposit = null;
        $balance = null;
        $total = 0;
        $percent = PaymentRules::DEFAULT_DEPOSIT_PERCENT;
        $terms = '';
        foreach ($list as $row) {
            $kind = $this->rowKind($row);
            if ($kind === PaymentRules::KIND_DEPOSIT) {
                $deposit = $row;
            }
            if ($kind === PaymentRules::KIND_BALANCE) {
                $balance = $row;
            }
            $total += (int) $row->amount;
            if (isset($row->deposit_percent) && $row->deposit_percent !== null && $row->deposit_percent !== '') {
                $percent = PaymentRules::normalizeDepositPercent($row->deposit_percent);
            }
            if (isset($row->deposit_terms) && trim((string) $row->deposit_terms) !== '') {
                $terms = (string) $row->deposit_terms;
            }
        }
        $items = [];
        foreach ($list as $row) {
            $items[] = $this->presentRow($row, $actorId, $isAdmin, $deposit);
        }
        $currentRow = $this->currentRow($list) ?? ($list[0] ?? null);
        $current = $currentRow
            ? $this->presentRow($currentRow, $actorId, $isAdmin, $deposit)
            : [];

        return array_merge($current, [
            'items' => $items,
            'deposit' => $deposit ? $this->presentRow($deposit, $actorId, $isAdmin, $deposit) : null,
            'balance' => $balance ? $this->presentRow($balance, $actorId, $isAdmin, $deposit) : null,
            'current' => $current !== [] ? $current : null,
            'total_amount' => $total,
            'total_amount_label' => PaymentRules::amountLabel($total),
            'deposit_percent' => $percent,
            'deposit_terms' => $terms,
            'split' => $deposit !== null && $balance !== null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRow(MakerPayment $row, int $actorId, bool $isAdmin, ?MakerPayment $deposit = null): array
    {
        $job = null;
        try {
            $job = MakerJob::query()->find((int) $row->job_id);
        } catch (\Throwable) {
            $job = null;
        }
        $title = $job ? (string) $job->title : '';
        $kind = $this->rowKind($row);
        $canConfirm = $this->canConfirm($row, $actorId, $isAdmin);
        $canReport = $actorId > 0 && (int) $row->payer_user_id === $actorId
            && in_array((string) $row->status, [PaymentRules::STATUS_DUE, PaymentRules::STATUS_REPORTED], true);
        if ($canReport && $kind === PaymentRules::KIND_BALANCE) {
            $dep = $deposit ?? $this->rowByKind((int) $row->job_id, PaymentRules::KIND_DEPOSIT);
            if ($dep && (string) $dep->status !== PaymentRules::STATUS_CONFIRMED) {
                $canReport = false;
            }
        }
        if ($canConfirm && $kind === PaymentRules::KIND_BALANCE && ! $isAdmin) {
            $dep = $deposit ?? $this->rowByKind((int) $row->job_id, PaymentRules::KIND_DEPOSIT);
            if ($dep && (string) $dep->status !== PaymentRules::STATUS_CONFIRMED) {
                $canConfirm = false;
            }
        }
        $canRefund = $isAdmin && (string) $row->status !== PaymentRules::STATUS_REFUNDED;
        $percent = isset($row->deposit_percent) && $row->deposit_percent !== null
            ? PaymentRules::normalizeDepositPercent($row->deposit_percent)
            : null;

        return [
            'id' => (int) $row->id,
            'job_id' => (int) $row->job_id,
            'job_title' => $title,
            'bid_id' => $row->bid_id !== null ? (int) $row->bid_id : null,
            'payer_user_id' => (int) $row->payer_user_id,
            'payee_user_id' => $row->payee_user_id !== null ? (int) $row->payee_user_id : null,
            'amount' => (int) $row->amount,
            'amount_label' => PaymentRules::amountLabel($row->amount),
            'kind' => $kind,
            'kind_label' => PaymentRules::kindLabel($kind),
            'deposit_percent' => $percent,
            'deposit_terms' => (string) ($row->deposit_terms ?? ''),
            'method' => PaymentRules::normalizeMethod($row->method),
            'method_label' => PaymentRules::methodLabel($row->method),
            'status' => PaymentRules::normalizeStatus($row->status),
            'status_label' => PaymentRules::statusLabel($row->status),
            'destination' => PaymentRules::normalizeDestination($row->destination),
            'destination_label' => PaymentRules::destinationLabel($row->destination),
            'bank_name' => (string) ($row->bank_name ?? ''),
            'account_no' => (string) ($row->account_no ?? ''),
            'account_holder' => (string) ($row->account_holder ?? ''),
            'transfer_note' => (string) ($row->transfer_note ?? ''),
            'instructions' => (string) ($row->instructions ?? ''),
            'depositor_name' => (string) ($row->depositor_name ?? ''),
            'memo' => (string) ($row->memo ?? ''),
            'reported_at' => optional($row->reported_at)?->format('Y-m-d H:i:s'),
            'confirmed_at' => optional($row->confirmed_at)?->format('Y-m-d H:i:s'),
            'confirmed_by' => $row->confirmed_by !== null ? (int) $row->confirmed_by : null,
            'refunded_at' => optional($row->refunded_at)?->format('Y-m-d H:i:s'),
            'refund_note' => (string) ($row->refund_note ?? ''),
            'created_at' => optional($row->created_at)?->format('Y-m-d H:i:s'),
            'can_report' => $canReport,
            'can_confirm' => $canConfirm,
            'can_refund' => $canRefund,
            'payer_is_me' => $actorId > 0 && (int) $row->payer_user_id === $actorId,
            'payee_is_me' => $actorId > 0 && (int) ($row->payee_user_id ?? 0) === $actorId,
            'account_label' => trim((string) ($row->bank_name ?? '').' '.(string) ($row->account_no ?? '').' '.(string) ($row->account_holder ?? '')),
        ];
    }

    /**
     * @return Collection<int, MakerPayment>
     */
    private function rowsForJob(int $jobId): Collection
    {
        return MakerPayment::query()->where('job_id', $jobId)->orderBy('id')->get();
    }

    private function requireRow(int $jobId, ?int $paymentId = null, string $kind = ''): MakerPayment
    {
        if (! $this->enabled()) {
            throw new DomainException('결제 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        if ($paymentId !== null && $paymentId > 0) {
            $row = MakerPayment::query()->find($paymentId);
            if ($row === null || (int) $row->job_id !== $jobId) {
                throw new DomainException('결제 내역이 없습니다.', 404);
            }

            return $row;
        }
        $kind = trim($kind);
        if ($kind !== '' && $this->hasKindColumn()) {
            $row = $this->rowByKind($jobId, PaymentRules::normalizeKind($kind));
            if ($row === null) {
                throw new DomainException('결제 내역이 없습니다. 낙찰 후 생성됩니다.', 404);
            }

            return $row;
        }
        $rows = $this->rowsForJob($jobId);
        if ($rows->isEmpty()) {
            throw new DomainException('결제 내역이 없습니다. 낙찰 후 생성됩니다.', 404);
        }
        $current = $this->currentRow($rows->all());

        return $current ?? $rows->first();
    }

    /**
     * @param  list<MakerPayment>  $rows
     */
    private function currentRow(array $rows): ?MakerPayment
    {
        if ($rows === []) {
            return null;
        }
        usort($rows, function (MakerPayment $a, MakerPayment $b) {
            $ka = PaymentRules::kindSort($this->rowKind($a));
            $kb = PaymentRules::kindSort($this->rowKind($b));
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }

            return (int) $a->id <=> (int) $b->id;
        });
        foreach ($rows as $row) {
            $status = (string) $row->status;
            if (in_array($status, [PaymentRules::STATUS_DUE, PaymentRules::STATUS_REPORTED], true)) {
                return $row;
            }
        }
        foreach (array_reverse($rows) as $row) {
            if ((string) $row->status === PaymentRules::STATUS_CONFIRMED) {
                return $row;
            }
        }

        return $rows[0];
    }

    private function rowByKind(int $jobId, string $kind): ?MakerPayment
    {
        if (! $this->hasKindColumn()) {
            return MakerPayment::query()->where('job_id', $jobId)->first();
        }

        return MakerPayment::query()->where('job_id', $jobId)->where('kind', $kind)->first();
    }

    private function rowKind(MakerPayment $row): string
    {
        if (! $this->hasKindColumn() || ! isset($row->kind) || $row->kind === null || $row->kind === '') {
            return PaymentRules::KIND_FULL;
        }

        return PaymentRules::normalizeKind($row->kind);
    }

    private function assertBalanceUnlocked(MakerPayment $row, int $jobId): void
    {
        if ($this->rowKind($row) !== PaymentRules::KIND_BALANCE) {
            return;
        }
        $deposit = $this->rowByKind($jobId, PaymentRules::KIND_DEPOSIT);
        if ($deposit && (string) $deposit->status !== PaymentRules::STATUS_CONFIRMED) {
            throw new DomainException('계약금 입금 확인 후에 잔금을 처리할 수 있습니다.', 422);
        }
    }

    /**
     * @param  array<string, mixed>  $base
     */
    private function createRow(array $base, string $kind, int $amount, int $percent, string $depositTerms): MakerPayment
    {
        $attrs = $base;
        $attrs['amount'] = max(0, $amount);
        if ($this->hasKindColumn()) {
            $attrs['kind'] = $kind;
            $attrs['deposit_percent'] = $percent;
            $attrs['deposit_terms'] = $depositTerms !== '' ? $depositTerms : null;
        }

        return MakerPayment::query()->create($attrs);
    }

    private function hasKindColumn(): bool
    {
        if ($this->kindColumn !== null) {
            return $this->kindColumn;
        }
        try {
            $this->kindColumn = Schema::hasColumn('maker_payments', 'kind');
        } catch (\Throwable) {
            $this->kindColumn = false;
        }

        return $this->kindColumn;
    }

    /**
     * @param  array<string, mixed>  $terms
     * @param  array<string, mixed>  $cfg
     */
    private function resolveDepositPercent(array $terms, MakerBid $bid, array $cfg): int
    {
        $fallback = PaymentRules::normalizeDepositPercent(
            $cfg['default_deposit_percent'] ?? PaymentRules::DEFAULT_DEPOSIT_PERCENT
        );
        $company = $this->companyForBid($bid);
        if ($company && isset($company->deposit_percent) && $company->deposit_percent !== null && $company->deposit_percent !== '') {
            $fallback = PaymentRules::normalizeDepositPercent($company->deposit_percent, $fallback);
        }
        if (array_key_exists('deposit_percent', $terms) && $terms['deposit_percent'] !== null && $terms['deposit_percent'] !== '') {
            return PaymentRules::normalizeDepositPercent($terms['deposit_percent'], $fallback);
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    private function resolveDepositTerms(array $terms, MakerBid $bid): string
    {
        if (array_key_exists('deposit_terms', $terms) && $terms['deposit_terms'] !== null && trim((string) $terms['deposit_terms']) !== '') {
            return mb_substr(trim((string) $terms['deposit_terms']), 0, 500);
        }
        $company = $this->companyForBid($bid);
        if ($company && isset($company->deposit_terms)) {
            return mb_substr(trim((string) $company->deposit_terms), 0, 500);
        }

        return '';
    }

    private function companyForBid(MakerBid $bid): ?MakerCompany
    {
        try {
            if ($bid->company_id) {
                $company = MakerCompany::query()->find($bid->company_id);
                if ($company) {
                    return $company;
                }
            }

            return MakerCompany::query()->where('user_id', (int) $bid->user_id)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function canSee(MakerPayment $row, int $actorId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ($actorId < 1) {
            return false;
        }

        return (int) $row->payer_user_id === $actorId || (int) ($row->payee_user_id ?? 0) === $actorId;
    }

    private function canConfirm(MakerPayment $row, int $actorId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        if ($actorId < 1) {
            return false;
        }
        $status = (string) $row->status;
        if ($status === PaymentRules::STATUS_CONFIRMED || $status === PaymentRules::STATUS_REFUNDED) {
            return false;
        }
        if (PaymentRules::normalizeDestination($row->destination) !== PaymentRules::DEST_MAKER) {
            return false;
        }

        return (int) ($row->payee_user_id ?? 0) === $actorId;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{bank_name:string,account_no:string,account_holder:string}
     */
    private function resolveAccount(string $destination, MakerBid $bid, array $cfg): array
    {
        $platform = [
            'bank_name' => trim((string) ($cfg['bank_name'] ?? '')),
            'account_no' => trim((string) ($cfg['account_no'] ?? '')),
            'account_holder' => trim((string) ($cfg['account_holder'] ?? '')),
        ];
        if ($destination !== PaymentRules::DEST_MAKER) {
            return $platform;
        }
        $company = $this->companyForBid($bid);
        $maker = [
            'bank_name' => $company && isset($company->bank_name) ? trim((string) $company->bank_name) : '',
            'account_no' => $company && isset($company->account_no) ? trim((string) $company->account_no) : '',
            'account_holder' => $company && isset($company->account_holder) ? trim((string) $company->account_holder) : '',
        ];
        if ($maker['account_no'] !== '') {
            return $maker;
        }

        return $platform;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        try {
            $all = app(MakerBidSettingsService::class)->getAllSettings();
            $row = $all['payment'] ?? [];

            return is_array($row) ? $row : [];
        } catch (\Throwable) {
            return SettingsRules::defaults()['payment'] ?? [];
        }
    }

    private function notifyParties(MakerPayment $row, string $type, string $title, string $body): void
    {
        try {
            $market = app(MarketplaceService::class);
            $jobId = (int) $row->job_id;
            $payer = (int) $row->payer_user_id;
            $payee = (int) ($row->payee_user_id ?? 0);
            if ($payer > 0) {
                $market->notify($payer, $type, $title, $body, $jobId);
            }
            if ($payee > 0 && $payee !== $payer) {
                $market->notify($payee, $type, $title, $body, $jobId);
            }
        } catch (\Throwable) {
        }
    }

    private function audit(int $actorId, string $action, int $jobId, array $meta = []): void
    {
        try {
            app(MarketplaceService::class)->audit($actorId > 0 ? $actorId : null, $action, 'payment', $jobId, $meta);
        } catch (\Throwable) {
        }
    }
}
