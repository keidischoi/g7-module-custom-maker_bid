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
        'user_id', 'type', 'title', 'description', 'budget', 'status', 'awarded_bid_id', 'closes_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'budget' => 'integer',
        'awarded_bid_id' => 'integer',
        'closes_at' => 'datetime',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(MakerBid::class, 'job_id');
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
