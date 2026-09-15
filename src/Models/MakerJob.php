<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MakerJob extends Model
{
    protected $table = 'maker_jobs';

    protected $fillable = [
        'user_id', 'type', 'title', 'description', 'budget', 'status', 'awarded_bid_id', 'closes_at',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(MakerBid::class, 'job_id');
    }
}
