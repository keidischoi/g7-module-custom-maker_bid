<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerJob;
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
