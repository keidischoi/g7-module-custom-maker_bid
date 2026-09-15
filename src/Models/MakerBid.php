<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MakerBid extends Model
{
    protected $table = 'maker_bids';

    protected $fillable = ['job_id', 'user_id', 'amount', 'days', 'message', 'status'];

    public function job(): BelongsTo
    {
        return $this->belongsTo(MakerJob::class, 'job_id');
    }
}
