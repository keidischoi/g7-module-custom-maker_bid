<?php

namespace Modules\Custom\MakerBid\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Custom\MakerBid\Models\MakerJobType;
use Modules\Custom\MakerBid\Support\DomainException;
use Modules\Custom\MakerBid\Support\TypeCatalog;

class JobTypeService
{
    /**
     * @return Collection<int, MakerJobType>
     */
    public function listPublic(): Collection
    {
        return MakerJobType::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, MakerJobType>
     */
    public function listAdmin(): Collection
    {
        return MakerJobType::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function findBySlug(string $slug): ?MakerJobType
    {
        $raw = trim($slug);
        if ($raw !== '' && ctype_digit($raw)) {
            $byId = MakerJobType::query()->find((int) $raw);
            if ($byId) {
                return $byId;
            }
        }
        $slug = TypeCatalog::normalizeSlug($raw);

        return MakerJobType::query()->where('slug', $slug)->first();
    }

    public function requireEnabled(string $slug): MakerJobType
    {
        $row = $this->findBySlug($slug);
        if ($row === null || ! $row->is_enabled) {
            throw new DomainException('사용할 수 없는 의뢰 유형입니다.', 422);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): MakerJobType
    {
        $slug = (string) $payload['slug'];
        if (MakerJobType::query()->where('slug', $slug)->exists()) {
            throw new DomainException('이미 있는 유형 슬러그입니다.', 422);
        }

        $sort = isset($payload['sort_order']) ? (int) $payload['sort_order'] : ((int) MakerJobType::query()->max('sort_order') + 10);

        return MakerJobType::query()->create([
            'slug' => $slug,
            'name' => $payload['name'],
            'description' => $payload['description'] ?? null,
            'requires_address' => array_key_exists('requires_address', $payload)
                ? (bool) $payload['requires_address']
                : ! (bool) ($payload['is_design_only'] ?? false),
            'is_design_only' => (bool) ($payload['is_design_only'] ?? false),
            'includes_modeling' => array_key_exists('includes_modeling', $payload)
                ? (bool) $payload['includes_modeling']
                : TypeCatalog::includesModeling($payload, $slug),
            'is_enabled' => array_key_exists('is_enabled', $payload) ? (bool) $payload['is_enabled'] : true,
            'sort_order' => $sort,
            'is_seeded' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(int $id, array $payload): MakerJobType
    {
        $row = MakerJobType::query()->findOrFail($id);
        if (isset($payload['slug']) && $payload['slug'] !== $row->slug) {
            if (MakerJobType::query()->where('slug', $payload['slug'])->where('id', '!=', $row->id)->exists()) {
                throw new DomainException('이미 있는 유형 슬러그입니다.', 422);
            }
        }
        $row->fill($payload);
        if (array_key_exists('is_design_only', $payload) && ! array_key_exists('requires_address', $payload)) {
            $row->requires_address = ! (bool) $payload['is_design_only'];
        }
        $row->save();

        return $row->fresh() ?? $row;
    }

    public function destroy(int $id): void
    {
        $row = MakerJobType::query()->findOrFail($id);
        if ($row->jobs()->exists()) {
            throw new DomainException('이 유형을 쓰는 의뢰가 있어 삭제할 수 없습니다. 비활성화해 주세요.', 422);
        }
        $row->delete();
    }

    public function move(int $id, string $direction): MakerJobType
    {
        $row = MakerJobType::query()->findOrFail($id);
        $siblings = MakerJobType::query()->orderBy('sort_order')->orderBy('id')->get();
        $index = $siblings->search(static fn (MakerJobType $item): bool => (int) $item->id === (int) $row->id);
        if ($index === false) {
            return $row;
        }
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($siblings[$swapWith])) {
            return $row;
        }
        $other = $siblings[$swapWith];
        $currentSort = (int) $row->sort_order;
        $row->sort_order = (int) $other->sort_order;
        $other->sort_order = $currentSort;
        $row->save();
        $other->save();

        return $row->fresh() ?? $row;
    }

    /**
     * @return list<string>
     */
    public function enabledSlugs(): array
    {
        try {
            return MakerJobType::query()->where('is_enabled', true)->pluck('slug')->all();
        } catch (\Throwable) {
            return TypeCatalog::defaultSlugs();
        }
    }
}
