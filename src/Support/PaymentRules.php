<?php

namespace Modules\Custom\MakerBids\Support;

class PaymentRules
{
    public const METHOD_BANK = 'bank_transfer';

    public const METHODS = [
        'bank_transfer' => '계좌이체',
    ];

    public const STATUS_DUE = 'due';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUSES = [
        'due' => '입금 대기',
        'reported' => '입금 신고',
        'confirmed' => '입금 확인',
        'refunded' => '환불',
    ];

    public const DEST_PLATFORM = 'platform';

    public const DEST_MAKER = 'maker';

    public const DESTINATIONS = [
        'platform' => '플랫폼 계좌',
        'maker' => '제작자 계좌',
    ];

    public static function normalizeMethod(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (isset(self::METHODS[$value])) {
            return $value;
        }
        $aliases = [
            '계좌이체' => self::METHOD_BANK,
            '무통장' => self::METHOD_BANK,
            '무통장입금' => self::METHOD_BANK,
            'bank' => self::METHOD_BANK,
            'transfer' => self::METHOD_BANK,
            'wire' => self::METHOD_BANK,
        ];

        return $aliases[$value] ?? ($aliases[(string) $raw] ?? self::METHOD_BANK);
    }

    public static function methodLabel(mixed $raw): string
    {
        $method = self::normalizeMethod($raw);

        return self::METHODS[$method] ?? self::METHODS[self::METHOD_BANK];
    }

    public static function isAllowedStatus(string $status): bool
    {
        return isset(self::STATUSES[$status]);
    }

    public static function normalizeStatus(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (isset(self::STATUSES[$value])) {
            return $value;
        }
        $aliases = [
            '입금대기' => self::STATUS_DUE,
            '입금 대기' => self::STATUS_DUE,
            '대기' => self::STATUS_DUE,
            'pending' => self::STATUS_DUE,
            '입금신고' => self::STATUS_REPORTED,
            '입금 신고' => self::STATUS_REPORTED,
            '신고' => self::STATUS_REPORTED,
            '입금확인' => self::STATUS_CONFIRMED,
            '입금 확인' => self::STATUS_CONFIRMED,
            '확인' => self::STATUS_CONFIRMED,
            'paid' => self::STATUS_CONFIRMED,
            '환불' => self::STATUS_REFUNDED,
        ];
        if (isset($aliases[$value])) {
            return $aliases[$value];
        }
        if (isset($aliases[(string) $raw])) {
            return $aliases[(string) $raw];
        }

        return self::STATUS_DUE;
    }

    public static function statusLabel(mixed $raw): string
    {
        $status = self::normalizeStatus($raw);

        return self::STATUSES[$status] ?? self::STATUSES[self::STATUS_DUE];
    }

    public static function normalizeDestination(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (isset(self::DESTINATIONS[$value])) {
            return $value;
        }
        $aliases = [
            '플랫폼' => self::DEST_PLATFORM,
            '플랫폼 계좌' => self::DEST_PLATFORM,
            '운영' => self::DEST_PLATFORM,
            'escrow' => self::DEST_PLATFORM,
            '제작자' => self::DEST_MAKER,
            '제작자 계좌' => self::DEST_MAKER,
            '업체' => self::DEST_MAKER,
            '낙찰자' => self::DEST_MAKER,
            'winner' => self::DEST_MAKER,
        ];
        if (isset($aliases[$value])) {
            return $aliases[$value];
        }
        if (isset($aliases[(string) $raw])) {
            return $aliases[(string) $raw];
        }

        return self::DEST_PLATFORM;
    }

    public static function destinationLabel(mixed $raw): string
    {
        $dest = self::normalizeDestination($raw);

        return self::DESTINATIONS[$dest] ?? self::DESTINATIONS[self::DEST_PLATFORM];
    }

    public static function methodOptions(): array
    {
        $out = [];
        foreach (self::METHODS as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    public static function destinationOptions(): array
    {
        $out = [];
        foreach (self::DESTINATIONS as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    public static function statusOptions(): array
    {
        $out = [];
        foreach (self::STATUSES as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    public static function interpolateNote(string $note, int $jobId, string $title = ''): string
    {
        $replaced = str_replace(
            ['{job_id}', '{id}', '{title}', '{JOB_ID}'],
            [(string) $jobId, (string) $jobId, $title, (string) $jobId],
            $note
        );

        return mb_substr(trim($replaced), 0, 120);
    }

    public static function amountLabel(mixed $amount): string
    {
        $n = (int) $amount;

        return number_format($n).'원';
    }

    public const KIND_FULL = 'full';

    public const KIND_DEPOSIT = 'deposit';

    public const KIND_BALANCE = 'balance';

    public const KINDS = [
        'full' => '전액',
        'deposit' => '계약금',
        'balance' => '잔금',
    ];

    public const DEFAULT_DEPOSIT_PERCENT = 30;

    public static function normalizeKind(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (isset(self::KINDS[$value])) {
            return $value;
        }
        $aliases = [
            '전액' => self::KIND_FULL,
            '전체' => self::KIND_FULL,
            '일시불' => self::KIND_FULL,
            '계약금' => self::KIND_DEPOSIT,
            '착수금' => self::KIND_DEPOSIT,
            '선금' => self::KIND_DEPOSIT,
            '잔금' => self::KIND_BALANCE,
            '잔액' => self::KIND_BALANCE,
            '후금' => self::KIND_BALANCE,
        ];

        return $aliases[$value] ?? ($aliases[(string) $raw] ?? self::KIND_FULL);
    }

    public static function kindLabel(mixed $raw): string
    {
        $kind = self::normalizeKind($raw);

        return self::KINDS[$kind] ?? self::KINDS[self::KIND_FULL];
    }

    public static function normalizeDepositPercent(mixed $raw, int $fallback = self::DEFAULT_DEPOSIT_PERCENT): int
    {
        if ($raw === null || $raw === '') {
            return max(0, min(100, $fallback));
        }
        if (is_string($raw) && str_ends_with(trim($raw), '%')) {
            $raw = rtrim(trim($raw), '% ');
        }
        $n = is_numeric($raw) ? (int) $raw : $fallback;
        if ($n < 0) {
            return 0;
        }
        if ($n > 100) {
            return 100;
        }

        return $n;
    }

    /**
     * @return array{0:int,1:int} deposit amount, balance amount
     */
    public static function splitAmounts(int $total, int $percent): array
    {
        $total = max(0, $total);
        $percent = max(0, min(100, $percent));
        if ($total < 1 || $percent <= 0) {
            return [0, $total];
        }
        if ($percent >= 100) {
            return [$total, 0];
        }
        $deposit = (int) floor($total * $percent / 100);
        if ($deposit < 1) {
            $deposit = 1;
        }
        if ($deposit >= $total) {
            return [$total, 0];
        }

        return [$deposit, $total - $deposit];
    }

    public static function kindSort(string $kind): int
    {
        return match (self::normalizeKind($kind)) {
            self::KIND_DEPOSIT => 1,
            self::KIND_FULL => 2,
            default => 3,
        };
    }

    public static function kindOptions(): array
    {
        $out = [];
        foreach (self::KINDS as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }
}
