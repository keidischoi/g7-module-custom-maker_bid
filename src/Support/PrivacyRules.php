<?php

namespace Modules\Custom\MakerBids\Support;

class PrivacyRules
{
    /**
     * Contact/address visible to job owner, admins, and awarded bidder after award/done.
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
        if ($viewerId < 1) {
            return false;
        }
        if ($ownerId !== null && $ownerId !== '' && (int) $ownerId === $viewerId) {
            return true;
        }
        if ($awardedBidderUserId === null || $awardedBidderUserId === '') {
            return false;
        }
        if ((int) $awardedBidderUserId !== $viewerId) {
            return false;
        }

        return in_array($jobStatus, ['awarded', 'done'], true);
    }

    /**
     * Archives / delivery files: owner, admin, and awarded maker after award/done.
     */
    public static function canViewArchives(
        int $viewerId,
        mixed $ownerId,
        string $jobStatus,
        mixed $awardedBidderUserId,
        bool $isAdmin = false,
    ): bool {
        if (self::canViewPersonal($viewerId, $ownerId, $jobStatus, $awardedBidderUserId, $isAdmin)) {
            return true;
        }
        if ($isAdmin) {
            return true;
        }
        if ($viewerId < 1 || $awardedBidderUserId === null || $awardedBidderUserId === '') {
            return false;
        }
        if ((int) $awardedBidderUserId !== $viewerId) {
            return false;
        }

        return in_array($jobStatus, ['awarded', 'done'], true);
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
