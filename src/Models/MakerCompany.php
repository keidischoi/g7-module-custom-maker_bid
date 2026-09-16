<?php

namespace Modules\Custom\MakerBids\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Custom\MakerBids\Support\CompanyPresenter;
use Modules\Custom\MakerBids\Support\CompanyRules;

class MakerCompany extends Model
{
    protected $table = 'maker_companies';

    protected $fillable = [
        'user_id', 'name', 'kind', 'type', 'business_no', 'job_types', 'logo_hash',
        'status', 'note', 'bio', 'homepage_url', 'portfolio_url',
        'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail',
        'admin_memo', 'hold_reason', 'rejected_reason', 'reviewed_at',
        'rating_score', 'rating_count', 'claim_count', 'claim_history', 'report_count',
        'is_recommended', 'is_designated', 'priority', 'upload_token',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'job_types' => 'array',
        'claim_history' => 'array',
        'reviewed_at' => 'datetime',
        'rating_score' => 'float',
        'rating_count' => 'integer',
        'claim_count' => 'integer',
        'report_count' => 'integer',
        'is_recommended' => 'boolean',
        'is_designated' => 'boolean',
        'priority' => 'integer',
    ];

    public function bids(): HasMany
    {
        return $this->hasMany(MakerBid::class, 'company_id');
    }

    public function isApproved(): bool
    {
        return CompanyRules::isApproved((string) $this->status);
    }

    public function logoUrl(): ?string
    {
        return CompanyPresenter::logoUrl($this->logo_hash);
    }
}
