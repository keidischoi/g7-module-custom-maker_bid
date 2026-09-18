<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\BiddingRules;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\DisputeRules;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobRules;

class MarketplaceService
{
    public function closeExpired(): int
    {
        if (! Schema::hasTable('maker_jobs')) {
            return 0;
        }
        $q = MakerJob::query()
            ->whereNotNull('closes_at')
            ->where('closes_at', '<', now())
            ->whereIn('status', ['quote_request', 'request', 'open']);
        if (Schema::hasColumn('maker_jobs', 'bidding_status')) {
            $q->where(function ($qq) {
                $qq->whereNull('bidding_status')
                    ->orWhere('bidding_status', '!=', BiddingRules::CLOSED);
            });
        }
        $count = 0;
        foreach ($q->get() as $job) {
            if (Schema::hasColumn($job->getTable(), 'bidding_status')) {
                $job->bidding_status = BiddingRules::CLOSED;
                $job->bidding_closed_at = now();
            }
            $job->save();
            $this->notify((int) $job->user_id, 'deadline_closed', '입찰이 마감되었습니다.', (string) $job->title, (int) $job->id);
            $this->audit(null, 'job.auto_close', 'job', (int) $job->id, ['closes_at' => (string) $job->closes_at]);
            $count++;
        }

        return $count;
    }

    /** Notify owners of jobs closing within the next N hours (default 24). */
    public function notifyDeadlineSoon(int $withinHours = 24): int
    {
        if (! Schema::hasTable('maker_jobs')) {
            return 0;
        }
        $from = now();
        $to = now()->addHours(max(1, $withinHours));
        $q = MakerJob::query()
            ->whereNotNull('closes_at')
            ->whereBetween('closes_at', [$from, $to])
            ->whereIn('status', ['quote_request', 'request', 'open']);
        if (Schema::hasColumn('maker_jobs', 'bidding_status')) {
            $q->where(function ($qq) {
                $qq->whereNull('bidding_status')
                    ->orWhere('bidding_status', '!=', BiddingRules::CLOSED);
            });
        }
        $count = 0;
        foreach ($q->get() as $job) {
            $dup = false;
            if (Schema::hasTable('maker_notices')) {
                $dup = DB::table('maker_notices')
                    ->where('user_id', (int) $job->user_id)
                    ->where('job_id', (int) $job->id)
                    ->where('type', 'deadline_soon')
                    ->where('created_at', '>=', now()->subHours(12))
                    ->exists();
            }
            if ($dup) {
                continue;
            }
            $when = $job->closes_at ? (string) $job->closes_at : '';
            $this->notify(
                (int) $job->user_id,
                'deadline_soon',
                '마감 임박: '.(string) $job->title,
                '입찰 마감 시각: '.$when,
                (int) $job->id
            );
            $count++;
        }

        return $count;
    }

    /** Run scheduled maintenance: close expired + deadline notices. */
    public function runSchedule(): array
    {
        $purged = 0;
        try {
            $purged = app(JobFileService::class)->purgeExpired();
        } catch (\Throwable) {
            $purged = 0;
        }

        return [
            'closed' => $this->closeExpired(),
            'deadline_notices' => $this->notifyDeadlineSoon(24),
            'files_purged' => $purged,
        ];
    }

