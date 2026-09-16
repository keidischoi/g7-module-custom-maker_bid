<?php

namespace Modules\Custom\MakerBids\Support;

class PrivacyRules
{
    /**
     * Contact/address (and archives that follow this rule) are visible
     * only to the job owner and admins.
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

        return $viewerId > 0
            && $ownerId !== null
            && $ownerId !== ''
            && (int) $ownerId === $viewerId;
    }

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
            'manager_name',
            'manager_phone',
            'manager_email',
        ];
    }
}
