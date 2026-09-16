<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Custom\MakerBid\Support\JobRules;

class MakerJob extends Model
{
    protected $table = 'maker_jobs';

    protected $fillable = [
        'user_id', 'type_id', 'type', 'title', 'description', 'budget', 'budget_min', 'budget_max',
        'status', 'awarded_bid_id', 'closes_at', 'rush_fee_enabled', 'rush_deadline',
        'schedule_premium_enabled', 'size_w', 'size_d', 'size_h', 'provided_extensions',
        'revision_enabled', 'revision_count', 'revision_cost', 'contact_name', 'contact_phone',
        'contact_hours', 'contact_email', 'zipcode', 'address', 'address_detail', 'upload_token',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'type_id' => 'integer',
        'budget' => 'integer',
        'budget_min' => 'integer',
        'budget_max' => 'integer',
        'awarded_bid_id' => 'integer',
        'closes_at' => 'datetime',
        'rush_fee_enabled' => 'boolean',
        'rush_deadline' => 'datetime',
        'schedule_premium_enabled' => 'boolean',
        'size_w' => 'integer',
        'size_d' => 'integer',
        'size_h' => 'integer',
        'provided_extensions' => 'array',
        'revision_enabled' => 'boolean',
        'revision_count' => 'integer',
        'revision_cost' => 'integer',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(MakerBid::class, 'job_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MakerJobFile::class, 'job_id');
    }

    public function jobType(): BelongsTo
    {
        return $this->belongsTo(MakerJobType::class, 'type_id');
    }

    public function awardedBid(): BelongsTo
    {
        return $this->belongsTo(MakerBid::class, 'awarded_bid_id');
    }

    public function isOpen(): bool
    {
        return JobRules::isOpen((string) $this->status, $this->closes_at);
    }
}
