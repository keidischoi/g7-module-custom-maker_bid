<?php

namespace Modules\Custom\MakerBids\Support;

use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Support\BidRules;

class CompanyPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(MakerCompany $row, string $audience = 'public'): array
    {
        $jobTypes = is_array($row->job_types) ? array_values($row->job_types) : CompanyRules::normalizeJobTypes($row->job_types);
        $payload = [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'name' => (string) $row->name,
            'kind' => CompanyRules::normalizeKind($row->kind ?? 'company'),
            'kind_label' => CompanyRules::kindLabel(CompanyRules::normalizeKind($row->kind ?? 'company')),
            'type' => $row->type,
            'job_types' => $jobTypes,
            'business_no' => $row->business_no,
            'logo_hash' => $row->logo_hash,
            'logo_url' => self::logoUrl($row->logo_hash),
            'bio' => $row->bio ?? $row->note,
            'homepage_url' => $row->homepage_url,
            'portfolio_url' => $row->portfolio_url,
            'status' => (string) $row->status,
            'status_label' => CompanyRules::statusLabel((string) $row->status),
            'rating_score' => CompanyRules::clampRatingScore($row->rating_score),
            'rating_count' => (int) ($row->rating_count ?? 0),
            'is_recommended' => (bool) $row->is_recommended,
            'is_designated' => CompanyRules::isDesignated($row),
            'priority' => CompanyRules::clampPriority($row->priority),
            'created_at' => optional($row->created_at)?->format('Y-m-d H:i:s') ?? $row->getRawOriginal('created_at'),
            'updated_at' => optional($row->updated_at)?->format('Y-m-d H:i:s') ?? $row->getRawOriginal('updated_at'),
        ];

        if ($audience === 'owner' || $audience === 'admin') {
            $payload['manager_name'] = $row->manager_name;
            $payload['phone'] = $row->phone;
            $payload['email'] = $row->email;
            $payload['zipcode'] = $row->zipcode;
            $payload['address'] = $row->address;
            $payload['address_detail'] = $row->address_detail;
            $payload['hold_reason'] = $row->hold_reason;
            $payload['rejected_reason'] = $row->rejected_reason;
            $payload['reviewed_at'] = optional($row->reviewed_at)?->format('Y-m-d H:i:s') ?? $row->getRawOriginal('reviewed_at');
            $payload['note'] = $row->note;
        }

        if ($audience === 'admin') {
            $payload['admin_memo'] = $row->admin_memo;
            $payload['claim_count'] = (int) ($row->claim_count ?? 0);
            $payload['claim_history'] = CompanyRules::normalizeClaimHistory($row->claim_history);
            $payload['report_count'] = (int) ($row->report_count ?? 0);
            $payload['upload_token'] = $row->upload_token;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentBid(MakerBid $bid): array
    {
        $company = $bid->relationLoaded('company') ? $bid->company : null;
        $payload = [
            'id' => (int) $bid->id,
            'job_id' => (int) $bid->job_id,
            'user_id' => (int) $bid->user_id,
            'company_id' => $bid->company_id !== null ? (int) $bid->company_id : null,
            'amount' => (int) $bid->amount,
            'amount_label' => number_format((int) $bid->amount).'원',
            'days' => $bid->days,
            'message' => $bid->message,
            'status' => (string) $bid->status,
            'status_label' => BidRules::statusLabel((string) $bid->status),
            'company_name' => $company?->name,
            'is_recommended' => CompanyRules::listingRecommended($company),
            'company_priority' => CompanyRules::listingPriority($company),
            'company_rating_score' => $company ? CompanyRules::clampRatingScore($company->rating_score) : null,
            'company_rating_count' => $company ? (int) ($company->rating_count ?? 0) : null,
            'company' => $company ? self::present($company, 'public') : null,
            'created_at' => optional($bid->created_at)?->format('Y-m-d H:i:s') ?? $bid->getRawOriginal('created_at'),
        ];

        return $payload;
    }

    public static function logoUrl(?string $hash): ?string
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        return '/api/modules/custom-maker_bids/files/'.$hash;
    }
}
