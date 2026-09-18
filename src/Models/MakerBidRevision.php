<?php

namespace Modules\Custom\MakerBids\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MakerBidRevision extends Model
{
    protected $table = 'maker_bid_revisions';

    protected $fillable = [
        'bid_id', 'job_id', 'user_id', 'event', 'amount', 'days', 'message', 'status',
    ];

    protected $casts = [
        'bid_id' => 'integer',
        'job_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'integer',
        'days' => 'integer',
    ];

    public function bid(): BelongsTo
    {
        return $this->belongsTo(MakerBid::class, 'bid_id');
    }
}
