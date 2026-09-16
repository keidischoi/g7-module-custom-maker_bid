<?php

namespace Modules\Custom\MakerBids\Support;

class SettingsRules
{
    public const MODULE_ID = 'custom-maker_bids';

    public const CATEGORIES = ['menu', 'notices', 'general'];

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
            'menu' => ['nullable', 'array'],
            'notices' => ['nullable', 'array'],
            'general' => ['nullable', 'array'],
            'form' => ['nullable', 'array'],
        ];
        foreach (array_keys(self::pages()) as $page) {
            $rules[$page.'_enabled'] = ['nullable'];
            $rules[$page.'_body'] = ['nullable', 'string', 'max:'.self::NOTICE_MAX];
        }

        return $rules;
    }
}
