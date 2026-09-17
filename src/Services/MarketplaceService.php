<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\BiddingRules;
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
        return [
            'closed' => $this->closeExpired(),
            'deadline_notices' => $this->notifyDeadlineSoon(24),
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
            // Soft-integrate G7 memo/message modules when present.
            if (function_exists('g7_send_memo')) {
                g7_send_memo($userId, $title, (string) $body);
                return;
            }
            if (class_exists(\App\Services\MemoService::class)) {
                app(\App\Services\MemoService::class)->sendSystem($userId, $title, (string) $body);
                return;
            }
            if (Schema::hasTable('memos')) {
                DB::table('memos')->insert([
                    'recv_user_id' => $userId,
                    'send_user_id' => 0,
                    'title' => mb_substr($title, 0, 200),
                    'content' => (string) $body,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Optional channel.
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
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok) {
            throw new DomainException('낙찰 후에만 대화할 수 있습니다.', 403);
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
            $this->notify($other, 'message', '새 대화', mb_substr($body, 0, 80), $jobId);
        }

        return ['id' => $id];
    }

    public function messages(int $userId, int $jobId): array
    {
        $job = MakerJob::query()->findOrFail($jobId);
        $winner = $job->awarded_bid_id ? MakerBid::query()->find($job->awarded_bid_id) : null;
        $ok = (int) $job->user_id === $userId || ($winner && (int) $winner->user_id === $userId);
        if (! $ok || ! Schema::hasTable('maker_messages')) {
            return [];
        }

        return DB::table('maker_messages')->where('job_id', $jobId)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function claim(int $userId, int $jobId, string $reason): array
    {
        if (! Schema::hasTable('maker_claims')) {
            throw new DomainException('클레임 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        $id = DB::table('maker_claims')->insertGetId([
            'job_id' => $jobId,
            'user_id' => $userId,
            'reason' => mb_substr($reason, 0, 2000),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($userId, 'claim.open', 'job', $jobId);

        return ['id' => $id];
    }

    public function report(int $userId, int $jobId, string $reason): array
    {
        if (! Schema::hasTable('maker_reports')) {
            throw new DomainException('신고 기능을 사용할 수 없습니다. 마이그레이션을 적용하세요.', 503);
        }
        $id = DB::table('maker_reports')->insertGetId([
            'job_id' => $jobId,
            'user_id' => $userId,
            'reason' => mb_substr($reason, 0, 2000),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($userId, 'report.open', 'job', $jobId);

        return ['id' => $id];
    }

    public function exportJob(int $jobId): array
    {
        $job = MakerJob::query()->with(['bids', 'jobType'])->findOrFail($jobId);
        $lines = [
            '의뢰 #'.$job->id,
            '제목: '.$job->title,
            '유형: '.$job->type,
            '상태: '.$job->status,
            '예산: '.$job->budget_min.' ~ '.$job->budget_max,
            '설명:',
            (string) $job->description,
        ];
        foreach ($job->bids as $bid) {
            $lines[] = '입찰 #'.$bid->id.' '.$bid->amount.'원 / '.$bid->days.'일 / '.$bid->status;
        }

        return ['filename' => 'job-'.$job->id.'.txt', 'body' => implode("\n", $lines)];
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
        return [
            'claims' => Schema::hasTable('maker_claims') ? DB::table('maker_claims')->orderByDesc('id')->limit(100)->get() : [],
            'reports' => Schema::hasTable('maker_reports') ? DB::table('maker_reports')->orderByDesc('id')->limit(100)->get() : [],
            'audits' => Schema::hasTable('maker_audits') ? DB::table('maker_audits')->orderByDesc('id')->limit(100)->get() : [],
            'file_logs' => Schema::hasTable('maker_file_logs') ? DB::table('maker_file_logs')->orderByDesc('id')->limit(100)->get() : [],
            'closed' => $this->closeExpired(),
        ];
    }

    public function resolveClaim(int $id, string $status, ?string $note): void
    {
        if (! Schema::hasTable('maker_claims')) {
            return;
        }
        DB::table('maker_claims')->where('id', $id)->update([
            'status' => $status === 'closed' ? 'closed' : 'open',
            'admin_note' => $note,
            'updated_at' => now(),
        ]);
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

}
