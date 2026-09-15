<?php

namespace Modules\Custom\MakerBid\Support;

use DateTimeInterface;

class JobRules
{
    public const TYPES = ['print_3d', 'design', 'manufacture'];

    public const STATUSES = ['open', 'awarded', 'done', 'cancelled', 'hold'];

    public const TITLE_MAX = 200;

    /**
     * @return array<string, list<string>>
     */
    public static function createRules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'closes_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminUpdateRules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:'.implode(',', self::TYPES)],
            'title' => ['sometimes', 'string', 'max:'.self::TITLE_MAX],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', self::STATUSES)],
            'closes_at' => ['nullable', 'date'],
        ];
    }

    public static function isAllowedType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public static function isAllowedStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function isOpen(string $status, mixed $closesAt = null, ?DateTimeInterface $now = null): bool
    {
        if ($status !== 'open') {
            return false;
        }

        if ($closesAt === null || $closesAt === '') {
            return true;
        }

        $ts = self::timestamp($closesAt);
        if ($ts === null) {
            return true;
        }

        $nowTs = $now?->getTimestamp() ?? time();

        return $ts > $nowTs;
    }

    private static function timestamp(mixed $closesAt): ?int
    {
        if ($closesAt instanceof DateTimeInterface) {
            return $closesAt->getTimestamp();
        }

        if (is_int($closesAt) || is_float($closesAt)) {
            return (int) $closesAt;
        }

        if (! is_string($closesAt) || $closesAt === '') {
            return null;
        }

        $ts = strtotime($closesAt);

        return $ts === false ? null : $ts;
    }
}
