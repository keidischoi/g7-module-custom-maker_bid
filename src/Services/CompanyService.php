<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerCompany;
use Modules\Custom\MakerBid\Support\CompanyRules;
use Modules\Custom\MakerBid\Support\DomainException;

class CompanyService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(int $userId, array $payload): MakerCompany
    {
        $existing = MakerCompany::query()->where('user_id', $userId)->first();
        if (! CompanyRules::canApply($existing?->status)) {
            throw new DomainException('이미 신청 중이거나 승인된 업체가 있습니다.', 422);
        }

        $attrs = [
            'user_id' => $userId,
            'name' => $payload['name'],
            'type' => $payload['type'] ?? null,
            'status' => 'pending',
            'note' => $payload['note'] ?? null,
            'rejected_reason' => null,
            'reviewed_at' => null,
        ];

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();

            return $existing->fresh() ?? $existing;
        }

        return MakerCompany::query()->create($attrs);
    }

    public function mine(int $userId): ?MakerCompany
    {
        return MakerCompany::query()->where('user_id', $userId)->first();
    }

    /**
     * @return Collection<int, MakerCompany>
     */
    public function listAdmin(Request $request): Collection
    {
        $q = MakerCompany::query()->latest();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return $q->limit(200)->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeAdmin(array $payload): MakerCompany
    {
        $userId = (int) $payload['user_id'];
        if (MakerCompany::query()->where('user_id', $userId)->exists()) {
            throw new DomainException('해당 회원에게 이미 업체가 등록되어 있습니다.', 422);
        }

        $status = $payload['status'] ?? 'pending';

        return MakerCompany::query()->create([
            'user_id' => $userId,
            'name' => $payload['name'],
            'type' => $payload['type'] ?? null,
            'status' => $status,
            'note' => $payload['note'] ?? null,
            'reviewed_at' => in_array($status, ['approved', 'rejected'], true) ? now() : null,
            'rejected_reason' => $status === 'rejected' ? ($payload['note'] ?? null) : null,
        ]);
    }

    public function approve(int $id): MakerCompany
    {
        $row = MakerCompany::query()->findOrFail($id);
        if (! CompanyRules::canApprove($row->status)) {
            throw new DomainException('이미 승인된 업체입니다.', 422);
        }
        $row->status = 'approved';
        $row->rejected_reason = null;
        $row->reviewed_at = now();
        $row->save();

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reject(int $id, array $payload): MakerCompany
    {
        $row = MakerCompany::query()->findOrFail($id);
        if (! CompanyRules::canReject($row->status)) {
            throw new DomainException('이미 거절된 업체입니다.', 422);
        }
        $row->status = 'rejected';
        $row->rejected_reason = $payload['rejected_reason'] ?? $payload['note'] ?? $row->rejected_reason;
        if (isset($payload['note'])) {
            $row->note = $payload['note'];
        }
        $row->reviewed_at = now();
        $row->save();

        return $row;
    }

    public function destroy(int $id): void
    {
        $row = MakerCompany::query()->findOrFail($id);
        MakerBid::query()->where('company_id', $row->id)->update(['company_id' => null]);
        $row->delete();
    }
}
