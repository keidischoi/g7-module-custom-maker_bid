<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MakerJobType extends Model
{
    protected $table = 'maker_job_types';

    protected $fillable = [
        'slug', 'name', 'description', 'requires_address', 'is_design_only',
        'is_enabled', 'sort_order', 'is_seeded',
    ];

    protected $casts = [
        'requires_address' => 'boolean',
        'is_design_only' => 'boolean',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'is_seeded' => 'boolean',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(MakerJob::class, 'type_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toOptionArray(): array
    {
        return [
            'id' => (int) $this->id,
            'value' => (string) $this->slug,
            'label' => (string) $this->name,
            'slug' => (string) $this->slug,
            'name' => (string) $this->name,
            'description' => $this->description,
            'requires_address' => (bool) $this->requires_address,
            'is_design_only' => (bool) $this->is_design_only,
            'is_enabled' => (bool) $this->is_enabled,
            'sort_order' => (int) $this->sort_order,
            'is_seeded' => (bool) $this->is_seeded,
        ];
    }
}
