<?php

namespace Modules\Custom\MakerBids\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MakerBid extends Model
{
    protected $table = 'maker_bids';

    protected $fillable = ['job_id', 'user_id', 'company_id', 'amount', 'days', 'message', 'status'];

    protected $casts = [
        'job_id' => 'integer',
        'user_id' => 'integer',
        'company_id' => 'integer',
        'amount' => 'integer',
        'days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('newest_first', function (Builder $q) {
            $q->orderByDesc('id');
        });
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(MakerJob::class, 'job_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(MakerCompany::class, 'company_id');
    }
}
