<?php

namespace Modules\Custom\MakerBids\Services\Concerns;

use Illuminate\Http\Request;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Support\JobRules;
use Modules\Custom\MakerBids\Support\TypeCatalog;

trait JobServiceListing
{
    public function listPublic(Request $request): array
    {
        $q = MakerJob::query()->with(['jobType'])->withCount('bids');
        $ctx = $this->viewerFromRequest($request);

        if (! $ctx['isAdmin'] && ! $ctx['isMember'] && ! $this->settingBool('general.guests_see_list', true)) {
            return [];
        }
        if ($type = JobRules::listTypeFilter($request->query('type'))) {
            $q->where('type', $type);
        }
        $statusFilter = JobRules::listStatusFilter($request->query('status'));
        if ($statusFilter !== null) {
            if ($statusFilter === []) {
                return [];
            }
            $q->whereIn('status', $statusFilter);
        }
        if (! $ctx['isAdmin']) {
            $allowed = JobRules::visibleAudiencesFor($ctx['isMember'], $ctx['hasApprovedCompany'], $ctx['companyKind']);
            $q->where(function ($outer) use ($allowed, $ctx) {
                $outer->where(function ($pub) use ($allowed) {
                    $pub->whereNotIn('status', JobRules::HIDDEN_PUBLIC_STATUSES)
                        ->where(function ($aud) use ($allowed) {
                            $aud->whereIn('audience', $allowed)->orWhereNull('audience');
                        });
                });
                if ($ctx['userId'] > 0) {
                    $outer->orWhere('user_id', $ctx['userId']);
                }
            });
        }
        $this->applyListSearch($q, (string) $request->query('q', ''));
        $this->applyListSort($q, $request->query('sort'));
        $jobs = $q->limit(100)->get();
        $this->attachOwnerSearchMeta($jobs);
        return $jobs->map(fn (MakerJob $job) => $this->present($job, $ctx, false, false))->all();
    }

    public function listMine(int $userId): array
    {
        return MakerJob::query()->with(['jobType', 'files'])->withCount('bids')->where('user_id', $userId)->latest()->limit(100)->get()
            ->map(fn (MakerJob $job) => $this->present($job, $this->ownerContext($userId), true, true))->all();
    }

    public function listAdmin(Request $request): array
    {
        $q = MakerJob::query()->with(['jobType'])->withCount('bids')->latest();
        if ($type = $request->query('type')) {
            $q->where('type', TypeCatalog::normalizeSlug((string) $type));
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($userId = $request->query('user_id')) {
            $q->where('user_id', (int) $userId);
        }
        $ctx = ['userId' => 0, 'isAdmin' => true];
        return $q->limit(200)->get()->map(fn (MakerJob $job) => $this->present($job, $ctx, false, true))->all();
    }
}
