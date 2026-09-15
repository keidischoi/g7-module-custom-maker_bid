<?php

namespace Modules\Custom\MakerBid\Support;

class CompanyRules
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public static function isApproved(?string $status): bool
    {
        return $status === 'approved';
    }

    public static function canApply(?string $existingStatus): bool
    {
        return $existingStatus === null || $existingStatus === 'rejected';
    }

    public static function canReject(?string $status): bool
    {
        return $status !== null && $status !== 'rejected';
    }

    public static function canApprove(?string $status): bool
    {
        return $status !== null && $status !== 'approved';
    }

    /**
     * @return array<string, list<string>>
     */
    public static function applyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'in:'.implode(',', JobRules::TYPES)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminStoreRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'in:'.implode(',', JobRules::TYPES)],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rejectRules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function isAllowedStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
