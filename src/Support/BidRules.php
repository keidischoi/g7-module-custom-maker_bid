<?php

namespace Modules\Custom\MakerBids\Support;

class BidRules
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    /** 모두 — logged-in users. Guests cannot bid in this product. */
    public const ALLOW_ALL = 'all';

    public const ALLOW_ADMIN = 'admin';

    public const ALLOW_DESIGNATED = 'designated';

    public const ALLOW_APPROVED_COMPANY = 'approved_company';

    public const ALLOW_APPROVED_INDIVIDUAL = 'approved_individual';

    public const ALLOW_APPROVED_BIDDERS = 'approved_bidders';

    public const ALLOW_MEMBER = 'member';

    public const ALLOW_MODES = [
        self::ALLOW_ALL,
        self::ALLOW_ADMIN,
        self::ALLOW_DESIGNATED,
        self::ALLOW_APPROVED_COMPANY,
        self::ALLOW_APPROVED_INDIVIDUAL,
        self::ALLOW_APPROVED_BIDDERS,
        self::ALLOW_MEMBER,
    ];

    public const ALLOW_LABELS = [
        self::ALLOW_ALL => '모두',
        self::ALLOW_ADMIN => '관리자',
        self::ALLOW_DESIGNATED => '지정업체',
        self::ALLOW_APPROVED_COMPANY => '모든 등록된 업체',
        self::ALLOW_APPROVED_INDIVIDUAL => '등록된 개인회원',
        self::ALLOW_APPROVED_BIDDERS => '모든 등록된 업체 & 등록된 개인회원',
        self::ALLOW_MEMBER => '일반회원',
    ];

    public static function normalizeAllow(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(['&amp;', '＋', '+'], '&', $value);
        if (in_array($value, ['admin', 'admins', '관리자'], true)) {
            return self::ALLOW_ADMIN;
        }
        if (in_array($value, ['designated', 'designated_company', '지정업체'], true)) {
            return self::ALLOW_DESIGNATED;
        }
        if (in_array($value, [
            'approved_bidders',
            'registered_bidders',
            'company_and_individual',
            '모든 등록된 업체 & 등록된 개인회원',
            '모든 등록된 업체&등록된 개인회원',
        ], true)) {
            return self::ALLOW_APPROVED_BIDDERS;
        }
        if (in_array($value, [
            'approved_company',
            'registered_company',
            'company',
            '모든 등록된 업체',
            '업체',
        ], true)) {
            return self::ALLOW_APPROVED_COMPANY;
        }
        if (in_array($value, [
            'approved_individual',
            'registered_individual',
            'individual',
            'person',
            '등록된 개인회원',
            '개인',
        ], true)) {
            return self::ALLOW_APPROVED_INDIVIDUAL;
        }
        if (in_array($value, ['member', 'regular', 'regular_member', '일반회원'], true)) {
            return self::ALLOW_MEMBER;
        }

        return self::ALLOW_ALL;
    }

    public static function allowLabel(mixed $mode): string
    {
        $mode = self::normalizeAllow($mode);

        return self::ALLOW_LABELS[$mode] ?? self::ALLOW_LABELS[self::ALLOW_ALL];
    }

    /**
     * Global bid-allow gate. Guests never bid (product has no guest bidding).
     *
     * 모두 = any logged-in user. 일반회원 = logged-in without an approved
     * company/individual bidder registration.
     */
    public static function canBid(
        bool $isMember,
        bool $hasApprovedCompany,
        mixed $mode = self::ALLOW_ALL,
        bool $isAdmin = false,
        mixed $companyKind = null,
        bool $isDesignated = false,
    ): bool {
        if (! $isMember && ! $hasApprovedCompany) {
            return false;
        }
        $approvedCompany = $hasApprovedCompany && CompanyRules::normalizeKind($companyKind) === 'company';
        $approvedIndividual = $hasApprovedCompany && CompanyRules::normalizeKind($companyKind) === 'individual';

        return match (self::normalizeAllow($mode)) {
            self::ALLOW_ADMIN => $isAdmin,
            self::ALLOW_DESIGNATED => $hasApprovedCompany && $isDesignated,
            self::ALLOW_APPROVED_COMPANY => $approvedCompany,
            self::ALLOW_APPROVED_INDIVIDUAL => $approvedIndividual,
            self::ALLOW_APPROVED_BIDDERS => $approvedCompany || $approvedIndividual,
            self::ALLOW_MEMBER => $isMember && ! $hasApprovedCompany,
            default => $isMember || $hasApprovedCompany,
        };
    }

    public static function denyMessage(mixed $mode): string
    {
        return match (self::normalizeAllow($mode)) {
            self::ALLOW_ADMIN => '관리자만 입찰할 수 있습니다.',
            self::ALLOW_DESIGNATED => '지정업체로 지정·승인된 업체만 입찰할 수 있습니다.',
            self::ALLOW_APPROVED_COMPANY => '등록된 업체만 입찰할 수 있습니다.',
            self::ALLOW_APPROVED_INDIVIDUAL => '등록된 개인회원만 입찰할 수 있습니다.',
            self::ALLOW_APPROVED_BIDDERS => '등록된 업체 또는 등록된 개인회원만 입찰할 수 있습니다.',
            self::ALLOW_MEMBER => '일반회원만 입찰할 수 있습니다.',
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
