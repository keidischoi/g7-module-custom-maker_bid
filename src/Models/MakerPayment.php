<?php

namespace Modules\Custom\MakerBids\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Custom\MakerBids\Support\PaymentRules;

class MakerPayment extends Model
{
    protected $table = 'maker_payments';

    protected $fillable = [
        'job_id', 'bid_id', 'payer_user_id', 'payee_user_id',
        'amount', 'method', 'status', 'destination',
        'bank_name', 'account_no', 'account_holder', 'transfer_note', 'instructions',
        'kind', 'deposit_percent', 'deposit_terms',
        'depositor_name', 'memo',
        'reported_at', 'confirmed_at', 'confirmed_by',
        'refunded_at', 'refund_note',
    ];

    protected $casts = [
        'job_id' => 'integer',
        'bid_id' => 'integer',
        'payer_user_id' => 'integer',
        'payee_user_id' => 'integer',
        'amount' => 'integer',
        'deposit_percent' => 'integer',
        'confirmed_by' => 'integer',
        'reported_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(MakerJob::class, 'job_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(MakerBid::class, 'bid_id');
    }

    public function isConfirmed(): bool
    {
        return (string) $this->status === PaymentRules::STATUS_CONFIRMED;
    }
}
