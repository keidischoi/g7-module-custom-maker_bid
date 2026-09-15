<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerJob;
use Modules\Custom\MakerBid\Support\AwardRules;
use Modules\Custom\MakerBid\Support\BidRules;
use Modules\Custom\MakerBid\Support\DomainException;
use Modules\Custom\MakerBid\Support\JobRules;

class JobService
{
    /**
     * @return Collection<int, MakerJob>
     */
    public function listPublic(Request $request): Collection
    {
        $q = MakerJob::query()->withCount('bids')->latest();
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return $q->limit(100)->get();
    }

    /**
     * @return Collection<int, MakerJob>
     */
    public function listMine(int $userId): Collection
    {
        return MakerJob::query()
            ->withCount('bids')
            ->where('user_id', $userId)
            ->latest()
            ->limit(100)
            ->get();
    }

    /**
     * Viewer flags for member UI (owner award, own-bid edit). Integer user ids stay internal.
     *
     * @return array{
     *     authenticated: bool,
     *     is_owner: bool,
     *     is_open: bool,
     *     can_bid: bool,
     *     can_award: bool,
     *     can_update_bid: bool,
     *     my_bid: ?MakerBid
     * }
     */
    public function viewerContext(int $userId, MakerJob $job): array
    {
        $open = $job->isOpen();
        $isOwner = BidRules::isOwnJob($userId, $job->user_id);
        $myBid = MakerBid::query()
            ->where('job_id', $job->id)
            ->where('user_id', $userId)
            ->first();
        $canUpdate = $myBid !== null
            && BidRules::canUpdateOwn($userId, (int) $myBid->user_id, $open, (string) $myBid->status);

        return [
            'authenticated' => $userId > 0,
            'is_owner' => $isOwner,
            'is_open' => $open,
            'can_bid' => $userId > 0 && ! $isOwner && $open,
            'can_award' => AwardRules::canAward($userId, $job->user_id) && $open,
            'can_update_bid' => $canUpdate,
            'my_bid' => $myBid,
        ];
    }

    public function findPublic(int $id): MakerJob
    {
        return MakerJob::query()->with('bids')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(int $userId, array $payload): MakerJob
    {
        return MakerJob::query()->create([
            'user_id' => $userId,
            'type' => $payload['type'],
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'budget' => $payload['budget'] ?? null,
            'status' => 'open',
            'closes_at' => $payload['closes_at'] ?? null,
        ]);
    }

    /**
     * @return Collection<int, MakerJob>
     */
    public function listAdmin(Request $request): Collection
    {
        $q = MakerJob::query()->withCount('bids')->latest();
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($userId = $request->query('user_id')) {
            $q->where('user_id', (int) $userId);
        }

        return $q->limit(200)->get();
    }

    public function findAdmin(int $id): MakerJob
    {
        return MakerJob::query()->with(['bids'])->withCount('bids')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateAdmin(int $id, array $payload): MakerJob
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->fill($payload);
        $job->save();

        return $job->fresh() ?? $job;
    }

    public function hold(int $id): MakerJob
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->status = 'hold';
        $job->save();

        return $job;
    }

    public function cancel(int $id): MakerJob
    {
        $job = MakerJob::query()->findOrFail($id);
        $job->status = 'cancelled';
        $job->save();

        return $job;
    }

    public function destroy(int $id): void
    {
        $job = MakerJob::query()->findOrFail($id);
        MakerBid::query()->where('job_id', $job->id)->delete();
        $job->delete();
    }

    public function assertOpen(MakerJob $job): void
    {
        if (! JobRules::isOpen((string) $job->status, $job->closes_at)) {
            throw new DomainException('입찰이 마감된 의뢰입니다.', 422);
        }
    }
}
