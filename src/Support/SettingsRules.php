<?php

namespace Modules\Custom\MakerBids\Support;

class SettingsRules
{
    public const MODULE_ID = 'custom-maker_bids';

    public const CATEGORIES = ['menu', 'notices', 'general', 'payment'];

    public const NAV_INSERTS = ['append_row', 'prepend_row', 'after_shop', 'after_home'];

    public const NOTICE_MAX = 20000;

    public const LABEL_MAX = 40;

    /**
     * User pages that can show a 안내문.
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'list' => '의뢰목록',
            'create' => '의뢰서 작성',
            'bids' => '입찰현황',
            'history' => '이력',
            'company' => '입찰자 등록',
            'companies' => '입찰자 목록',
            'show' => '의뢰 상세',
            'edit' => '의뢰서 수정',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $notices = [];
        foreach (array_keys(self::pages()) as $page) {
            $notices[$page.'_enabled'] = false;
            $notices[$page.'_body'] = '';
        }

        return [
            'menu' => [
                'nav_js_enabled' => true,
                'nav_insert' => 'append_row',
                'nav_label' => '의뢰/입찰',
                'extension_user_base' => true,
                'extension_home' => true,
            ],
            'notices' => $notices,
            'general' => [
                'default_job_status' => 'quote_request',
                'guests_see_list' => true,
                'bid_allow' => BidRules::ALLOW_ALL,
                'provided_extensions' => implode(',', UploadRules::PROVIDED_EXTENSIONS),
            ],
            'payment' => [
                'method' => PaymentRules::METHOD_BANK,
                'destination' => PaymentRules::DEST_PLATFORM,
                'bank_name' => '',
                'account_no' => '',
                'account_holder' => '',
                'transfer_note' => '의뢰 #{job_id}',
                'instructions' => '낙찰 후 안내된 계좌로 이체하고, 작업실에서 입금자명을 적어 신고해 주세요. 입금이 확인되면 완료 처리할 수 있습니다.',
                'require_confirmed' => true,
                'default_deposit_percent' => PaymentRules::DEFAULT_DEPOSIT_PERCENT,
            ],
        ];
    }

    /**
     * Merge saved category payloads onto defaults (saved wins).
     *
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    public static function merge(array $saved): array
    {
        $out = self::defaults();
        foreach (self::CATEGORIES as $category) {
            $row = $saved[$category] ?? [];
            if (! is_array($row)) {
                continue;
            }
            foreach ($out[$category] as $key => $default) {
                if (array_key_exists($key, $row)) {
                    $out[$category][$key] = $row[$key];
                }
            }
        }

        return self::normalize($out);
    }

    /**
     * Accept nested categories or a flat admin form.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function fromInput(array $input): array
    {
        if (isset($input['form']) && is_array($input['form'])) {
            $input = array_merge($input, $input['form']);
        }
        $nested = [];
        foreach (self::CATEGORIES as $category) {
            if (isset($input[$category]) && is_array($input[$category])) {
                $nested[$category] = $input[$category];
            }
        }
        if ($nested !== []) {
            return self::merge($nested);
        }

        return self::merge(self::unflatten($input));
    }

    /**
     * @param  array<string, mixed>  $all
     * @return array<string, mixed>
     */
    public static function flatten(array $all): array
    {
        $flat = [];
        foreach (self::CATEGORIES as $category) {
            $row = is_array($all[$category] ?? null) ? $all[$category] : [];
            foreach ($row as $key => $value) {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    public static function unflatten(array $flat): array
    {
        $defaults = self::defaults();
        $out = [];
        foreach (self::CATEGORIES as $category) {
            $out[$category] = [];
            foreach (array_keys($defaults[$category]) as $key) {
                if (array_key_exists($key, $flat)) {
                    $out[$category][$key] = $flat[$key];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $all
     * @return array<string, mixed>
     */
    public static function normalize(array $all, bool $sanitizeBodies = true): array
    {
        $defaults = self::defaults();
        $menu = is_array($all['menu'] ?? null) ? $all['menu'] : [];
        $insert = (string) ($menu['nav_insert'] ?? $defaults['menu']['nav_insert']);
        if (! in_array($insert, self::NAV_INSERTS, true)) {
            $insert = 'append_row';
        }
        $label = trim((string) ($menu['nav_label'] ?? $defaults['menu']['nav_label']));
        if ($label === '') {
            $label = '의뢰/입찰';
        }
        if (function_exists('mb_substr')) {
            $label = mb_substr($label, 0, self::LABEL_MAX);
        } else {
            $label = substr($label, 0, self::LABEL_MAX);
        }

        $noticesIn = is_array($all['notices'] ?? null) ? $all['notices'] : [];
        $notices = [];
        foreach (array_keys(self::pages()) as $page) {
            $notices[$page.'_enabled'] = self::boolish($noticesIn[$page.'_enabled'] ?? false);
            $body = (string) ($noticesIn[$page.'_body'] ?? '');
            if (function_exists('mb_strlen') && mb_strlen($body) > self::NOTICE_MAX) {
                $body = mb_substr($body, 0, self::NOTICE_MAX);
            } elseif (strlen($body) > self::NOTICE_MAX) {
                $body = substr($body, 0, self::NOTICE_MAX);
            }
            $notices[$page.'_body'] = $sanitizeBodies ? self::sanitizeNotice($body) : $body;
        }

        $general = is_array($all['general'] ?? null) ? $all['general'] : [];
        $status = (string) ($general['default_job_status'] ?? 'quote_request');
        if ($status === 'open') {
            $status = 'quote_request';
        }
        if (! in_array($status, JobRules::LISTING_STATUSES, true)) {
            $status = 'quote_request';
        }

        $paymentIn = is_array($all['payment'] ?? null) ? $all['payment'] : [];
        $bankName = trim((string) ($paymentIn['bank_name'] ?? ''));
        $accountNo = trim((string) ($paymentIn['account_no'] ?? ''));
        $accountHolder = trim((string) ($paymentIn['account_holder'] ?? ''));
        $transferNote = trim((string) ($paymentIn['transfer_note'] ?? '의뢰 #{job_id}'));
        $instructions = (string) ($paymentIn['instructions'] ?? '');
        if (function_exists('mb_substr')) {
            $bankName = mb_substr($bankName, 0, 80);
            $accountNo = mb_substr($accountNo, 0, 80);
            $accountHolder = mb_substr($accountHolder, 0, 80);
            $transferNote = mb_substr($transferNote, 0, 120);
            $instructions = mb_substr($instructions, 0, 2000);
        } else {
            $bankName = substr($bankName, 0, 80);
            $accountNo = substr($accountNo, 0, 80);
            $accountHolder = substr($accountHolder, 0, 80);
            $transferNote = substr($transferNote, 0, 120);
            $instructions = substr($instructions, 0, 2000);
        }

        return [
            'menu' => [
                'nav_js_enabled' => self::boolish($menu['nav_js_enabled'] ?? true),
                'nav_insert' => $insert,
                'nav_label' => $label,
                'extension_user_base' => self::boolish($menu['extension_user_base'] ?? true),
                'extension_home' => self::boolish($menu['extension_home'] ?? true),
            ],
            'notices' => $notices,
            'general' => [
                'default_job_status' => $status,
                'guests_see_list' => self::boolish($general['guests_see_list'] ?? true),
                'bid_allow' => BidRules::normalizeAllow($general['bid_allow'] ?? BidRules::ALLOW_ALL),
                'provided_extensions' => self::normalizeProvidedExtensionsSetting(
                    $general['provided_extensions'] ?? implode(',', UploadRules::PROVIDED_EXTENSIONS)
                ),
            ],
            'payment' => [
                'method' => PaymentRules::normalizeMethod($paymentIn['method'] ?? PaymentRules::METHOD_BANK),
                'destination' => PaymentRules::normalizeDestination($paymentIn['destination'] ?? PaymentRules::DEST_PLATFORM),
                'bank_name' => $bankName,
                'account_no' => $accountNo,
                'account_holder' => $accountHolder,
                'transfer_note' => $transferNote !== '' ? $transferNote : '의뢰 #{job_id}',
                'instructions' => $instructions,
                'require_confirmed' => self::boolish($paymentIn['require_confirmed'] ?? true),
                'default_deposit_percent' => PaymentRules::normalizeDepositPercent(
                    $paymentIn['default_deposit_percent'] ?? PaymentRules::DEFAULT_DEPOSIT_PERCENT
                ),
            ],
        ];
    }

    /**
     * Nested public payload for user layouts / nav.js.
     *
     * @param  array<string, mixed>  $all
     * @return array<string, mixed>
     */
    public static function publicPayload(array $all): array
    {
        $all = self::merge($all);
        $notices = [];
        foreach (array_keys(self::pages()) as $page) {
            $enabled = self::boolish($all['notices'][$page.'_enabled'] ?? false);
            $body = trim((string) ($all['notices'][$page.'_body'] ?? ''));
            $notices[$page] = [
                'enabled' => $enabled,
                'body' => $enabled ? $body : '',
            ];
        }

        return [
            'menu' => $all['menu'],
            'notices' => $notices,
            'general' => $all['general'],
            'payment' => [
                'method' => $all['payment']['method'],
                'method_label' => PaymentRules::methodLabel($all['payment']['method']),
                'destination' => $all['payment']['destination'],
                'destination_label' => PaymentRules::destinationLabel($all['payment']['destination']),
                'bank_name' => $all['payment']['bank_name'],
                'account_no' => $all['payment']['account_no'],
                'account_holder' => $all['payment']['account_holder'],
                'transfer_note' => $all['payment']['transfer_note'],
                'instructions' => $all['payment']['instructions'],
                'require_confirmed' => $all['payment']['require_confirmed'],
                'default_deposit_percent' => PaymentRules::normalizeDepositPercent(
                    $all['payment']['default_deposit_percent'] ?? PaymentRules::DEFAULT_DEPOSIT_PERCENT
                ),
            ],
        ];
    }

    public static function sanitizeNotice(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = preg_replace('/<object\b[^>]*>.*?<\/object>/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        return $html;
    }


    /**
     * Canonical CSV of uppercase extensions for 제공 확장자 options.
     */
    public static function normalizeProvidedExtensionsSetting(mixed $raw): string
    {
        $list = UploadRules::parseExtensionList($raw);
        if ($list === []) {
            $list = UploadRules::PROVIDED_EXTENSIONS;
        }

        return implode(',', $list);
    }

    /**
     * @return list<string>
     */
    public static function providedExtensionList(mixed $raw = null): array
    {
        if ($raw === null) {
            return UploadRules::PROVIDED_EXTENSIONS;
        }
        $list = UploadRules::parseExtensionList(
            is_string($raw) || is_array($raw) ? $raw : self::normalizeProvidedExtensionsSetting($raw)
        );

        return $list !== [] ? $list : UploadRules::PROVIDED_EXTENSIONS;
    }

    public static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (! is_string($value)) {
            return (bool) $value;
        }
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function adminUpdateRules(): array
    {
        $rules = [
            'nav_js_enabled' => ['nullable'],
            'nav_insert' => ['nullable', 'string', 'in:'.implode(',', self::NAV_INSERTS)],
            'nav_label' => ['nullable', 'string', 'max:'.self::LABEL_MAX],
            'extension_user_base' => ['nullable'],
            'extension_home' => ['nullable'],
            'default_job_status' => ['nullable', 'string', 'in:'.implode(',', JobRules::LISTING_STATUSES)],
            'guests_see_list' => ['nullable'],
            'bid_allow' => ['nullable', 'string', 'in:'.implode(',', BidRules::ALLOW_MODES)],
            'provided_extensions' => ['nullable', 'string', 'max:500'],
            'method' => ['nullable', 'string', 'max:40'],
            'destination' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'account_no' => ['nullable', 'string', 'max:80'],
            'account_holder' => ['nullable', 'string', 'max:80'],
            'transfer_note' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'require_confirmed' => ['nullable'],
            'default_deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'menu' => ['nullable', 'array'],
            'notices' => ['nullable', 'array'],
            'general' => ['nullable', 'array'],
            'payment' => ['nullable', 'array'],
            'form' => ['nullable', 'array'],
        ];
        foreach (array_keys(self::pages()) as $page) {
            $rules[$page.'_enabled'] = ['nullable'];
            $rules[$page.'_body'] = ['nullable', 'string', 'max:'.self::NOTICE_MAX];
        }

        return $rules;
    }
}