    public function notify(int $userId, string $type, string $title, ?string $body = null, ?int $jobId = null): void
    {
        if ($userId < 1) {
            return;
        }
        if (Schema::hasTable('maker_notices')) {
            DB::table('maker_notices')->insert([
                'user_id' => $userId,
                'job_id' => $jobId,
                'type' => $type,
                'title' => mb_substr($title, 0, 200),
                'body' => $body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->trySendEmail($userId, $title, $body);
        $this->trySendMemo($userId, $title, $body, $jobId);
    }

    private function trySendEmail(int $userId, string $title, ?string $body): void
    {
        try {
            $user = null;
            if (class_exists(\App\Models\User::class)) {
                $user = \App\Models\User::query()->find($userId);
            } elseif (Schema::hasTable('users')) {
                $user = DB::table('users')->where('id', $userId)->first();
            }
            $email = is_object($user) ? (string) ($user->email ?? '') : '';
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            if (! class_exists(\Illuminate\Support\Facades\Mail::class)) {
                return;
            }
            \Illuminate\Support\Facades\Mail::raw(
                trim($title."\n\n".(string) $body),
                static function ($message) use ($email, $title) {
                    $message->to($email)->subject('[의뢰/입찰] '.$title);
                }
            );
        } catch (\Throwable) {
            // Optional: core mail may be unconfigured.
        }
    }

    private function trySendMemo(int $userId, string $title, ?string $body, ?int $jobId): void
    {
        try {
            // Soft-integrate G7 memo/쪽지 modules when present (no hard dependency).
            if (function_exists('g7_send_memo')) {
                g7_send_memo($userId, $title, (string) $body);
                return;
            }
            if (function_exists('g7_memo_send')) {
                g7_memo_send($userId, $title, (string) $body);
                return;
            }
            $classes = [
                'App\\Services\\MemoService',
                'App\\Services\\MessageService',
                'App\\Services\\PrivateMessageService',
            ];
            foreach ($classes as $class) {
                if (! class_exists($class)) {
                    continue;
                }
                $svc = app($class);
                foreach (['sendSystem', 'sendToUser', 'send', 'notify'] as $method) {
                    if (! method_exists($svc, $method)) {
                        continue;
                    }
                    try {
                        $svc->{$method}($userId, $title, (string) $body);
                        return;
                    } catch (\Throwable) {
                        // try next signature / method
                    }
                }
            }
            if (Schema::hasTable('memos')) {
                $cols = Schema::getColumnListing('memos');
                $row = [
                    'title' => mb_substr($title, 0, 200),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (in_array('recv_user_id', $cols, true)) {
                    $row['recv_user_id'] = $userId;
                    $row['send_user_id'] = 0;
                    $row['content'] = (string) $body;
                } elseif (in_array('user_id', $cols, true)) {
                    $row['user_id'] = $userId;
                    if (in_array('content', $cols, true)) {
                        $row['content'] = (string) $body;
                    } elseif (in_array('body', $cols, true)) {
                        $row['body'] = (string) $body;
                    }
                } else {
                    return;
                }
                if ($jobId && in_array('job_id', $cols, true)) {
                    $row['job_id'] = $jobId;
                }
                DB::table('memos')->insert($row);
            }
        } catch (\Throwable) {
            // Optional channel — in-app maker_messages is the primary 1:1 thread.
        }
    }

    public function audit(?int $userId, string $action, string $type, ?int $id, array $meta = []): void
    {
        if (! Schema::hasTable('maker_audits')) {
            return;
        }
        DB::table('maker_audits')->insert([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'meta' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function notices(int $userId): array
    {
        if (! Schema::hasTable('maker_notices')) {
            return [];
        }

        return DB::table('maker_notices')->where('user_id', $userId)->orderByDesc('id')->limit(100)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function markRead(int $userId, int $id): void
    {
        if (! Schema::hasTable('maker_notices')) {
            return;
        }
        DB::table('maker_notices')->where('user_id', $userId)->where('id', $id)->update(['read_at' => now()]);
    }

    public function rejectBid(int $actorId, int $jobId, int $bidId): void
    {
        $job = MakerJob::query()->findOrFail($jobId);
        if ((int) $job->user_id !== $actorId) {
            throw new DomainException('의뢰 작성자만 거절할 수 있습니다.', 403);
        }
        $bid = MakerBid::query()->where('job_id', $jobId)->findOrFail($bidId);
        $bid->status = 'rejected';
        $bid->save();
        $this->notify((int) $bid->user_id, 'bid_rejected', '입찰이 거절되었습니다.', (string) $job->title, $jobId);
        $this->audit($actorId, 'bid.reject', 'bid', $bidId);
    }

    public function compare(int $jobId): array
    {
        return MakerBid::query()->where('job_id', $jobId)->orderBy('amount')->get()->map(function (MakerBid $b) {
            return [
                'id' => (int) $b->id,
                'user_id' => (int) $b->user_id,
                'amount' => (int) $b->amount,
                'days' => $b->days,
                'message' => $b->message,
                'status' => $b->status,
            ];
        })->all();
    }

    public function complete(int $userId, int $jobId, int $score, ?string $comment): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        if ((int) $job->user_id !== $userId) {
            throw new DomainException('의뢰자만 완료할 수 있습니다.', 403);
        }
        $job->status = 'done';
        $job->work_status = 'done';
        $job->save();
        $companyId = null;
        if ($job->awarded_bid_id) {
            $bid = MakerBid::query()->find($job->awarded_bid_id);
            $companyId = $bid?->company_id;
        }
        if (Schema::hasTable('maker_reviews')) {
            DB::table('maker_reviews')->updateOrInsert(
                ['job_id' => $jobId],
                [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'score' => max(1, min(5, $score)),
                    'comment' => $comment,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
        if ($companyId && Schema::hasTable('maker_reviews')) {
            $avg = DB::table('maker_reviews')->where('company_id', $companyId)->avg('score');
            $cnt = DB::table('maker_reviews')->where('company_id', $companyId)->count();
            MakerCompany::query()->where('id', $companyId)->update([
                'rating_score' => round((float) $avg, 2),
                'rating_count' => $cnt,
            ]);
        }
        if ($job->awarded_bid_id) {
            $winner = MakerBid::query()->find($job->awarded_bid_id);
            if ($winner) {
                $this->notify((int) $winner->user_id, 'complete', '의뢰가 완료·후기 처리되었습니다.', (string) $job->title, $jobId);
            }
        }
        $this->audit($userId, 'job.complete', 'job', $jobId, ['score' => $score]);

        return ['ok' => true, 'status' => 'done'];
    }

    public function setWork(int $userId, int $jobId, string $status, ?string $tracking = null, ?string $carrier = null): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok) {
            throw new DomainException('낙찰 당사자만 진행을 바꿈 수 있습니다.', 403);
        }
        $allow = ['producing', 'printing', 'shipping', 'delivered', 'done'];
        if (! in_array($status, $allow, true)) {
            throw new DomainException('잘못된 진행 상태입니다.', 422);
        }
        if ($status === 'shipping' && ($tracking === null || trim((string) $tracking) === '')) {
            throw new DomainException('발송 상태에는 송장번호가 필요합니다.', 422);
        }
        $job->work_status = $status;
        if ($tracking !== null) {
            $job->tracking_no = $tracking;
        }
        if ($carrier !== null) {
            $job->carrier = $carrier;
        }
        $job->save();
        $this->notify((int) $job->user_id, 'work', '작업 상태: '.$status, $tracking, $jobId);

        return ['work_status' => $status, 'tracking_no' => $job->tracking_no, 'carrier' => $job->carrier];
    }

    public function postMessage(int $userId, int $jobId, string $body): array
    {
        if (! Schema::hasTable('maker_messages')) {
            throw new DomainException('메시지 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        $body = trim($body);
        if ($body === '') {
            throw new DomainException('메시지 내용을 입력해 주세요.', 422);
        }
        $job = MakerJob::query()->findOrFail($jobId);
        if (! in_array((string) $job->status, ['awarded', 'done'], true)) {
            throw new DomainException('낙찰 후에만 대화할 수 있습니다.', 403);
        }
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok) {
            throw new DomainException('낙찰 당사자만 대화할 수 있습니다.', 403);
        }
        $id = DB::table('maker_messages')->insertGetId([
            'job_id' => $jobId,
            'user_id' => $userId,
            'body' => mb_substr($body, 0, 4000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $other = (int) $job->user_id === $userId ? (int) ($winner->user_id ?? 0) : (int) $job->user_id;
        if ($other > 0) {
            $this->notify($other, 'message', '새 쪽지 · '.(string) $job->title, mb_substr($body, 0, 80), $jobId);
        }

        return ['id' => $id, 'body' => mb_substr($body, 0, 4000)];
    }

    public function messages(int $userId, int $jobId): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok || ! Schema::hasTable('maker_messages')) {
            return [];
        }
        $ownerId = (int) $job->user_id;

        return DB::table('maker_messages')->where('job_id', $jobId)->orderBy('id')->get()->map(function ($r) use ($userId, $ownerId) {
            $uid = (int) $r->user_id;
            $role = $uid === $ownerId ? 'owner' : 'maker';

            return [
                'id' => (int) $r->id,
                'job_id' => (int) $r->job_id,
                'user_id' => $uid,
                'body' => (string) $r->body,
                'created_at' => (string) $r->created_at,
                'updated_at' => (string) ($r->updated_at ?? $r->created_at),
                'is_mine' => $uid === $userId,
                'role' => $role,
                'author_label' => $role === 'owner' ? '의뢰자' : '제작자',
            ];
        })->all();
    }

    public function claim(int $userId, int $jobId, string $reason, string $factor = 'other'): array
    {
        if (! Schema::hasTable('maker_claims')) {
            throw new DomainException('분쟁 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        $job = MakerJob::query()->findOrFail($jobId);
        $party = $this->disputeParty($userId, $job);
        if (! $party['is_party']) {
            throw new DomainException('의뢰 당사자만 분쟁을 접수할 수 있습니다.', 403);
        }
        if (! DisputeRules::canOpen((string) $job->status)) {
            throw new DomainException('낙찰 이후 의뢰만 분쟁 접수할 수 있습니다.', 422);
        }
        $factor = DisputeRules::normalizeFactor($factor !== '' ? $factor : $reason);
        $stored = DisputeRules::composeReason($factor, $reason);
        $row = [
            'job_id' => $jobId,
            'user_id' => $userId,
            'reason' => $stored,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('maker_claims', 'factor')) {
            $row['factor'] = $factor;
        }
        $id = DB::table('maker_claims')->insertGetId($row);
        $job->status = DisputeRules::STATUS;
        $job->save();
        $this->touchCompanyClaim($job, $stored);
        $other = $party['is_owner'] ? (int) $party['winner_id'] : (int) $job->user_id;
        if ($other > 0 && $other !== $userId) {
            $this->notify($other, 'dispute.open', '분쟁이 접수되었습니다.', DisputeRules::factorLabel($factor).' · '.(string) $job->title, $jobId);
        }
        $this->audit($userId, 'claim.open', 'job', $jobId, ['factor' => $factor, 'claim_id' => $id]);

        return $this->presentDisputeRow((object) array_merge($row, [
            'id' => $id,
            'job_title' => (string) $job->title,
            'job_status' => (string) $job->status,
        ]), 'claim');
    }

    public function report(int $userId, int $jobId, string $reason, string $factor = 'fraud'): array
    {
        if (! Schema::hasTable('maker_reports')) {
            throw new DomainException('신고 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        if ($userId < 1) {
            throw new DomainException('로그인이 필요합니다.', 401);
        }
        $job = MakerJob::query()->findOrFail($jobId);
        $factor = DisputeRules::normalizeFactor($factor !== '' ? $factor : $reason);
        $stored = DisputeRules::composeReason($factor, $reason);
        $row = [
            'job_id' => $jobId,
            'user_id' => $userId,
            'reason' => $stored,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('maker_reports', 'factor')) {
            $row['factor'] = $factor;
        }
        $id = DB::table('maker_reports')->insertGetId($row);
        $this->audit($userId, 'report.open', 'job', $jobId, ['factor' => $factor, 'report_id' => $id]);
        $this->notify((int) $job->user_id, 'report.open', '신고가 접수되었습니다.', DisputeRules::factorLabel($factor).' · '.(string) $job->title, $jobId);

        return $this->presentDisputeRow((object) array_merge($row, [
            'id' => $id,
            'job_title' => (string) $job->title,
            'job_status' => (string) $job->status,
        ]), 'report');
    }

    public function listMineDisputes(int $userId): array
    {
        $items = [];
        if (Schema::hasTable('maker_claims')) {
            foreach ($this->disputeQuery('maker_claims')->where('c.user_id', $userId)->orderByDesc('c.id')->limit(100)->get() as $row) {
                $items[] = $this->presentDisputeRow($row, 'claim');
            }
        }
        if (Schema::hasTable('maker_reports')) {
            foreach ($this->disputeQuery('maker_reports')->where('c.user_id', $userId)->orderByDesc('c.id')->limit(100)->get() as $row) {
                $items[] = $this->presentDisputeRow($row, 'report');
            }
        }
        usort($items, static fn (array $a, array $b) => ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));

        return array_values($items);
    }

    public function exportJob(int $jobId, string $doc = 'all'): array
    {
        $job = MakerJob::query()->with(['bids', 'jobType', 'awardedBid'])->findOrFail($jobId);
        $requestHtml = $this->buildRequestFormHtml($job);
        $quoteHtml = $this->buildQuoteFormHtml($job);
        $documents = [
            [
                'type' => 'request',
                'title' => '의뢰서',
                'filename' => 'request-'.$job->id.'.html',
                'html' => $requestHtml,
            ],
            [
                'type' => 'quote',
                'title' => '견적서',
                'filename' => 'quote-'.$job->id.'.html',
                'html' => $quoteHtml,
            ],
        ];
        if ($doc === 'request') {
            $documents = array_values(array_filter($documents, static fn ($d) => $d['type'] === 'request'));
        } elseif ($doc === 'quote') {
            $documents = array_values(array_filter($documents, static fn ($d) => $d['type'] === 'quote'));
        }
        $combined = $this->wrapPrintDocument(
            '의뢰·견적 문서 #'.$job->id,
            '<section class="doc-section">'.$this->innerFromWrapped($requestHtml).'</section>'
            .'<div class="doc-page-break"></div>'
            .'<section class="doc-section">'.$this->innerFromWrapped($quoteHtml).'</section>'
        );

        return [
            'filename' => 'job-'.$job->id.'-forms.html',
            'html' => $combined,
            'body' => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['</p>', '</tr>', '<br>', '<br/>', '<br />'], "\n", $combined))) ?? ''),
            'documents' => $documents,
            'print_hint' => '브라우저에서 인쇄 → PDF로 저장하면 됩니다.',
        ];
    }

    public function exportHtml(int $jobId, string $doc = 'all'): string
    {
        $data = $this->exportJob($jobId, $doc);
        if (($doc === 'request' || $doc === 'quote') && ! empty($data['documents'][0]['html'])) {
            return (string) $data['documents'][0]['html'];
        }

        return (string) $data['html'];
    }

    private function buildRequestFormHtml(MakerJob $job): string
    {
        $type = $job->relationLoaded('jobType') && $job->jobType
            ? (string) ($job->jobType->name ?? $job->type)
            : (string) $job->type;
        $budget = trim((string) ($job->budget_min ?? '').' ~ '.(string) ($job->budget_max ?? $job->budget ?? ''));
        $rows = [
            ['의뢰 번호', '#'.$job->id],
            ['제목', (string) $job->title],
            ['유형', $type],
            ['상태', (string) $job->status],
            ['예산', $budget !== '~' ? $budget.' 원' : '-'],
            ['마감', $job->closes_at ? (string) $job->closes_at : '-'],
            ['작성일', $job->created_at ? (string) $job->created_at : '-'],
        ];
        $desc = nl2br($this->e((string) $job->description));

        return $this->wrapPrintDocument(
            '제작 의뢰서',
            '<h1 class="doc-title">제작 의뢰서</h1>'
            .'<p class="doc-sub">Maker Bids · Job #'.(int) $job->id.'</p>'
            .'<table class="doc-table">'.$this->formTableRows($rows).'</table>'
            .'<h2 class="doc-h2">상세 설명</h2>'
            .'<div class="doc-box">'.$desc.'</div>'
            .'<div class="doc-sign"><div>의뢰자 확인: ____________</div><div>일자: ____ / ____ / ____</div></div>'
        );
    }

    private function buildQuoteFormHtml(MakerJob $job): string
    {
        $awarded = $job->awardedBid;
        $bids = $job->bids ?? collect();
        $rows = [
            ['의뢰 번호', '#'.$job->id],
            ['의뢰 제목', (string) $job->title],
            ['작업 상태', (string) ($job->work_status ?: '-')],
        ];
        if ($awarded) {
            $rows[] = ['낙찰 견적', number_format((int) $awarded->amount).' 원'];
            $rows[] = ['제작 기간', ($awarded->days !== null ? $awarded->days.' 일' : '-')];
            $rows[] = ['견적 메모', (string) ($awarded->message ?: '-')];
            $rows[] = ['입찰 번호', '#'.$awarded->id];
        } else {
            $rows[] = ['낙찰 견적', '미정'];
        }
        $bidLines = '';
        foreach ($bids as $bid) {
            $bidLines .= '<tr>'
                .'<td>#'.(int) $bid->id.'</td>'
                .'<td>'.number_format((int) $bid->amount).' 원</td>'
                .'<td>'.$this->e((string) ($bid->days ?? '-')).' 일</td>'
                .'<td>'.$this->e((string) $bid->status).'</td>'
                .'<td>'.$this->e(mb_substr((string) ($bid->message ?? ''), 0, 80)).'</td>'
                .'</tr>';
        }
        if ($bidLines === '') {
            $bidLines = '<tr><td colspan="5">입찰 없음</td></tr>';
        }

        return $this->wrapPrintDocument(
            '견적서',
            '<h1 class="doc-title">견적서</h1>'
            .'<p class="doc-sub">Maker Bids · Job #'.(int) $job->id.'</p>'
            .'<table class="doc-table">'.$this->formTableRows($rows).'</table>'
            .'<h2 class="doc-h2">입찰 목록</h2>'
            .'<table class="doc-table">'
            .'<thead><tr><th>번호</th><th>금액</th><th>기간</th><th>상태</th><th>메모</th></tr></thead>'
            .'<tbody>'.$bidLines.'</tbody></table>'
            .'<div class="doc-sign"><div>제작자 확인: ____________</div><div>의뢰자 확인: ____________</div></div>'
        );
    }

    /** @param list<array{0:string,1:string}> $rows */
    private function formTableRows(array $rows): string
    {
        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr><th>'.$this->e($row[0]).'</th><td>'.$this->e($row[1]).'</td></tr>';
        }

        return $html;
    }

    private function wrapPrintDocument(string $title, string $inner): string
    {
        $css = 'body{font-family:"Noto Sans KR",Malgun Gothic,sans-serif;color:#111;margin:24px;}'
            .'h1.doc-title{font-size:22px;margin:0 0 4px;}'
            .'.doc-sub{color:#555;margin:0 0 16px;font-size:12px;}'
            .'.doc-h2{font-size:15px;margin:20px 0 8px;border-bottom:1px solid #ccc;padding-bottom:4px;}'
            .'table.doc-table{width:100%;border-collapse:collapse;margin:8px 0 16px;font-size:13px;}'
            .'table.doc-table th,table.doc-table td{border:1px solid #bbb;padding:8px 10px;text-align:left;vertical-align:top;}'
            .'table.doc-table th{width:28%;background:#f3f4f6;font-weight:600;}'
            .'.doc-box{border:1px solid #bbb;padding:12px;min-height:80px;font-size:13px;line-height:1.5;}'
            .'.doc-sign{display:flex;justify-content:space-between;gap:24px;margin-top:32px;font-size:13px;}'
            .'.doc-actions{margin:0 0 16px;}'
            .'.doc-actions button{padding:8px 14px;font-size:13px;cursor:pointer;}'
            .'.doc-page-break{page-break-after:always;height:24px;}'
            .'@media print{.doc-actions{display:none!important;}body{margin:12mm;}}';

        return '<!DOCTYPE html><html lang="ko"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.$this->e($title).'</title><style>'.$css.'</style></head><body>'
            .'<div class="doc-actions"><button type="button" onclick="window.print()">인쇄 / PDF 저장</button></div>'
            .$inner
            .'</body></html>';
    }

    private function innerFromWrapped(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $body = $m[1];
            $body = preg_replace('/<div class="doc-actions">.*?<\/div>/is', '', $body, 1) ?? $body;

            return $body;
        }

        return $html;
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function logDownload(?int $userId, string $hash, ?int $jobId, ?string $ip): void
    {
        if (! Schema::hasTable('maker_file_logs')) {
            return;
        }
        DB::table('maker_file_logs')->insert([
            'user_id' => $userId,
            'hash' => $hash,
            'job_id' => $jobId,
            'ip' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function adminBundle(): array
    {
        $claims = [];
        if (Schema::hasTable('maker_claims')) {
            foreach ($this->disputeQuery('maker_claims')->orderByDesc('c.id')->limit(100)->get() as $row) {
                $claims[] = $this->presentDisputeRow($row, 'claim');
            }
        }
        $reports = [];
        if (Schema::hasTable('maker_reports')) {
            foreach ($this->disputeQuery('maker_reports')->orderByDesc('c.id')->limit(100)->get() as $row) {
                $reports[] = $this->presentDisputeRow($row, 'report');
            }
        }

        return [
            'claims' => $claims,
            'reports' => $reports,
            'factors' => DisputeRules::factorOptions(),
            'audits' => Schema::hasTable('maker_audits') ? DB::table('maker_audits')->orderByDesc('id')->limit(100)->get() : [],
            'file_logs' => Schema::hasTable('maker_file_logs') ? DB::table('maker_file_logs')->orderByDesc('id')->limit(100)->get() : [],
            'closed' => $this->closeExpired(),
        ];
    }

    public function resolveClaim(int $id, string $status, ?string $note, ?string $jobStatus = null): void
    {
        if (! Schema::hasTable('maker_claims')) {
            return;
        }
        $open = $status !== 'closed';
        DB::table('maker_claims')->where('id', $id)->update([
            'status' => $open ? 'open' : 'closed',
            'admin_note' => $note,
            'updated_at' => now(),
        ]);
        $row = DB::table('maker_claims')->where('id', $id)->first();
        $jobId = (int) ($row->job_id ?? 0);
        if ($jobId < 1) {
            return;
        }
        $job = MakerJob::query()->find($jobId);
        if ($job === null) {
            return;
        }
        if ($open) {
            $job->status = DisputeRules::STATUS;
            $job->save();
            $this->notify((int) $job->user_id, 'dispute.reopen', '분쟁이 다시 열렸습니다.', (string) $job->title, $jobId);
            return;
        }
        $resolveTo = DisputeRules::normalizeResolveTo($jobStatus);
        if ($resolveTo !== null) {
            $job->status = $resolveTo;
            $job->save();
        }
        $this->notify((int) $job->user_id, 'dispute.closed', '분쟁이 종결되었습니다.', JobRules::statusLabel((string) $job->status).' · '.(string) $job->title, $jobId);
        $this->audit(null, 'claim.closed', 'job', $jobId, ['claim_id' => $id, 'job_status' => (string) $job->status]);
    }
    public function reviewsForJob(int $jobId): array
    {
        if (! Schema::hasTable('maker_reviews')) {
            return [];
        }

        return DB::table('maker_reviews')->where('job_id', $jobId)->orderByDesc('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function reviewsForCompany(int $companyId): array
    {
        if (! Schema::hasTable('maker_reviews')) {
            return [];
        }

        return DB::table('maker_reviews')->where('company_id', $companyId)->orderByDesc('id')->limit(50)->get()->map(fn ($r) => (array) $r)->all();
    }

    public function resolveReport(int $id, string $status, ?int $actorId = null): void
    {
        if (! Schema::hasTable('maker_reports')) {
            return;
        }
        $status = $status === 'closed' ? 'closed' : 'open';
        DB::table('maker_reports')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
        $this->audit($actorId, 'report.'.$status, 'report', $id);
    }

    public function notifyJobEvent(MakerJob $job, string $type, string $title, ?string $body = null): void
    {
        $this->notify((int) $job->user_id, $type, $title, $body, (int) $job->id);
    }

    /**
     * @return array{is_owner: bool, is_winner: bool, is_party: bool, winner_id: int}
     */
    public function disputeParty(int $userId, MakerJob $job): array
    {
        $isOwner = $userId > 0 && (int) $job->user_id === $userId;
        $winnerId = 0;
        if ($job->awarded_bid_id) {
            try {
                $bid = $job->relationLoaded('awardedBid') ? $job->awardedBid : MakerBid::query()->find($job->awarded_bid_id);
                $winnerId = $bid ? (int) $bid->user_id : 0;
            } catch (\Throwable) {
                $winnerId = 0;
            }
        }
        $isWinner = $userId > 0 && $winnerId === $userId;

        return [
            'is_owner' => $isOwner,
            'is_winner' => $isWinner,
            'is_party' => $isOwner || $isWinner,
            'winner_id' => $winnerId,
        ];
    }

    private function disputeQuery(string $table)
    {
        return DB::table($table.' as c')
            ->leftJoin('maker_jobs as j', 'j.id', '=', 'c.job_id')
            ->select('c.*', 'j.title as job_title', 'j.status as job_status');
    }

    public function presentDisputeRow(object $row, string $kind): array
    {
        $factor = DisputeRules::normalizeFactor($row->factor ?? '');

        return [
            'id' => (int) ($row->id ?? 0),
            'kind' => $kind,
            'kind_label' => DisputeRules::kindLabel($kind),
            'job_id' => (int) ($row->job_id ?? 0),
            'job_title' => (string) ($row->job_title ?? ''),
            'job_status' => (string) ($row->job_status ?? ''),
            'job_status_label' => JobRules::statusLabel((string) ($row->job_status ?? '')),
            'user_id' => (int) ($row->user_id ?? 0),
            'factor' => $factor,
            'factor_label' => DisputeRules::factorLabel($factor),
            'reason' => (string) ($row->reason ?? ''),
            'status' => (string) ($row->status ?? 'open'),
            'status_label' => DisputeRules::statusLabel($row->status ?? 'open'),
            'admin_note' => (string) ($row->admin_note ?? ''),
            'created_at' => (string) ($row->created_at ?? ''),
            'updated_at' => (string) ($row->updated_at ?? ''),
            'job_href' => '/maker-bids/'.(int) ($row->job_id ?? 0),
            'work_href' => '/maker-bids/'.(int) ($row->job_id ?? 0).'/work',
        ];
    }

    private function touchCompanyClaim(MakerJob $job, string $text): void
    {
        if (! $job->awarded_bid_id) {
            return;
        }
        try {
            $bid = MakerBid::query()->find($job->awarded_bid_id);
            $companyId = $bid?->company_id;
            if (! $companyId) {
                return;
            }
            $company = MakerCompany::query()->find($companyId);
            if ($company === null) {
                return;
            }
            $company->claim_count = max(0, (int) $company->claim_count) + 1;
            $history = CompanyRules::normalizeClaimHistory($company->claim_history ?? []);
            $history[] = ['at' => date('Y-m-d H:i:s'), 'text' => mb_substr($text, 0, 2000)];
            $company->claim_history = $history;
            $company->save();
        } catch (\Throwable) {
        }
    }

}
