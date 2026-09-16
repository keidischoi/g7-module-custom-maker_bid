<?php

namespace Modules\Custom\MakerBid\Support;

class BidRules
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    /** Logged-in members (모두). Guests cannot bid. */
    public const ALLOW_MEMBERS = 'members';

    public const ALLOW_ADMIN = 'admin';

    public const ALLOW_DESIGNATED = 'designated';

    public const ALLOW_COMPANY = 'company';

    public const ALLOW_INDIVIDUAL = 'individual';

    public const ALLOW_MODES = [
        self::ALLOW_MEMBERS,
        self::ALLOW_ADMIN,
        self::ALLOW_DESIGNATED,
        self::ALLOW_COMPANY,
        self::ALLOW_INDIVIDUAL,
    ];

    public const ALLOW_LABELS = [
        self::ALLOW_MEMBERS => '모두',
        self::ALLOW_ADMIN => '관리자',
        self::ALLOW_DESIGNATED => '지정업체',
        self::ALLOW_COMPANY => '업체',
        self::ALLOW_INDIVIDUAL => '개인',
    ];

    public static function normalizeAllow(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['admin', 'admins', '관리자'], true)) {
            return self::ALLOW_ADMIN;
        }
        if (in_array($value, ['designated', 'designated_company', '지정업체'], true)) {
            return self::ALLOW_DESIGNATED;
        }
        if (in_array($value, ['company', '업체'], true)) {
            return self::ALLOW_COMPANY;
        }
        if (in_array($value, ['individual', '개인', 'person'], true)) {
            return self::ALLOW_INDIVIDUAL;
        }

        return self::ALLOW_MEMBERS;
    }

    public static function allowLabel(mixed $mode): string
    {
        $mode = self::normalizeAllow($mode);

        return self::ALLOW_LABELS[$mode] ?? self::ALLOW_LABELS[self::ALLOW_MEMBERS];
    }

    /**
     * Global bid-allow gate. Guests never bid.
     *
     * Default 모두 = logged-in members (or an approved company row).
     * Extra args are ignored when $mode is members, so existing 2-arg calls stay valid.
     */
    public static function canBid(
        bool $isMember,
        bool $hasApprovedCompany,
        mixed $mode = self::ALLOW_MEMBERS,
        bool $isAdmin = false,
        mixed $companyKind = null,
        bool $isDesignated = false,
    ): bool {
        if (! $isMember && ! $hasApprovedCompany) {
            return false;
        }

        return match (self::normalizeAllow($mode)) {
            self::ALLOW_ADMIN => $isAdmin,
            self::ALLOW_DESIGNATED => $hasApprovedCompany && $isDesignated,
            self::ALLOW_COMPANY => $hasApprovedCompany
                && CompanyRules::normalizeKind($companyKind) === 'company',
            self::ALLOW_INDIVIDUAL => $isMember
                && ! ($hasApprovedCompany && CompanyRules::normalizeKind($companyKind) === 'company'),
            default => $isMember || $hasApprovedCompany,
        };
    }

    public static function denyMessage(mixed $mode): string
    {
        return match (self::normalizeAllow($mode)) {
            self::ALLOW_ADMIN => '관리자만 입찰할 수 있습니다.',
            self::ALLOW_DESIGNATED => '지정업체로 지정·승인된 업체만 입찰할 수 있습니다.',
            self::ALLOW_COMPANY => '승인된 업체만 입찰할 수 있습니다.',
            self::ALLOW_INDIVIDUAL => '개인 입찰자만 입찰할 수 있습니다.',
            default => '로그인 회원만 입찰할 수 있습니다.',
        };
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

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => '대기',
            'accepted' => '낙찰',
            'rejected' => '거절',
            default => $status,
        };
    }
}
