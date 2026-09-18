<?php

namespace Modules\Custom\MakerBids\Services;

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
    public function enabled(): bool
    {
        return Schema::hasTable('maker_payments');
    }

    /**
     * Create (or return) the due payment snapshot when a bid is awarded.
     *
     * @return array<string, mixed>|null
     */
    public function ensureDue(MakerJob $job, MakerBid $bid): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $existing = MakerPayment::query()->where('job_id', (int) $job->id)->first();
        if ($existing) {
            return $this->presentRow($existing, 0, true);
        }
        $cfg = $this->settings();
        $destination = PaymentRules::normalizeDestination($cfg['destination'] ?? PaymentRules::DEST_PLATFORM);
        $account = $this->resolveAccount($destination, $bid, $cfg);
        $note = PaymentRules::interpolateNote(
            (string) ($cfg['transfer_note'] ?? ''),
            (int) $job->id,
            (string) $job->title
        );
        $row = MakerPayment::query()->create([
            'job_id' => (int) $job->id,
            'bid_id' => (int) $bid->id,
            'payer_user_id' => (int) $job->user_id,
            'payee_user_id' => $destination === PaymentRules::DEST_MAKER ? (int) $bid->user_id : null,
            'amount' => max(0, (int) $bid->amount),
            'method' => PaymentRules::normalizeMethod($cfg['method'] ?? PaymentRules::METHOD_BANK),
            'status' => PaymentRules::STATUS_DUE,
            'destination' => $destination,
            'bank_name' => $account['bank_name'],
            'account_no' => $account['account_no'],
            'account_holder' => $account['account_holder'],
            'transfer_note' => $note !== '' ? $note : ('의뢰 #'.(int) $job->id),
            'instructions' => (string) ($cfg['instructions'] ?? ''),
        ]);

        return $this->presentRow($row, 0, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forJob(int $jobId, int $actorId = 0, bool $isAdmin = false): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $row = MakerPayment::query()->where('job_id', $jobId)->first();
        if (! $row) {
            return null;
        }
        if (! $this->canSee($row, $actorId, $isAdmin)) {
            return null;
        }

        return $this->presentRow($row, $actorId, $isAdmin);
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
    public function report(int $actorId, int $jobId, string $depositorName, string $memo = ''): array
    {
        $row = $this->requireRow($jobId);
        if ((int) $row->payer_user_id !== $actorId) {
            throw new DomainException('의뢰자만 입금을 신고할 수 있습니다.', 403);
        }
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
        $this->notifyParties($row, 'payment.reported', '입금이 신고되었습니다.', $name);
        $this->audit($actorId, 'payment.report', $jobId, ['depositor_name' => $name]);

        return $this->presentRow($row->fresh() ?? $row, $actorId, false);
    }

    /**
     * Admin (platform) or winning maker (destination=maker) confirms the transfer.
     *
     * @return array<string, mixed>
     */
    public function confirm(int $actorId, int $jobId, bool $isAdmin): array
    {
        $row = $this->requireRow($jobId);
        if (! $this->canConfirm($row, $actorId, $isAdmin)) {
            throw new DomainException('입금을 확인할 권한이 없습니다.', 403);
        }
        $status = (string) $row->status;
        if ($status === PaymentRules::STATUS_CONFIRMED) {
            return $this->presentRow($row, $actorId, $isAdmin);
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
        $this->notifyParties($row, 'payment.confirmed', '입금이 확인되었습니다.', PaymentRules::amountLabel($row->amount));
        $this->audit($actorId, 'payment.confirm', $jobId, ['status' => PaymentRules::STATUS_CONFIRMED]);

        return $this->presentRow($row->fresh() ?? $row, $actorId, $isAdmin);
    }

    /**
     * Admin marks a payment as refunded.
     *
     * @return array<string, mixed>
     */
    public function refund(int $actorId, int $jobId, string $note = ''): array
    {
        $row = $this->requireRow($jobId);
        $status = (string) $row->status;
        if ($status === PaymentRules::STATUS_REFUNDED) {
            return $this->presentRow($row, $actorId, true);
        }
        $row->status = PaymentRules::STATUS_REFUNDED;
        $row->refunded_at = now();
        $row->refund_note = mb_substr(trim($note), 0, 500);
        $row->save();
        $this->notifyParties($row, 'payment.refunded', '결제가 환불 처리되었습니다.', (string) $row->refund_note);
        $this->audit($actorId, 'payment.refund', $jobId, ['note' => (string) $row->refund_note]);

        return $this->presentRow($row->fresh() ?? $row, $actorId, true);
    }

    public function assertCompleteAllowed(MakerJob $job): void
    {
        if (! $this->requireConfirmed()) {
            return;
        }
        if (! $this->enabled()) {
            return;
        }
        $row = MakerPayment::query()->where('job_id', (int) $job->id)->first();
        if ($row === null && $job->awarded_bid_id) {
            $bid = MakerBid::query()->find($job->awarded_bid_id);
            if ($bid) {
                $this->ensureDue($job, $bid);
                $row = MakerPayment::query()->where('job_id', (int) $job->id)->first();
            }
        }
        if ($row && (string) $row->status !== PaymentRules::STATUS_CONFIRMED) {
            throw new DomainException('입금 확인 후에 완료할 수 있습니다.', 422);
        }
    }

    public function isConfirmed(int $jobId): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        $row = MakerPayment::query()->where('job_id', $jobId)->first();

        return $row === null || (string) $row->status === PaymentRules::STATUS_CONFIRMED;
    }

    public function requireConfirmed(): bool
    {
        $cfg = $this->settings();

        return SettingsRules::boolish($cfg['require_confirmed'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRow(MakerPayment $row, int $actorId, bool $isAdmin): array
    {
        $job = null;
        try {
            $job = MakerJob::query()->find((int) $row->job_id);
        } catch (\Throwable) {
            $job = null;
        }
        $title = $job ? (string) $job->title : '';
        $canConfirm = $this->canConfirm($row, $actorId, $isAdmin);
        $canReport = $actorId > 0 && (int) $row->payer_user_id === $actorId
            && in_array((string) $row->status, [PaymentRules::STATUS_DUE, PaymentRules::STATUS_REPORTED], true);
        $canRefund = $isAdmin && (string) $row->status !== PaymentRules::STATUS_REFUNDED;

        return [
            'id' => (int) $row->id,
            'job_id' => (int) $row->job_id,
            'job_title' => $title,
            'bid_id' => $row->bid_id !== null ? (int) $row->bid_id : null,
            'payer_user_id' => (int) $row->payer_user_id,
            'payee_user_id' => $row->payee_user_id !== null ? (int) $row->payee_user_id : null,
            'amount' => (int) $row->amount,
            'amount_label' => PaymentRules::amountLabel($row->amount),
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

    private function requireRow(int $jobId): MakerPayment
    {
        if (! $this->enabled()) {
            throw new DomainException('결제 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        $row = MakerPayment::query()->where('job_id', $jobId)->first();
        if ($row === null) {
            throw new DomainException('결제 내역이 없습니다. 낙찰 후 생성됩니다.', 404);
        }

        return $row;
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
        $company = null;
        try {
            if ($bid->company_id) {
                $company = MakerCompany::query()->find($bid->company_id);
            }
            if ($company === null) {
                $company = MakerCompany::query()->where('user_id', (int) $bid->user_id)->first();
            }
        } catch (\Throwable) {
            $company = null;
        }
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
