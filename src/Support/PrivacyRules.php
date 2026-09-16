<?php

namespace Modules\Custom\MakerBid\Support;

class PrivacyRules
{
    /**
     * Personal fields stay masked until production is confirmed (award),
     * except for the owner and admins.
     */
    public static function canViewPersonal(
        int $viewerId,
        mixed $ownerId,
        string $jobStatus,
        mixed $awardedBidderUserId,
        bool $isAdmin = false,
    ): bool {
        if ($isAdmin) {
            return true;
        }

        if ($viewerId > 0 && $ownerId !== null && $ownerId !== '' && (int) $ownerId === $viewerId) {
            return true;
        }

        if ($jobStatus !== 'awarded') {
            return false;
        }

        if ($viewerId <= 0 || $awardedBidderUserId === null || $awardedBidderUserId === '') {
            return false;
        }

        return (int) $awardedBidderUserId === $viewerId;
    }

    /**
     * Working archives follow the same counterparty rule as personal fields.
     */
    public static function canViewArchives(
        int $viewerId,
        mixed $ownerId,
        string $jobStatus,
        mixed $awardedBidderUserId,
        bool $isAdmin = false,
    ): bool {
        return self::canViewPersonal($viewerId, $ownerId, $jobStatus, $awardedBidderUserId, $isAdmin);
    }

    /**
     * @return list<string>
     */
    public static function personalKeys(): array
    {
        return [
            'contact_name',
            'contact_phone',
            'contact_hours',
            'contact_email',
            'zipcode',
            'address',
            'address_detail',
        ];
    }
}
