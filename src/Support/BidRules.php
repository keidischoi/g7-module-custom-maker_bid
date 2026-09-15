<?php

namespace Modules\Custom\MakerBid\Support;

class BidRules
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    /**
     * Logged-in members and approved maker companies may bid.
     */
    public static function canBid(bool $isMember, bool $hasApprovedCompany): bool
    {
        return $isMember || $hasApprovedCompany;
    }

    public static function isOwnJob(?int $userId, mixed $jobUserId): bool
    {
        if ($userId === null || $userId <= 0) {
            return false;
        }

        if ($jobUserId === null || $jobUserId === '') {
            return false;
        }

        return (int) $jobUserId === $userId;
    }

    public static function canCreateOrUpdateOwn(
        int $actorId,
        ?int $bidUserId,
        bool $jobOpen,
        ?string $bidStatus = null,
    ): bool {
        if (! $jobOpen) {
            return false;
        }

        if ($bidUserId === null) {
            return true;
        }

        if ($bidUserId !== $actorId) {
            return false;
        }

        return $bidStatus === null || $bidStatus === 'pending';
    }

    public static function canUpdateOwn(int $actorId, int $bidUserId, bool $jobOpen, string $bidStatus): bool
    {
        return $actorId === $bidUserId
            && $jobOpen
            && $bidStatus === 'pending';
    }

    /**
     * @return array<string, list<string>>
     */
    public static function writeRules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminUpdateRules(): array
    {
        return [
            'amount' => ['sometimes', 'integer', 'min:1'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'message' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', self::STATUSES)],
        ];
    }

    public static function isAllowedStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
