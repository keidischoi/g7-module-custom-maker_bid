<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Custom\MakerBid\Support\CompanyRules;

class MakerCompany extends Model
{
    protected $table = 'maker_companies';

    protected $fillable = ['user_id', 'name', 'type', 'status', 'note', 'rejected_reason', 'reviewed_at'];

    protected $casts = [
        'user_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(MakerBid::class, 'company_id');
    }

    public function isApproved(): bool
    {
        return CompanyRules::isApproved((string) $this->status);
    }
}
