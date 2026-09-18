<?php

namespace Modules\Custom\MakerBids\Support;

class CompanyRules
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public const KINDS = ['company', 'individual'];

    public const KIND_LABELS = [
        'company' => '업체',
        'individual' => '개인',
    ];

    public const STATUS_LABELS = [
        'pending' => '보류',
        'approved' => '승인',
        'rejected' => '거절',
    ];

    public const RATING_MIN = 0.0;

    public const RATING_MAX = 5.0;

    public const PRIORITY_MIN = 0;

    public const PRIORITY_MAX = 9999;

    public static function isApproved(?string $status): bool
    {
        return $status === 'approved';
    }

    /**
     * Admin-marked 지정업체. Applicants cannot set this.
     */
    public static function isDesignated(mixed $row): bool
    {
        if ($row === null) {
            return false;
        }
        if (is_bool($row) || is_int($row) || is_float($row) || is_string($row)) {
            return self::isTruthy($row);
        }
        if (is_array($row)) {
            return self::isTruthy($row['is_designated'] ?? false);
        }
        if (is_object($row)) {
            return self::isTruthy($row->is_designated ?? false);
        }

        return false;
    }

    public static function canApply(?string $existingStatus): bool
    {
        return $existingStatus === null || $existingStatus === 'rejected';
    }

    public static function canReject(?string $status): bool
    {
        return $status !== null && $status !== 'rejected';
    }

    public static function canApprove(?string $status): bool
    {
        return $status !== null && $status !== 'approved';
    }

    public static function canHold(?string $status): bool
    {
        return $status !== null && $status !== 'pending';
    }

    public static function isAllowedStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function isAllowedKind(string $kind): bool
    {
        return in_array($kind, self::KINDS, true);
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? (string) $status;
    }

    public static function kindLabel(?string $kind): string
    {
        return self::KIND_LABELS[$kind ?? ''] ?? (string) $kind;
    }

    public static function normalizeKind(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === 'individual' || $value === '개인' || $value === 'person') {
            return 'individual';
        }

        return 'company';
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    public static function normalizeJobTypes(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $trim = trim($raw);
            if (str_starts_with($trim, '[')) {
                $decoded = json_decode($trim, true);
                $raw = is_array($decoded) ? $decoded : preg_split('/[,\s]+/', $trim);
            } else {
                $raw = preg_split('/[,\s]+/', $trim);
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        $items = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = $item['slug'] ?? $item['value'] ?? $item['type'] ?? '';
            }
            if (! is_string($item) && ! is_int($item)) {
                continue;
            }
            $value = strtolower(trim((string) $item));
            if ($value !== '' && TypeCatalog::isValidSlugFormat($value) && ! in_array($value, $items, true)) {
                $items[] = TypeCatalog::normalizeSlug($value);
            }
        }

        return $items;
    }

    /**
     * Collect specialty types from JSON/`job_types` plus `job_type_{slug}` checkboxes.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function collectJobTypes(array $payload): array
    {
        $items = self::normalizeJobTypes($payload['job_types'] ?? null);
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'job_type_')) {
                continue;
            }
            if (! self::isTruthy($value)) {
                continue;
            }
            $slug = substr($key, strlen('job_type_'));
            $items = array_merge($items, self::normalizeJobTypes($slug));
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  mixed  $raw
     * @return list<array{at:?string,text:string}>
     */
    public static function normalizeClaimHistory(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $trim = trim($raw);
            if (str_starts_with($trim, '[')) {
                $decoded = json_decode($trim, true);
                $raw = is_array($decoded) ? $decoded : [$trim];
            } else {
                $raw = preg_split("/\r\n|\n|\r/", $trim) ?: [$trim];
            }
        }
        if (! is_array($raw)) {
            return [];
        }
        $items = [];
        foreach ($raw as $item) {
            if (is_string($item) || is_int($item)) {
                $text = trim((string) $item);
                if ($text === '') {
                    continue;
                }
                $items[] = ['at' => null, 'text' => mb_substr($text, 0, 2000)];
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? $item['note'] ?? $item['reason'] ?? ''));
            if ($text === '') {
                continue;
            }
            $at = $item['at'] ?? $item['date'] ?? $item['created_at'] ?? null;
            $items[] = [
                'at' => is_string($at) && $at !== '' ? $at : null,
                'text' => mb_substr($text, 0, 2000),
            ];
        }

        return array_slice($items, 0, 100);
    }

    public static function clampRatingScore(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        $value = is_numeric($raw) ? (float) $raw : 0.0;
        if ($value < self::RATING_MIN) {
            return self::RATING_MIN;
        }
        if ($value > self::RATING_MAX) {
            return self::RATING_MAX;
        }

        return round($value, 2);
    }

    public static function clampPriority(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : 0;
        if ($value < self::PRIORITY_MIN) {
            return self::PRIORITY_MIN;
        }
        if ($value > self::PRIORITY_MAX) {
            return self::PRIORITY_MAX;
        }

        return $value;
    }

    /**
     * Recommended first, then higher priority, then newer id.
     *
     * @param  array<string, mixed>|object|null  $a
     * @param  array<string, mixed>|object|null  $b
     */
    public static function compareListing(mixed $a, mixed $b): int
    {
        $ar = self::listingRecommended($a) ? 1 : 0;
        $br = self::listingRecommended($b) ? 1 : 0;
        if ($ar !== $br) {
            return $br <=> $ar;
        }
        $ap = self::listingPriority($a);
        $bp = self::listingPriority($b);
        if ($ap !== $bp) {
            return $bp <=> $ap;
        }

        return self::listingId($b) <=> self::listingId($a);
    }

    public static function listingRecommended(mixed $row): bool
    {
        if ($row === null) {
            return false;
        }
        if (is_array($row)) {
            return self::isTruthy($row['is_recommended'] ?? false);
        }
        if (is_object($row)) {
            return self::isTruthy($row->is_recommended ?? false);
        }

        return false;
    }

    public static function listingPriority(mixed $row): int
    {
        if ($row === null) {
            return 0;
        }
        if (is_array($row)) {
            return (int) ($row['priority'] ?? 0);
        }
        if (is_object($row)) {
            return (int) ($row->priority ?? 0);
        }

        return 0;
    }

    public static function listingId(mixed $row): int
    {
        if ($row === null) {
            return 0;
        }
        if (is_array($row)) {
            return (int) ($row['id'] ?? 0);
        }
        if (is_object($row)) {
            return (int) ($row->id ?? 0);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function applicantAttributes(array $payload): array
    {
        $jobTypes = self::collectJobTypes($payload);
        $bio = $payload['bio'] ?? $payload['note'] ?? null;
        $kind = self::normalizeKind($payload['kind'] ?? $payload['entity_type'] ?? 'company');

        return [
            'name' => $payload['name'],
            'kind' => $kind,
            'type' => $jobTypes[0] ?? ($payload['type'] ?? null),
            'job_types' => $jobTypes,
            'business_no' => $payload['business_no'] ?? null,
            'bio' => $bio,
            'note' => $bio,
            'homepage_url' => $payload['homepage_url'] ?? null,
            'portfolio_url' => $payload['portfolio_url'] ?? null,
            'manager_name' => $payload['manager_name'] ?? null,
            'phone' => $payload['phone'] ?? $payload['contact_phone'] ?? null,
            'email' => $payload['email'] ?? $payload['contact_email'] ?? null,
            'zipcode' => $payload['zipcode'] ?? null,
            'address' => $payload['address'] ?? null,
            'address_detail' => $payload['address_detail'] ?? null,
            'upload_token' => $payload['upload_token'] ?? null,
            'bank_name' => isset($payload['bank_name']) ? mb_substr(trim((string) $payload['bank_name']), 0, 80) : null,
            'account_no' => isset($payload['account_no']) ? mb_substr(trim((string) $payload['account_no']), 0, 80) : null,
            'account_holder' => isset($payload['account_holder']) ? mb_substr(trim((string) $payload['account_holder']), 0, 80) : null,
            'deposit_percent' => PaymentRules::normalizeDepositPercent($payload['deposit_percent'] ?? PaymentRules::DEFAULT_DEPOSIT_PERCENT),
            'deposit_terms' => isset($payload['deposit_terms']) ? mb_substr(trim((string) $payload['deposit_terms']), 0, 500) : null,
        ];
    }

    /**
     * Partial member profile update: omit blank/missing keys so logo-only saves keep profile.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public static function applicantUpdateAttributes(array $payload, array $existing = []): array
    {
        $attrs = [];
        $scalarKeys = [
            'name', 'business_no', 'homepage_url', 'portfolio_url', 'manager_name',
            'zipcode', 'address', 'address_detail', 'upload_token',
            'bank_name', 'account_no', 'account_holder', 'deposit_terms',
        ];
        foreach ($scalarKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $val = $payload[$key];
            if ($val === null || $val === '') {
                continue;
            }
            $attrs[$key] = $val;
        }
        if (array_key_exists('deposit_percent', $payload) && $payload['deposit_percent'] !== null && $payload['deposit_percent'] !== '') {
            $attrs['deposit_percent'] = PaymentRules::normalizeDepositPercent($payload['deposit_percent']);
        }
        if (array_key_exists('kind', $payload) && $payload['kind'] !== null && $payload['kind'] !== '') {
            $attrs['kind'] = self::normalizeKind($payload['kind']);
        } elseif (array_key_exists('entity_type', $payload) && $payload['entity_type'] !== null && $payload['entity_type'] !== '') {
            $attrs['kind'] = self::normalizeKind($payload['entity_type']);
        }
        if (array_key_exists('bio', $payload) && $payload['bio'] !== null && $payload['bio'] !== '') {
            $attrs['bio'] = $payload['bio'];
            $attrs['note'] = $payload['bio'];
        } elseif (array_key_exists('note', $payload) && $payload['note'] !== null && $payload['note'] !== '') {
            $attrs['bio'] = $payload['note'];
            $attrs['note'] = $payload['note'];
        }
        if (array_key_exists('phone', $payload) && $payload['phone'] !== null && $payload['phone'] !== '') {
            $attrs['phone'] = $payload['phone'];
        } elseif (array_key_exists('contact_phone', $payload) && $payload['contact_phone'] !== null && $payload['contact_phone'] !== '') {
            $attrs['phone'] = $payload['contact_phone'];
        }
        if (array_key_exists('email', $payload) && $payload['email'] !== null && $payload['email'] !== '') {
            $attrs['email'] = $payload['email'];
        } elseif (array_key_exists('contact_email', $payload) && $payload['contact_email'] !== null && $payload['contact_email'] !== '') {
            $attrs['email'] = $payload['contact_email'];
        }

        $hasJobTypeFlags = false;
        foreach ($payload as $key => $_) {
            if (is_string($key) && str_starts_with($key, 'job_type_')) {
                $hasJobTypeFlags = true;
                break;
            }
        }
        if (array_key_exists('job_types', $payload) || $hasJobTypeFlags) {
            $jobTypes = self::collectJobTypes($payload);
            // Unbound checkboxes (all false, no job_types key) → keep existing types.
            if ($jobTypes !== [] || array_key_exists('job_types', $payload)) {
                $attrs['job_types'] = $jobTypes;
                $attrs['type'] = $jobTypes[0] ?? ($payload['type'] ?? ($existing['type'] ?? null));
            }
        } elseif (array_key_exists('type', $payload) && $payload['type'] !== null && $payload['type'] !== '') {
            $attrs['type'] = $payload['type'];
        }

        return $attrs;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function applyRules(): array
    {
        return array_merge(self::profileFieldRules(), [
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', 'in:'.implode(',', self::KINDS)],
            'job_types' => ['nullable'],
            'type' => ['nullable', 'string', 'max:64'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'upload_token' => ['nullable', 'string', 'max:64'],
        ]);
    }

    /**
     * Existing company profile PATCH (logo-only safe): name/kind optional when omitted.
     *
     * @return array<string, list<string>>
     */
    public static function applyUpdateRules(): array
    {
        $rules = self::applyRules();
        $rules['name'] = ['sometimes', 'string', 'max:120'];
        $rules['kind'] = ['sometimes', 'string', 'in:'.implode(',', self::KINDS)];

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminStoreRules(): array
    {
        return array_merge(self::profileFieldRules(), self::adminOnlyRules(), [
            'user_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', self::KINDS)],
            'job_types' => ['nullable'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'bio' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'upload_token' => ['nullable', 'string', 'max:64'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminUpdateRules(): array
    {
        return array_merge(self::profileFieldRules(), self::adminOnlyRules(), [
            'name' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', self::KINDS)],
            'job_types' => ['nullable'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'bio' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rejectRules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000'],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
            'admin_memo' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function holdRules(): array
    {
        return [
            'hold_reason' => ['nullable', 'string', 'max:2000'],
            'admin_memo' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */

    public static function normalizeBusinessNo(mixed $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        return $digits;
    }

    public static function isValidBusinessNo(?string $digits): bool
    {
        if ($digits === null || $digits === '') {
            return true; // optional
        }

        // Format only (10 digits). Checksum is soft — many test/legacy numbers fail it.
        return (bool) preg_match('/^\d{10}$/', $digits);
    }

    public static function passesBusinessNoChecksum(?string $digits): bool
    {
        if ($digits === null || $digits === '' || ! preg_match('/^\d{10}$/', $digits)) {
            return false;
        }
        $w = [1, 3, 7, 1, 3, 7, 1, 3, 5];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * $w[$i];
        }
        $sum += intdiv((int) $digits[8] * 5, 10);
        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $digits[9];
    }

    public static function profileFieldRules(): array
    {
        return [
            'business_no' => ['nullable', 'string', 'max:32', 'regex:/^[0-9\-]{10,12}$/'],
            'homepage_url' => ['nullable', 'string', 'max:500'],
            'portfolio_url' => ['nullable', 'string', 'max:500'],
            'manager_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:120'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'account_no' => ['nullable', 'string', 'max:80'],
            'account_holder' => ['nullable', 'string', 'max:80'],
            'deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'deposit_terms' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminOnlyRules(): array
    {
        return [
            'admin_memo' => ['nullable', 'string', 'max:5000'],
            'hold_reason' => ['nullable', 'string', 'max:2000'],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
            'rating_score' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rating_count' => ['nullable', 'integer', 'min:0'],
            'claim_count' => ['nullable', 'integer', 'min:0'],
            'claim_history' => ['nullable'],
            'report_count' => ['nullable', 'integer', 'min:0'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_designated' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:'.self::PRIORITY_MIN, 'max:'.self::PRIORITY_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => '업체/개인 이름을 입력해 주세요.',
            'kind.required' => '등록유형을 선택해 주세요.',
            'kind.in' => '등록유형은 업체 또는 개인입니다.',
            'email.email' => '이메일 형식이 올바르지 않습니다.',
        ];
    }

    public static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (! is_string($value)) {
            return false;
        }
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
