<?php

namespace Modules\Custom\MakerBids\Support;

class DisputeRules
{
    public const STATUS = 'disputed';

    public const LABEL = '분쟁조정';

    /** Statuses where a claim/dispute can be opened (after award, before done). */
    public const OPEN_FROM = ['awarded'];

    /** After mediation admin may return to awarded or cancel. */
    public const RESOLVE_TO = ['awarded', 'cancelled', 'done'];

    public static function isDisputed(mixed $status): bool
    {
        return strtolower(trim((string) $status)) === self::STATUS;
    }

    public static function canOpen(string $status): bool
    {
        return in_array($status, self::OPEN_FROM, true);
    }

    public static function canResolveTo(string $status): bool
    {
        return in_array($status, self::RESOLVE_TO, true);
    }
}
