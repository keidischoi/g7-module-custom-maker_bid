<?php

namespace Modules\Custom\MakerBids\Support;

use DateTimeInterface;

class BiddingRules
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public static function normalize(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['closed', 'close', 'ended', 'end', '종료', '마감'], true)) {
            return self::CLOSED;
        }

        return self::OPEN;
    }

    public static function isOpen(mixed $biddingStatus, string $jobStatus, mixed $closesAt = null, ?DateTimeInterface $now = null): bool
    {
        if (self::normalize($biddingStatus) === self::CLOSED) {
            return false;
        }
        if (in_array($jobStatus, ['cancelled', 'done', 'hold'], true)) {
            return false;
        }
        if (! in_array($jobStatus, ['request', 'quote_request', 'open', 'awarded'], true)) {
            return false;
        }
        if ($closesAt === null || $closesAt === '') {
            return true;
        }

        return JobRules::isOpen(
            in_array($jobStatus, ['request', 'quote_request', 'open'], true) ? $jobStatus : 'open',
            $closesAt,
            $now,
        );
    }
}
