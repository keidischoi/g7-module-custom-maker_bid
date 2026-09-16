<?php

namespace Modules\Custom\MakerBid\Support;

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
                'sort_order' => 10,
            ],
            [
                'slug' => 'print_3d',
                'name' => '3D 출력 대행',
                'description' => '3D 출력 대행 의뢰',
                'requires_address' => true,
                'is_design_only' => false,
                'sort_order' => 20,
            ],
            [
                'slug' => 'full_package',
                'name' => '풀 패키지 제작 (모델링 + 출력 + 후가공)',
                'description' => '모델링, 출력, 후가공을 포함한 풀 패키지',
                'requires_address' => true,
                'is_design_only' => false,
                'sort_order' => 30,
            ],
            [
                'slug' => 'character_figure',
                'name' => '캐릭터·피규어 커미션',
                'description' => '캐릭터 및 피규어 커미션',
                'requires_address' => true,
                'is_design_only' => false,
                'sort_order' => 40,
            ],
            [
                'slug' => 'design_mockup',
                'name' => '디자인 목업(Mock-up) 및 시제품',
                'description' => '디자인 목업 및 시제품 (순수 디자인류)',
                'requires_address' => false,
                'is_design_only' => true,
                'sort_order' => 50,
            ],
            [
                'slug' => 'working_prototype',
                'name' => '워킹 프로토타입(기능성 시제품)',
                'description' => '기능성 워킹 프로토타입',
                'requires_address' => true,
                'is_design_only' => false,
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

    public static function isValidSlugFormat(string $slug): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{1,62}$/', $slug);
    }
}
