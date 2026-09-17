<?php

namespace Modules\Custom\MakerBids\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJob;

/**
 * Seed realistic Korean dummy bids onto a job for UI/demo checks.
 *
 * NAS: cd /volume1/web/3dsdptj && php82 artisan maker-bids:seed-dummy-bids 5
 */
class SeedDummyBidsCommand extends Command
{
    public const SEED_MARKER = '[seed-dummy]';

    /** Reserved synthetic user ids for dummy bidders (no real G7 user required). */
    public const DUMMY_USER_BASE = 900501;

    protected $signature = 'maker-bids:seed-dummy-bids
                            {jobId=5 : Target maker_jobs.id}
                            {--fresh : Delete prior seed-dummy bids (and unused seed companies) for this job first}
                            {--count=5 : How many dummy bids to ensure (3–5 recommended)}';

    protected $description = 'Seed Korean dummy bids (and bidder companies if needed) onto a maker job';

    /**
     * @return list<array{name:string,kind:string,amount:int,days:int,message:string,manager:string,phone:string,email:string,bio:string}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => '[더미] 한빛3D스튜디오',
                'kind' => 'company',
                'amount' => 185000,
                'days' => 7,
                'message' => self::SEED_MARKER.' PLA 출력·서포트 제거·표면 다듬기 포함. 시안 1회 무료 수정 가능합니다.',
                'manager' => '김민수',
                'phone' => '010-1111-2001',
                'email' => 'dummy.hanbit3d@example.invalid',
                'bio' => '더미 시드 업체 — 피규어·프로토타입 FDM 전문',
            ],
            [
                'name' => '[더미] 네오메이커랩',
                'kind' => 'company',
                'amount' => 220000,
                'days' => 5,
                'message' => self::SEED_MARKER.' 레진 고해상도 출력 가능. 도색은 별도 견적이며, 납기 5일(영업일) 기준입니다.',
                'manager' => '이서연',
                'phone' => '010-2222-2002',
                'email' => 'dummy.neomaker@example.invalid',
                'bio' => '더미 시드 업체 — 레진·소량 생산',
            ],
            [
                'name' => '[더미] 박준호 (개인)',
                'kind' => 'individual',
                'amount' => 98000,
                'days' => 10,
                'message' => self::SEED_MARKER.' 개인 제작자입니다. 야간 작업 가능, 재료비 포함 최저가 제안드립니다.',
                'manager' => '박준호',
                'phone' => '010-3333-2003',
                'email' => 'dummy.parkjh@example.invalid',
                'bio' => '더미 시드 개인 입찰자',
            ],
            [
                'name' => '[더미] 블루프린트웍스',
                'kind' => 'company',
                'amount' => 310000,
                'days' => 14,
                'message' => self::SEED_MARKER.' 모델링 보완+출력 패키지. 도면 검토 후 치수 피드백 드리겠습니다.',
                'manager' => '최유진',
                'phone' => '010-4444-2004',
                'email' => 'dummy.blueprint@example.invalid',
                'bio' => '더미 시드 업체 — 설계·출력 일괄',
            ],
            [
                'name' => '[더미] 스튜디오 메탈릭',
                'kind' => 'company',
                'amount' => 156000,
                'days' => 8,
                'message' => self::SEED_MARKER.' ABS/PETG 가능. 급행(+2일 단축) 시 추가 2만원. 서울 당일 픽업 협의 가능.',
                'manager' => '정하늘',
                'phone' => '010-5555-2005',
                'email' => 'dummy.metallic@example.invalid',
                'bio' => '더미 시드 업체 — 엔지니어링 플라스틱',
            ],
        ];
    }

    public function handle(): int
    {
        $jobId = (int) $this->argument('jobId');
        $count = max(1, min(count($this->templates()), (int) $this->option('count')));

        if (! Schema::hasTable('maker_jobs') || ! Schema::hasTable('maker_bids')) {
            $this->error('maker_jobs / maker_bids tables missing. Install/activate custom-maker_bids first.');

            return self::FAILURE;
        }

        $job = MakerJob::query()->find($jobId);
        if (! $job) {
            $this->error("Job id={$jobId} not found.");

            return self::FAILURE;
        }

        $this->info("Target job #{$job->id}: {$job->title} (owner user_id={$job->user_id}, status={$job->status})");

        if ($this->option('fresh')) {
            $removed = $this->clearSeededBids($jobId);
            $this->warn("fresh: removed {$removed} seed-dummy bid(s) for job {$jobId}");
        }

        $created = 0;
        $skipped = 0;
        $companiesCreated = 0;
        $ownerId = (int) $job->user_id;

        foreach (array_slice($this->templates(), 0, $count) as $i => $tpl) {
            $userId = self::DUMMY_USER_BASE + $i;
            if ($userId === $ownerId) {
                $userId += 100;
            }

            $company = $this->ensureDummyCompany($userId, $tpl, $companiesCreated);
            $existing = MakerBid::query()
                ->where('job_id', $jobId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                $skipped++;
                $this->line("  skip existing bid #{$existing->id} user={$userId} amount={$existing->amount}");

                continue;
            }

            $bid = MakerBid::query()->create([
                'job_id' => $jobId,
                'user_id' => $userId,
                'company_id' => $company?->id,
                'amount' => (int) $tpl['amount'],
                'days' => (int) $tpl['days'],
                'message' => $tpl['message'],
                'status' => 'pending',
            ]);
            $created++;
            $this->line("  + bid #{$bid->id} {$tpl['name']} amount={$tpl['amount']} days={$tpl['days']}");
        }

        $total = MakerBid::query()->where('job_id', $jobId)->count();
        $this->info("Done. created={$created} skipped={$skipped} companies_created={$companiesCreated} job_bids_total={$total}");

        return self::SUCCESS;
    }

    /**
     * @param  array{name:string,kind:string,amount:int,days:int,message:string,manager:string,phone:string,email:string,bio:string}  $tpl
     */
    private function ensureDummyCompany(int $userId, array $tpl, int &$companiesCreated): ?MakerCompany
    {
        if (! Schema::hasTable('maker_companies')) {
            return null;
        }

        $existing = MakerCompany::query()->where('user_id', $userId)->first();
        if ($existing) {
            if ($existing->status !== 'approved') {
                $existing->status = 'approved';
                $existing->reviewed_at = now();
                $existing->save();
            }

            return $existing;
        }

        $attrs = [
            'user_id' => $userId,
            'name' => $tpl['name'],
            'kind' => $tpl['kind'],
            'status' => 'approved',
            'note' => self::SEED_MARKER.' auto-created by maker-bids:seed-dummy-bids',
            'bio' => $tpl['bio'],
            'manager_name' => $tpl['manager'],
            'phone' => $tpl['phone'],
            'email' => $tpl['email'],
            'reviewed_at' => now(),
        ];

        if (Schema::hasColumn('maker_companies', 'admin_memo')) {
            $attrs['admin_memo'] = self::SEED_MARKER.' NAS demo seed — safe to delete';
        }

        $row = MakerCompany::query()->create($attrs);
        $companiesCreated++;

        return $row;
    }

