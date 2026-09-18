<?php

namespace Modules\Custom\MakerBids\Support;

use DateTimeInterface;

class JobRules
{
    public const TYPES = ['modeling_3d', 'print_3d', 'full_package', 'character_figure', 'design_mockup', 'working_prototype'];

    public const STATUSES = ['pending', 'hold', 'request', 'quote_request', 'open', 'awarded', 'done', 'cancelled', 'draft', 'disputed'];

    public const LISTING_STATUSES = ['pending', 'hold', 'request', 'quote_request', 'draft'];

    public const PUBLIC_STATUSES = ['request', 'quote_request', 'open', 'awarded', 'done', 'cancelled'];

    public const BIDDABLE_STATUSES = ['request', 'quote_request', 'open'];

    public const HIDDEN_PUBLIC_STATUSES = ['pending', 'hold', 'draft', 'disputed'];

    public const AUDIENCES = ['all', 'company', 'individual', 'admin'];

    public const LIST_SORT_LATEST = 'latest';
    public const LIST_SORT_CREATED = 'created';
    public const LIST_SORT_VIEWS = 'views';
    public const LIST_SORT_STATUS = 'status';
    public const LIST_SORTS = [self::LIST_SORT_LATEST, self::LIST_SORT_CREATED, self::LIST_SORT_VIEWS, self::LIST_SORT_STATUS];
    public const TITLE_MAX = 200;
    public const SIZES_MAX = 20;

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
            'terms_agreed' => ['nullable', 'boolean'],
            'audience' => ['nullable', 'string', 'in:'.implode(',', self::AUDIENCES)],
            'rush_fee_enabled' => ['nullable', 'boolean'],
            'rush_deadline' => ['nullable', 'date', 'required_if:rush_fee_enabled,1', 'required_if:rush_fee_enabled,true'],
            'schedule_premium_enabled' => ['nullable', 'boolean'],
            'size_w' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size_d' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'size_h' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sizes' => ['nullable'],
            'provided_extensions' => ['nullable'],
            'provided_extensions.*' => ['string', 'max:16'],
            'ownership_requested' => ['nullable', 'boolean'],
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
            'ext_dwg' => ['nullable', 'boolean'],
        ];
    }

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

    public static function adminUpdateRules(): array
    {
        $rules = self::memberUpdateRules();
        $rules['status'] = ['sometimes', 'string', 'in:'.implode(',', self::STATUSES)];
        return $rules;
    }

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
    public static function isAllowedStatus(string $status): bool { return in_array($status, self::STATUSES, true); }
    public static function isListingStatus(string $status): bool { return in_array($status, self::LISTING_STATUSES, true); }
    public static function isHiddenFromPublic(string $status): bool { return in_array($status, self::HIDDEN_PUBLIC_STATUSES, true); }
    public static function isBiddableStatus(string $status): bool { return in_array($status, self::BIDDABLE_STATUSES, true); }

    public static function normalizeListingStatus(mixed $raw): string
    {
        if (is_array($raw)) {
            foreach (['status', 'value', 'slug', 'id', 'label', 'name'] as $k) {
                if (isset($raw[$k]) && ! is_array($raw[$k]) && (string) $raw[$k] !== '') {
                    $raw = $raw[$k];
                    break;
                }
            }
        }
        $value = trim((string) $raw);
        if ($value === '') {
            return 'quote_request';
        }
        $map = [
            '견적요청' => 'quote_request',
            '입찰진행' => 'quote_request',
            '입찰중' => 'quote_request',
            '의뢰' => 'request',
            '보류' => 'hold',
            '승인대기' => 'pending',
            '대기' => 'pending',
            '승인' => 'quote_request',
            '임시저장' => 'draft',
            '초안' => 'draft',
            'open' => 'quote_request',
            'OPEN' => 'quote_request',
            'pending' => 'pending',
            'pending_approval' => 'pending',
        ];
        if (isset($map[$value])) {
            return $map[$value];
        }
        $lower = strtolower($value);
        if (isset($map[$lower])) {
            return $map[$lower];
        }
        if (in_array($lower, self::LISTING_STATUSES, true)) {
            return $lower;
        }
        return 'quote_request';
    }

    public static function normalizeAudience(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['company', 'company_only', '업체만', '업체'], true)) return 'company';
        if (in_array($value, ['individual', 'individual_only', '개인만', '개인', 'person'], true)) return 'individual';
        if (in_array($value, ['admin', 'admin_only', 'admins', '관리자', '관리자만'], true)) return 'admin';
        return 'all';
    }

    public static function audienceLabel(?string $audience): string
    {
        return match (self::normalizeAudience($audience)) {
            'company' => '업체만',
            'individual' => '개인만',
            'admin' => '관리자',
            default => '전체',
        };
    }

    public static function viewerRole(bool $isMember, bool $hasApprovedCompany, mixed $companyKind = null): string
    {
        if ($hasApprovedCompany && CompanyRules::normalizeKind($companyKind) === 'company') return 'company';
        if ($isMember) return 'individual';
        return 'guest';
    }

    public static function visibleAudiencesFor(bool $isMember, bool $hasApprovedCompany, mixed $companyKind = null): array
    {
        $role = self::viewerRole($isMember, $hasApprovedCompany, $companyKind);
        if ($role === 'company') return ['all', 'company'];
        if ($role === 'individual') return ['all', 'individual'];
        return ['all'];
    }

    public static function canViewAudience(mixed $audience, bool $isOwner, bool $isAdmin, bool $isMember, bool $hasApprovedCompany, mixed $companyKind = null): bool
    {
        if ($isOwner || $isAdmin) return true;
        $scope = self::normalizeAudience($audience);
        if ($scope === 'admin') return false;
        return in_array($scope, self::visibleAudiencesFor($isMember, $hasApprovedCompany, $companyKind), true);
    }

    public static function canBidAudience(mixed $audience, bool $isMember, bool $hasApprovedCompany, mixed $companyKind = null, mixed $allowMode = BidRules::ALLOW_ALL, bool $isAdmin = false, bool $isDesignated = false): bool
    {
        $scope = self::normalizeAudience($audience);
        if ($scope === 'admin') return $isAdmin;
        if (! BidRules::canBid($isMember, $hasApprovedCompany, $allowMode, $isAdmin, $companyKind, $isDesignated)) return false;
        $mode = BidRules::normalizeAllow($allowMode);
        if ($isAdmin && $mode === BidRules::ALLOW_ADMIN) return true;
        $role = self::viewerRole($isMember, $hasApprovedCompany, $companyKind);
        if ($scope === 'company') return $role === 'company';
        if ($scope === 'individual') return $role === 'individual';
        return $isMember;
    }

    public static function listTypeFilter(mixed $type): ?string
    {
        $raw = strtolower(trim((string) $type));
        if ($raw === '' || in_array($raw, ['undefined', 'null', '*', 'all'], true)) return null;
        return TypeCatalog::normalizeSlug((string) $type);
    }

    public static function listStatusFilter(mixed $status): ?array
    {
        $raw = strtolower(trim((string) $status));
        if ($raw === '' || in_array($raw, ['undefined', 'null', '*', 'all'], true)) return null;
        if ($raw === 'open' || $raw === 'biddable') return self::BIDDABLE_STATUSES;
        if (in_array($raw, ['분쟁조정', 'dispute', 'disputed'], true)) return ['disputed'];
        if (in_array($raw, ['임시저장', '임시', 'draft'], true)) return ['draft'];
        if (! self::isAllowedStatus($raw)) return [];
        return [$raw];
    }

    public static function normalizeListSort(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['created', 'created_asc', '등록순', '등록', 'oldest'], true)) return self::LIST_SORT_CREATED;
        if (in_array($value, ['views', 'view', 'view_count', '조회순', '조회', 'popular', 'hits'], true)) return self::LIST_SORT_VIEWS;
        if (in_array($value, ['status', '상태순', '상태'], true)) return self::LIST_SORT_STATUS;
        return self::LIST_SORT_LATEST;
    }

    public static function isOpen(string $status, mixed $closesAt = null, ?DateTimeInterface $now = null): bool
    {
        if (! self::isBiddableStatus($status)) return false;
        if ($closesAt === null || $closesAt === '') return true;
        $ts = self::timestamp($closesAt);
        if ($ts === null) return true;
        return $ts > ($now?->getTimestamp() ?? time());
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => '승인대기',
            'hold' => '보류',
            'request' => '의뢰',
            'quote_request', 'open' => '견적요청',
            'awarded' => '낙찰',
            'done' => '완료',
            'cancelled' => '취소',
            'draft' => '임시저장',
            'disputed' => '분쟁조정',
            default => $status,
        };
    }

    public static function sizeLabel(mixed $w, mixed $d, mixed $h): ?string
    {
        if ($w === null && $d === null && $h === null) return null;
        return trim((string) ($w ?? '-')).' x '.trim((string) ($d ?? '-')).' x '.trim((string) ($h ?? '-')).' mm';
    }

    public static function decodeSizesInput(mixed $sizes): mixed
    {
        if (is_string($sizes)) {
            $raw = trim($sizes);
            if ($raw === '') return [];
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : $sizes;
        }
        return $sizes;
    }

    public static function normalizeSizes(array $payload): array
    {
        $raw = self::decodeSizesInput($payload['sizes'] ?? null);
        $rows = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (count($rows) >= self::SIZES_MAX) break;
                if (! is_array($item)) continue;
                $row = self::normalizeSizeRow($item);
                if ($row !== null) $rows[] = $row;
            }
        }
        if ($rows === []) {
            $fallback = self::normalizeSizeRow([
                'name' => $payload['size_name'] ?? '',
                'w' => $payload['size_w'] ?? null,
                'd' => $payload['size_d'] ?? null,
                'h' => $payload['size_h'] ?? null,
            ]);
            if ($fallback !== null) $rows[] = $fallback;
        }
        return $rows;
    }

    public static function sizesLabel(mixed $sizes, mixed $w = null, mixed $d = null, mixed $h = null): ?string
    {
        $parts = [];
        if (is_array($sizes)) {
            foreach ($sizes as $row) {
                if (! is_array($row)) continue;
                $name = trim((string) ($row['name'] ?? ''));
                $dims = self::sizeLabel($row['w'] ?? null, $row['d'] ?? null, $row['h'] ?? null);
                if ($name !== '' && $dims !== null) $parts[] = $name.' '.$dims;
                elseif ($name !== '') $parts[] = $name;
                elseif ($dims !== null) $parts[] = $dims;
            }
        }
        return $parts === [] ? self::sizeLabel($w, $d, $h) : implode(' · ', $parts);
    }

    public static function sizesJson(array $sizes): string
    {
        return $sizes === [] ? '' : (string) json_encode($sizes, JSON_UNESCAPED_UNICODE);
    }

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
        if ($name === '' && $w === null && $d === null && $h === null) return null;
        return ['name' => $name, 'w' => $w, 'd' => $d, 'h' => $h];
    }

    private static function nullableDim(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) return null;
        $n = (int) $value;
        if ($n < 0) return 0;
        if ($n > 100000) return 100000;
        return $n;
    }

    public static function budgetLabel(mixed $min, mixed $max, mixed $legacy = null): ?string
    {
        $min = $min !== null && $min !== '' ? (int) $min : null;
        $max = $max !== null && $max !== '' ? (int) $max : ($legacy !== null && $legacy !== '' ? (int) $legacy : null);
        if ($min === null && $max === null) return null;
        if ($min !== null && $max !== null) return number_format($min).'~'.number_format($max).'원';
        return number_format((int) ($max ?? $min)).'원';
    }

    public static function contactHours(mixed $from, mixed $to, mixed $combined = null): ?string
    {
        if (is_string($combined) && trim($combined) !== '') return trim($combined);
        $from = is_string($from) ? trim($from) : '';
        $to = is_string($to) ? trim($to) : '';
        if ($from === '' && $to === '') return null;
        return ($from === '' ? '00:00' : $from).' ~ '.($to === '' ? '00:00' : $to);
    }

    public static function datetimeLocal(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) return $value->format('Y-m-d\TH:i');
        $raw = trim((string) $value);
        if ($raw === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) return $raw.'T00:00';
        return substr(str_replace(' ', 'T', $raw), 0, 16);
    }

    public static function datetimeLabel(mixed $value): ?string
    {
        $local = self::datetimeLocal($value);
        return $local === null ? null : str_replace('T', ' ', $local);
    }

    public static function collectProvidedExtensions(array $payload, ?array $allowed = null): array
    {
        $allowed = $allowed ?? UploadRules::PROVIDED_EXTENSIONS;
        $fromArray = UploadRules::normalizeProvidedExtensions($payload['provided_extensions'] ?? null, $allowed);
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'ext_') || empty($value)) continue;
            $ext = strtoupper(substr($key, 4));
            if ($ext === '' || ! UploadRules::isAllowedProvidedExtension($ext, $allowed)) continue;
            if (! in_array($ext, $fromArray, true)) $fromArray[] = $ext;
        }
        return $fromArray;
    }

    private static function timestamp(mixed $closesAt): ?int
    {
        if ($closesAt instanceof DateTimeInterface) return $closesAt->getTimestamp();
        if (is_int($closesAt) || is_float($closesAt)) return (int) $closesAt;
        if (! is_string($closesAt) || $closesAt === '') return null;
        $ts = strtotime($closesAt);
        return $ts === false ? null : $ts;
    }
}
