<?php

namespace Modules\Custom\MakerBids\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Custom\MakerBids\Support\BiddingRules;

class MakerJob extends Model
{
    protected $table = 'maker_jobs';

    protected $fillable = [
        'user_id', 'type_id', 'type', 'title', 'description', 'budget', 'budget_min', 'budget_max',
        'status', 'bidding_status', 'bidding_closed_at', 'awarded_bid_id', 'closes_at',
        'rush_fee_enabled', 'rush_deadline',
        'audience', 'schedule_premium_enabled', 'size_w', 'size_d', 'size_h', 'sizes',
        'provided_extensions', 'ownership_requested',
        'revision_enabled', 'revision_count', 'revision_cost', 'contact_name', 'contact_phone',
        'contact_hours', 'contact_email', 'zipcode', 'address', 'address_detail',
        'manager_name', 'manager_phone', 'manager_email', 'upload_token',
        'terms_agreed', 'work_status', 'tracking_no', 'carrier',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'type_id' => 'integer',
        'budget' => 'integer',
        'budget_min' => 'integer',
        'budget_max' => 'integer',
        'awarded_bid_id' => 'integer',
        'closes_at' => 'datetime',
        'bidding_closed_at' => 'datetime',
        'audience' => 'string',
        'rush_fee_enabled' => 'boolean',
        'rush_deadline' => 'datetime',
        'schedule_premium_enabled' => 'boolean',
        'size_w' => 'integer',
        'size_d' => 'integer',
        'size_h' => 'integer',
        'sizes' => 'array',
        'provided_extensions' => 'array',
        'ownership_requested' => 'boolean',
        'revision_enabled' => 'boolean',
        'revision_count' => 'integer',
        'revision_cost' => 'integer',
        'terms_agreed' => 'boolean',
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
        return BiddingRules::isOpen(
            $this->bidding_status ?? BiddingRules::OPEN,
            (string) $this->status,
            $this->closes_at,
        );
    }
}
