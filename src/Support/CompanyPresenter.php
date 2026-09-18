<?php

namespace Modules\Custom\MakerBids\Support;

use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJobFile;

class CompanyPresenter
{
    public static function present(MakerCompany $row, string $audience = 'public'): array
    {
        $jobTypes = is_array($row->job_types) ? array_values($row->job_types) : CompanyRules::normalizeJobTypes($row->job_types);
        $logos = self::logoFilesFor($row);
        $thumb = null;
        if ($logos) {
            $first = $logos[0];
            $thumb = $first['thumbnail_url'] ?? $first['url'] ?? $first['download_url'] ?? null;
        }
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
            'logo_url' => $thumb ?: self::logoUrl($row->logo_hash),
            'thumbnail_url' => $thumb,
            'logo_files' => $logos,
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
            'deposit_percent' => $row->deposit_percent !== null
                ? PaymentRules::normalizeDepositPercent($row->deposit_percent)
                : PaymentRules::DEFAULT_DEPOSIT_PERCENT,
            'deposit_terms' => (string) ($row->deposit_terms ?? ''),
            'deposit_label' => PaymentRules::normalizeDepositPercent($row->deposit_percent ?? PaymentRules::DEFAULT_DEPOSIT_PERCENT).'%',
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
            $payload['upload_token'] = $row->upload_token;
            $payload['bank_name'] = (string) ($row->bank_name ?? '');
            $payload['account_no'] = (string) ($row->account_no ?? '');
            $payload['account_holder'] = (string) ($row->account_holder ?? '');
            $payload['account_label'] = trim((string) ($row->bank_name ?? '').' '.(string) ($row->account_no ?? '').' '.(string) ($row->account_holder ?? ''));
        }

        if ($audience === 'admin') {
            $payload['admin_memo'] = $row->admin_memo;
            $payload['claim_count'] = (int) ($row->claim_count ?? 0);
            $payload['claim_history'] = CompanyRules::normalizeClaimHistory($row->claim_history);
            $payload['report_count'] = (int) ($row->report_count ?? 0);
        }

        return $payload;
    }

    public static function presentBid(MakerBid $bid): array
    {
        $company = $bid->relationLoaded('company') ? $bid->company : null;
        return [
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
            'deposit_percent' => $company && $company->deposit_percent !== null
                ? PaymentRules::normalizeDepositPercent($company->deposit_percent)
                : PaymentRules::DEFAULT_DEPOSIT_PERCENT,
            'deposit_terms' => $company ? (string) ($company->deposit_terms ?? '') : '',
            'deposit_label' => $company
                ? (PaymentRules::normalizeDepositPercent($company->deposit_percent ?? PaymentRules::DEFAULT_DEPOSIT_PERCENT).'%')
                : (PaymentRules::DEFAULT_DEPOSIT_PERCENT.'%'),
            'company' => $company ? self::present($company, 'public') : null,
            'created_at' => optional($bid->created_at)?->format('Y-m-d H:i:s') ?? $bid->getRawOriginal('created_at'),
        ];
    }

    public static function logoUrl(?string $hash): ?string
    {
        if ($hash === null || $hash === '') {
            return null;
        }
        return '/api/modules/custom-maker_bids/files/'.$hash;
    }

    public static function logoFiles(?string $hash): array
    {
        return self::logoFilesForHash($hash);
    }

    public static function logoFilesFor($row): array
    {
        $hash = is_object($row) ? (string) ($row->logo_hash ?? '') : '';
        $files = self::logoFilesForHash($hash);
        if ($files) {
            return $files;
        }
        $userId = is_object($row) ? (int) ($row->user_id ?? 0) : 0;
        if ($userId < 1) {
            return [];
        }
        $found = MakerJobFile::query()
            ->where('user_id', $userId)
            ->where('collection', UploadRules::COLLECTION_LOGOS)
            ->orderByDesc('id')
            ->get();
        if ($found->isEmpty()) {
            return [];
        }
        return $found->map(static fn ($f) => $f->toAttachmentArray())->values()->all();
    }

    public static function logoFilesForHash(?string $hash): array
    {
        $url = self::logoUrl($hash);
        if ($url === null || $hash === null || $hash === '') {
            return [];
        }
        $row = MakerJobFile::query()->where('hash', $hash)->first();
        if ($row) {
            return [$row->toAttachmentArray()];
        }
        return [];
    }
}