    private function clearSeededBids(int $jobId): int
    {
        $marker = self::SEED_MARKER;
        $q = MakerBid::query()->where('job_id', $jobId)->where(function ($w) use ($marker) {
            $w->where('message', 'like', '%'.$marker.'%');
            if (Schema::hasTable('maker_companies')) {
                $seedCompanyIds = MakerCompany::query()
                    ->where(function ($c) use ($marker) {
                        $c->where('note', 'like', '%'.$marker.'%')
                            ->orWhere('name', 'like', '[더미]%');
                        if (Schema::hasColumn('maker_companies', 'admin_memo')) {
                            $c->orWhere('admin_memo', 'like', '%'.$marker.'%');
                        }
                    })
                    ->pluck('id')
                    ->all();
                if ($seedCompanyIds !== []) {
                    $w->orWhereIn('company_id', $seedCompanyIds);
                }
            }
            $w->orWhereBetween('user_id', [self::DUMMY_USER_BASE, self::DUMMY_USER_BASE + 20]);
        });

        $ids = $q->pluck('id')->all();
        $count = count($ids);
        if ($count > 0) {
            MakerBid::query()->whereIn('id', $ids)->delete();
        }

        // Drop seed companies that no longer have any bids.
        if (Schema::hasTable('maker_companies')) {
            $seedCompanies = MakerCompany::query()
                ->where(function ($c) use ($marker) {
                    $c->where('note', 'like', '%'.$marker.'%')
                        ->orWhere('name', 'like', '[더미]%');
                    if (Schema::hasColumn('maker_companies', 'admin_memo')) {
                        $c->orWhere('admin_memo', 'like', '%'.$marker.'%');
                    }
                })
                ->orWhereBetween('user_id', [self::DUMMY_USER_BASE, self::DUMMY_USER_BASE + 20])
                ->get();

            foreach ($seedCompanies as $company) {
                $stillUsed = MakerBid::query()->where('company_id', $company->id)->exists();
                if (! $stillUsed) {
                    $company->delete();
                }
            }
        }

        // Clear awarded_bid_id if it pointed at a removed seed bid.
        $job = MakerJob::query()->find($jobId);
        if ($job && $job->awarded_bid_id && ! MakerBid::query()->where('id', $job->awarded_bid_id)->exists()) {
            $job->awarded_bid_id = null;
            $job->save();
        }

        return $count;
    }
}
