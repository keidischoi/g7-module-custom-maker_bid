<?php

namespace Modules\Custom\MakerBid\Support;

use DateTimeInterface;

class JobRules
{
    /** @deprecated 0.5.0 catalog slugs replace these. Kept as default-slug alias. */
    public const TYPES = ['modeling_3d', 'print_3d', 'full_package', 'character_figure', 'design_mockup', 'working_prototype'];

    public const STATUSES = ['hold', 'request', 'quote_request', 'open', 'awarded', 'done', 'cancelled'];

    public const LISTING_STATUSES = ['hold', 'request', 'quote_request'];

    public const PUBLIC_STATUSES = ['request', 'quote_request', 'open', 'awarded', 'done', 'cancelled'];

    public const BIDDABLE_STATUSES = ['request', 'quote_request', 'open'];

    public const HIDDEN_PUBLIC_STATUSES = ['hold'];

    public const TITLE_MAX = 200;

    public const SIZES_MAX = 20;

    /**
     * @param  list<string>|null  $allowedTypeSlugs
     * @return array<string, list<string>>
     */
    public static function createRules(?array $allowedTypeSlugs = null): array
    {
        $typeRule = ['required', 'string', 'max:64'];
        if ($allowedTypeSlugs !== null && $allowedTypeSlugs !== []) {
            $typeRule[] = 'in:'.implode(',', $allowedTypeSlugs);
        }

        return [
            'type' => $typeRule,
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'closes_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::LISTING_STATUSES)],
            'rush_fee_enabled' => ['nullable', 'boolean'],
            'rush_deadline' => ['nullable', 'date', 'required_if:rush_fee_enabled,1', 'required_if:rush_fee_enabled,true'],
            'schedule_premium_enabled' => ['nullable', 'boolean'],
            'size_w' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size_d' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size_h' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sizes' => ['nullable'],
            'provided_extensions' => ['nullable', 'array'],
            'provided_extensions.*' => ['string', 'in:'.implode(',', UploadRules::PROVIDED_EXTENSIONS)],
            'revision_enabled' => ['nullable', 'boolean'],
            'revision_count' => ['nullable', 'integer', 'min:0', 'max:99', 'required_if:revision_enabled,1', 'required_if:revision_enabled,true'],
            'revision_cost' => ['nullable', 'integer', 'min:0', 'required_if:revision_enabled,1', 'required_if:revision_enabled,true'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'contact_hours' => ['nullable', 'string', 'max:40'],
            'contact_hours_from' => ['nullable', 'string', 'max:8'],
            'contact_hours_to' => ['nullable', 'string', 'max:8'],
            'contact_email' => ['required', 'email', 'max:120'],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'manager_phone' => ['nullable', 'string', 'max:40'],
            'manager_email' => ['nullable', 'email', 'max:120'],
            'zipcode' => ['nullable', 'string', 'max:12'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'upload_token' => ['nullable', 'string', 'max:64'],
            'ext_stl' => ['nullable', 'boolean'],
            'ext_3mf' => ['nullable', 'boolean'],
            'ext_obj' => ['nullable', 'boolean'],
            'ext_step' => ['nullable', 'boolean'],
            'ext_stp' => ['nullable', 'boolean'],
            'ext_gcode' => ['nullable', 'boolean'],
            'ext_fbx' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function memberUpdateRules(): array
    {
        $rules = self::createRules();
        $rules['title'] = ['sometimes', 'string', 'max:'.self::TITLE_MAX];
        $rules['type'] = ['sometimes', 'string', 'max:64'];
        $rules['contact_name'] = ['sometimes', 'string', 'max:120'];
        $rules['contact_phone'] = ['sometimes', 'string', 'max:40'];
        $rules['contact_email'] = ['sometimes', 'email', 'max:120'];
        $rules['status'] = ['sometimes', 'string', 'in:'.implode(',', self::LISTING_STATUSES)];

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminUpdateRules(): array
    {
        $rules = self::memberUpdateRules();
        $rules['status'] = ['sometimes', 'string', 'in:'.implode(',', self::STATUSES)];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'title.required' => '제목을 입력해 주세요.',
            'type.required' => '유형을 선택해 주세요.',
            'contact_name.required' => '주문자명 또는 업체명을 입력해 주세요.',
            'contact_phone.required' => '연락처를 입력해 주세요.',
            'contact_email.required' => '이메일을 입력해 주세요.',
            'contact_email.email' => '이메일 형식이 올바르지 않습니다.',
            'rush_deadline.required_if' => '급행비를 적용하면 적용 조건 시각을 선택해 주세요.',
            'revision_count.required_if' => '최소 수정 횟수를 입력해 주세요.',
            'revision_cost.required_if' => '회당 또는 최대 수정비용을 입력해 주세요.',
            'manager_email.email' => '담당자 이메일 형식이 올바르지 않습니다.',
            'budget_max.gte' => '예산 최댓값은 최솟값보다 크거나 같아야 합니다.',
        ];
    }

    public static function isAllowedType(string $type): bool
    {
        return TypeCatalog::isKnownSlug($type) || TypeCatalog::isValidSlugFormat($type);
    }

    public static function isAllowedStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function isListingStatus(string $status): bool
    {
        return in_array($status, self::LISTING_STATUSES, true);
    }

    public static function isHiddenFromPublic(string $status): bool
    {
        return in_array($status, self::HIDDEN_PUBLIC_STATUSES, true);
    }

    public static function isBiddableStatus(string $status): bool
    {
        return in_array($status, self::BIDDABLE_STATUSES, true);
    }

    public static function isOpen(string $status, mixed $closesAt = null, ?DateTimeInterface $now = null): bool
    {
        if (! self::isBiddableStatus($status)) {
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

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'hold' => '보류',
            'request' => '의뢰',
            'quote_request', 'open' => '견적요청',
            'awarded' => '낙찰',
            'done' => '완료',
            'cancelled' => '취소',
            default => $status,
        };
    }

    public static function sizeLabel(mixed $w, mixed $d, mixed $h): ?string
    {
        if ($w === null && $d === null && $h === null) {
            return null;
        }

        return trim((string) ($w ?? '-')).' x '.trim((string) ($d ?? '-')).' x '.trim((string) ($h ?? '-')).' mm';
    }

    /**
     * Decode a JSON string or pass through an array of size rows.
     */
    public static function decodeSizesInput(mixed $sizes): mixed
    {
        if (is_string($sizes)) {
            $raw = trim($sizes);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : $sizes;
        }

        return $sizes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{name: string, w: ?int, d: ?int, h: ?int}>
     */
    public static function normalizeSizes(array $payload): array
    {
        $raw = self::decodeSizesInput($payload['sizes'] ?? null);
        $rows = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (count($rows) >= self::SIZES_MAX) {
                    break;
                }
                if (! is_array($item)) {
                    continue;
                }
                $row = self::normalizeSizeRow($item);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }
        if ($rows === []) {
            $fallback = self::normalizeSizeRow([
                'name' => $payload['size_name'] ?? '',
                'w' => $payload['size_w'] ?? null,
                'd' => $payload['size_d'] ?? null,
                'h' => $payload['size_h'] ?? null,
            ]);
            if ($fallback !== null) {
                $rows[] = $fallback;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{name?: mixed, w?: mixed, d?: mixed, h?: mixed}>|mixed  $sizes
     */
    public static function sizesLabel(mixed $sizes, mixed $w = null, mixed $d = null, mixed $h = null): ?string
    {
        $parts = [];
        if (is_array($sizes)) {
            foreach ($sizes as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $dims = self::sizeLabel($row['w'] ?? null, $row['d'] ?? null, $row['h'] ?? null);
                if ($name !== '' && $dims !== null) {
                    $parts[] = $name.' '.$dims;
                } elseif ($name !== '') {
                    $parts[] = $name;
                } elseif ($dims !== null) {
                    $parts[] = $dims;
                }
            }
        }
        if ($parts === []) {
            return self::sizeLabel($w, $d, $h);
        }

        return implode(' · ', $parts);
    }

    public static function sizesJson(array $sizes): string
    {
        if ($sizes === []) {
            return '';
        }

        return (string) json_encode($sizes, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{name: string, w: ?int, d: ?int, h: ?int}|null
     */
    private static function normalizeSizeRow(array $item): ?array
    {
        $name = trim((string) ($item['name'] ?? ''));
        if (function_exists('mb_strlen') && mb_strlen($name) > 80) {
            $name = function_exists('mb_substr') ? mb_substr($name, 0, 80) : substr($name, 0, 80);
        } elseif (strlen($name) > 80) {
            $name = substr($name, 0, 80);
        }
        $w = self::nullableDim($item['w'] ?? $item['size_w'] ?? null);
        $d = self::nullableDim($item['d'] ?? $item['size_d'] ?? null);
        $h = self::nullableDim($item['h'] ?? $item['size_h'] ?? null);
        if ($name === '' && $w === null && $d === null && $h === null) {
            return null;
        }

        return ['name' => $name, 'w' => $w, 'd' => $d, 'h' => $h];
    }

    private static function nullableDim(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;
        if ($n < 0) {
            return 0;
        }
        if ($n > 100000) {
            return 100000;
        }

        return $n;
    }

    public static function budgetLabel(mixed $min, mixed $max, mixed $legacy = null): ?string
    {
        $min = $min !== null && $min !== '' ? (int) $min : null;
        $max = $max !== null && $max !== '' ? (int) $max : ($legacy !== null && $legacy !== '' ? (int) $legacy : null);
        if ($min === null && $max === null) {
            return null;
        }
        if ($min !== null && $max !== null) {
            return number_format($min).'~'.number_format($max).'원';
        }

        return number_format((int) ($max ?? $min)).'원';
    }

    public static function contactHours(mixed $from, mixed $to, mixed $combined = null): ?string
    {
        if (is_string($combined) && trim($combined) !== '') {
            return trim($combined);
        }
        $from = is_string($from) ? trim($from) : '';
        $to = is_string($to) ? trim($to) : '';
        if ($from === '' && $to === '') {
            return null;
        }
        if ($from === '') {
            $from = '00:00';
        }
        if ($to === '') {
            $to = '00:00';
        }

        return $from.' ~ '.$to;
    }

    public static function datetimeLocal(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i');
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw.'T00:00';
        }
        $raw = str_replace(' ', 'T', $raw);

        return substr($raw, 0, 16);
    }

    public static function datetimeLabel(mixed $value): ?string
    {
        $local = self::datetimeLocal($value);
        if ($local === null) {
            return null;
        }

        return str_replace('T', ' ', $local);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function collectProvidedExtensions(array $payload): array
    {
        $fromArray = UploadRules::normalizeProvidedExtensions($payload['provided_extensions'] ?? null);
        $map = [
            'ext_stl' => 'STL',
            'ext_3mf' => '3MF',
            'ext_obj' => 'OBJ',
            'ext_step' => 'STEP',
            'ext_stp' => 'STP',
            'ext_gcode' => 'GCODE',
            'ext_fbx' => 'FBX',
        ];
        foreach ($map as $key => $ext) {
            if (! empty($payload[$key]) && ! in_array($ext, $fromArray, true)) {
                $fromArray[] = $ext;
            }
        }

        return $fromArray;
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
