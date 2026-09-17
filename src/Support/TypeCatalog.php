<?php

namespace Modules\Custom\MakerBids\Support;

class TypeCatalog
{
    /**
     * Default commission types seeded into maker_job_types.
     *
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     requires_address: bool,
     *     is_design_only: bool,
     *     includes_modeling: bool,
     *     sort_order: int
     * }>
     */
    public static function defaults(): array
    {
        return [
            [
                'slug' => 'modeling_3d',
                'name' => '3D 모델링',
                'description' => '3D 모델링 의뢰',
                'requires_address' => false,
                'is_design_only' => true,
                'includes_modeling' => true,
                'sort_order' => 10,
            ],
            [
                'slug' => 'print_3d',
                'name' => '3D 출력 대행',
                'description' => '3D 출력 대행 의뢰',
                'requires_address' => true,
                'is_design_only' => false,
                'includes_modeling' => false,
                'sort_order' => 20,
            ],
            [
                'slug' => 'full_package',
                'name' => '풀 패키지 제작 (모델링 + 출력 + 후가공)',
                'description' => '모델링, 출력, 후가공을 포함한 풀 패키지',
                'requires_address' => true,
                'is_design_only' => false,
                'includes_modeling' => true,
                'sort_order' => 30,
            ],
            [
                'slug' => 'character_figure',
                'name' => '캐릭터·피규어 커미션',
                'description' => '캐릭터 및 피규어 커미션',
                'requires_address' => true,
                'is_design_only' => false,
                'includes_modeling' => true,
                'sort_order' => 40,
            ],
            [
                'slug' => 'design_mockup',
                'name' => '디자인 목업(Mock-up) 및 시제품',
                'description' => '디자인 목업 및 시제품 (순수 디자인류)',
                'requires_address' => false,
                'is_design_only' => true,
                'includes_modeling' => false,
                'sort_order' => 50,
            ],
            [
                'slug' => 'working_prototype',
                'name' => '워킹 프로토타입(기능성 시제품)',
                'description' => '기능성 워킹 프로토타입',
                'requires_address' => true,
                'is_design_only' => false,
                'includes_modeling' => true,
                'sort_order' => 60,
            ],
        ];
    }

    /**
     * Legacy hard-coded types → catalog slugs.
     *
     * @return array<string, string>
     */
    public static function legacyMap(): array
    {
        return [
            'print_3d' => 'print_3d',
            'design' => 'design_mockup',
            'manufacture' => 'full_package',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultSlugs(): array
    {
        return array_map(static fn (array $row): string => $row['slug'], self::defaults());
    }

    public static function isKnownSlug(string $slug): bool
    {
        return in_array($slug, self::defaultSlugs(), true) || isset(self::legacyMap()[$slug]);
    }

    public static function normalizeSlug(string $slug): string
    {
        return self::legacyMap()[$slug] ?? $slug;
    }

    /**
     * Coerce API/form type input (string|array|object junk) into a slug string.
     * Returns '' for missing / "[object Object]" / "Array".
     */
    public static function coerceTypeInput(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        if (is_array($raw)) {
            foreach (['value', 'slug', 'id', 'type', 'name', 'label'] as $k) {
                if (isset($raw[$k]) && ! is_array($raw[$k]) && ! is_object($raw[$k]) && (string) $raw[$k] !== '') {
                    return self::coerceTypeInput($raw[$k]);
                }
            }
            if (array_is_list($raw) && isset($raw[0])) {
                return self::coerceTypeInput($raw[0]);
            }

            return '';
        }
        if (is_object($raw)) {
            return self::coerceTypeInput((array) $raw);
        }
        if (is_bool($raw) || is_int($raw) || is_float($raw)) {
            return trim((string) $raw);
        }
        $s = trim((string) $raw);
        if ($s === '' || $s === '[object Object]' || $s === 'Array' || str_starts_with(strtolower($s), '[object ')) {
            return '';
        }

        return self::normalizeSlug($s);
    }

    /**
     * @param  array{is_design_only?: bool, requires_address?: bool, slug?: string}|null  $type
     */
    public static function requiresAddress(?array $type, ?string $slug = null): bool
    {
        if (is_array($type)) {
            if (array_key_exists('requires_address', $type)) {
                return (bool) $type['requires_address'];
            }
            if (array_key_exists('is_design_only', $type)) {
                return ! (bool) $type['is_design_only'];
            }
            $slug = (string) ($type['slug'] ?? $slug ?? '');
        }

        $slug = self::normalizeSlug((string) $slug);
        foreach (self::defaults() as $row) {
            if ($row['slug'] === $slug) {
                return $row['requires_address'];
            }
        }

        return true;
    }

    /**
     * Whether the type includes 3D modeling (so 제공 확장자 is shown).
     *
     * Prefers the DB `includes_modeling` flag. If missing, uses seeded slugs
     * then name/slug heuristics (모델링 / 풀 패키지 / 커미션 / 워킹 프로토타입).
     *
     * @param  array{includes_modeling?: mixed, is_design_only?: bool, requires_address?: bool, slug?: string, value?: string, name?: string, label?: string}|null  $type
     */
    public static function includesModeling(?array $type, ?string $slug = null): bool
    {
        if (is_array($type) && array_key_exists('includes_modeling', $type) && $type['includes_modeling'] !== null && $type['includes_modeling'] !== '') {
            return self::truthy($type['includes_modeling']);
        }

        $slug = self::normalizeSlug((string) ($type['slug'] ?? $type['value'] ?? $slug ?? ''));
        $name = (string) ($type['name'] ?? $type['label'] ?? '');
        foreach (self::defaults() as $row) {
            if ($row['slug'] === $slug) {
                return (bool) $row['includes_modeling'];
            }
        }

        $hay = $slug.' '.$name;
        if (preg_match('/print_3d|출력\s*대행|design_mockup|목업/u', $hay)) {
            return false;
        }

        return (bool) preg_match('/modeling|모델링|full_package|풀\s*패키지|character_figure|커미션|working_prototype|워킹\s*프로토타입/u', $hay);
    }

    private static function truthy(mixed $value): bool
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

        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function isValidSlugFormat(string $slug): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{1,62}$/', $slug);
    }
}
