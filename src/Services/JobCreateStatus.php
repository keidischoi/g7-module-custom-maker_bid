<?php

namespace Modules\Custom\MakerBids\Services;

use Modules\Custom\MakerBids\Support\JobRules;

/** Applied on public job create: draft stays draft, otherwise admin default_job_status. */
class JobCreateStatus
{
    public static function resolve(mixed $submitted, string $defaultStatus): string
    {
        $picked = JobRules::normalizeListingStatus($submitted ?? '');
        if ($picked === 'draft') {
            return 'draft';
        }
        $default = JobRules::normalizeListingStatus($defaultStatus);
        if (! in_array($default, JobRules::LISTING_STATUSES, true)) {
            return 'quote_request';
        }
        return $default;
    }
}
