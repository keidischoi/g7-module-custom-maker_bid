<?php

namespace Modules\Custom\MakerBids\Support;

class DisputeRules
{
    public const STATUS = 'disputed';

    public const LABEL = '분쟁조정';

    /** Statuses where a claim/dispute can be opened (after award, including completed work). */
    public const OPEN_FROM = ['awarded', 'done'];

    /** After mediation admin may return to awarded or cancel/complete. */
    public const RESOLVE_TO = ['awarded', 'cancelled', 'done'];

    public const FACTORS = [
        'fraud' => '사기·허위',
        'quality' => '제품 품질 문제',
        'shipping' => '배송 문제',
        'delay' => '납기 지연',
        'damage' => '파손·분실',
        'spec' => '사양·수량 불일치',
        'communication' => '소통 불량',
        'payment' => '결제·대금 문제',
        'other' => '기타',
    ];

    public static function isDisputed(mixed $status): bool
    {
        return strtolower(trim((string) $status)) === self::STATUS;
    }

    public static function canOpen(string $status): bool
    {
        return in_array($status, self::OPEN_FROM, true) || self::isDisputed($status);
    }

    public static function canResolveTo(string $status): bool
    {
        return in_array($status, self::RESOLVE_TO, true);
    }

    public static function normalizeResolveTo(mixed $raw): ?string
    {
        $status = JobRules::normalizeStatus($raw);
        return self::canResolveTo($status) ? $status : null;
    }

    public static function factorOptions(): array
    {
        $out = [];
        foreach (self::FACTORS as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    public static function isAllowedFactor(string $factor): bool
    {
        return isset(self::FACTORS[$factor]);
    }

    public static function normalizeFactor(mixed $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return 'other';
        }
        $lower = strtolower($value);
        if (isset(self::FACTORS[$lower])) {
            return $lower;
        }
        $aliases = [
            '사기' => 'fraud',
            '허위' => 'fraud',
            '사기허위' => 'fraud',
            '사기·허위' => 'fraud',
            'scam' => 'fraud',
            'fake' => 'fraud',
            '품질' => 'quality',
            '제품품질' => 'quality',
            '제품 품질 문제' => 'quality',
            '품질문제' => 'quality',
            '배송' => 'shipping',
            '배송문제' => 'shipping',
            '배송 문제' => 'shipping',
            'delivery' => 'shipping',
            '지연' => 'delay',
            '납기' => 'delay',
            '납기지연' => 'delay',
            '납기 지연' => 'delay',
            '파손' => 'damage',
            '분실' => 'damage',
            '파손분실' => 'damage',
            '파손·분실' => 'damage',
            '사양' => 'spec',
            '수량' => 'spec',
            '불일치' => 'spec',
            '사양·수량 불일치' => 'spec',
            '소통' => 'communication',
            '소통불량' => 'communication',
            '소통 불량' => 'communication',
            '결제' => 'payment',
            '대금' => 'payment',
            '결제대금' => 'payment',
            '결제·대금 문제' => 'payment',
            '기타' => 'other',
        ];
        if (isset($aliases[$value])) {
            return $aliases[$value];
        }
        if (isset($aliases[$lower])) {
            return $aliases[$lower];
        }

        return 'other';
    }

    public static function factorLabel(mixed $raw): string
    {
        $factor = self::normalizeFactor($raw);

        return self::FACTORS[$factor] ?? self::FACTORS['other'];
    }

    public static function composeReason(mixed $factor, mixed $detail): string
    {
        $label = self::factorLabel($factor);
        $text = trim((string) $detail);
        if ($text === '' || $text === $label) {
            return $label;
        }
        if (str_starts_with($text, $label)) {
            return mb_substr($text, 0, 2000);
        }

        return mb_substr($label.' · '.$text, 0, 2000);
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'report' => '신고',
            'company_report' => '업체신고',
            default => '분쟁',
        };
    }

    public static function statusLabel(mixed $status): string
    {
        return strtolower(trim((string) $status)) === 'closed' ? '종결' : '진행중';
    }
}
