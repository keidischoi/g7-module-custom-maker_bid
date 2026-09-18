<?php

namespace Modules\Custom\MakerBids\Support;

use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Models\MakerJobFile;

class JobPresenter
{
    public static function statusLabel(MakerJob $job): string
    {
        $status = (string) $job->status;
        if (in_array($status, ['quote_request', 'open'], true)) {
            return '입찰중';
        }

        return JobRules::statusLabel($status);
    }

    public static function biddingLabel(MakerJob $job): string
    {
        $status = (string) $job->status;
        if (in_array($status, ['cancelled', 'done', 'hold', 'draft', 'pending', 'disputed'], true)) {
            return '종료';
        }
        $bidding = BiddingRules::normalize($job->bidding_status ?? BiddingRules::OPEN);

        return $bidding === BiddingRules::CLOSED ? '종료' : '입찰중';
    }

    public static function present(
        MakerJob $job,
        bool $canViewPersonal,
        bool $canViewArchives,
        iterable $files = [],
        bool $includeBids = false,
    ): array {
        $type = $job->relationLoaded('jobType') ? $job->jobType : null;
        $typeRow = $type ? $type->toOptionArray() : null;
        $bidding = BiddingRules::normalize($job->bidding_status ?? BiddingRules::OPEN);
        $statusLabel = self::statusLabel($job);
        $biddingLabel = self::biddingLabel($job);

        $images = [];
        $archives = [];
        $deliveries = [];
        foreach ($files as $file) {
            if ($file instanceof MakerJobFile && $file->isExpired()) {
                continue;
            }
            $row = $file instanceof MakerJobFile ? $file->toAttachmentArray() : (is_array($file) ? UploadRules::toUploaderFile($file) : $file);
            if (! is_array($row) || ! empty($row['is_expired'])) {
                continue;
            }
            $collection = (string) ($row['collection'] ?? '');
            if ($collection === 'image') {
                $collection = UploadRules::COLLECTION_IMAGES;
                $row['collection'] = $collection;
            }
            if ($collection === 'archive' || $collection === 'file' || $collection === 'files') {
                $collection = UploadRules::COLLECTION_ARCHIVES;
                $row['collection'] = $collection;
            }
            if ($collection === UploadRules::COLLECTION_DELIVERY) {
                if ($canViewArchives) {
                    $deliveries[] = $row;
                    $archives[] = $row;
                }
            } elseif ($collection === UploadRules::COLLECTION_ARCHIVES) {
                if ($canViewArchives) {
                    $archives[] = $row;
                }
            } else {
                $images[] = $row;
            }
        }

        $payload = [
            'id' => (int) $job->id,
            'user_id' => $job->user_id !== null ? (int) $job->user_id : null,
            'owner_name' => isset($job->owner_name) ? (string) $job->owner_name : null,
            'owner_login' => isset($job->owner_login) ? (string) $job->owner_login : null,
            'owner_company_name' => isset($job->owner_company_name) ? (string) $job->owner_company_name : null,
            'type' => (string) $job->type,
            'type_id' => $job->type_id !== null ? (int) $job->type_id : null,
            'type_name' => $typeRow['name'] ?? (string) $job->type,
            'type_requires_address' => TypeCatalog::requiresAddress($typeRow, (string) $job->type),
            'type_includes_modeling' => TypeCatalog::includesModeling($typeRow, (string) $job->type),
            'title' => (string) $job->title,
            'description' => $job->description,
            'budget' => $job->budget_max ?? $job->budget,
            'budget_min' => $job->budget_min,
            'budget_max' => $job->budget_max ?? $job->budget,
            'budget_label' => JobRules::budgetLabel($job->budget_min, $job->budget_max, $job->budget),
            'status' => (string) $job->status,
            'status_label' => $statusLabel,
            'bidding_status' => $bidding,
            'bidding_status_label' => $biddingLabel,
            'bidding_closed_at' => optional($job->bidding_closed_at)?->format('Y-m-d H:i:s'),
            'audience' => JobRules::normalizeAudience($job->audience ?? 'all'),
            'audience_label' => JobRules::audienceLabel($job->audience ?? 'all'),
            'awarded_bid_id' => $job->awarded_bid_id !== null ? (int) $job->awarded_bid_id : null,
            'awarded_company_id' => self::awardedCompanyId($job),
            'awarded_company_name' => self::awardedCompanyName($job),
            'is_open' => $job->isOpen(),
            'work_status' => $job->work_status,
            'tracking_no' => $job->tracking_no,
            'carrier' => $job->carrier,
            'closes_at' => optional($job->closes_at)?->format('Y-m-d H:i:s') ?? $job->getRawOriginal('closes_at'),
            'closes_at_local' => JobRules::datetimeLocal($job->closes_at) ?? JobRules::datetimeLocal($job->getRawOriginal('closes_at')),
            'rush_fee_enabled' => (bool) $job->rush_fee_enabled,
            'rush_deadline' => JobRules::datetimeLocal($job->rush_deadline) ?? JobRules::datetimeLocal($job->getRawOriginal('rush_deadline')),
            'rush_deadline_label' => JobRules::datetimeLabel($job->rush_deadline) ?? JobRules::datetimeLabel($job->getRawOriginal('rush_deadline')),
            'schedule_premium_enabled' => (bool) $job->schedule_premium_enabled,
            'size_w' => $job->size_w,
            'size_d' => $job->size_d,
            'size_h' => $job->size_h,
            'sizes' => JobRules::normalizeSizes([
                'sizes' => $job->sizes,
                'size_w' => $job->size_w,
                'size_d' => $job->size_d,
                'size_h' => $job->size_h,
            ]),
            'sizes_json' => JobRules::sizesJson(JobRules::normalizeSizes([
                'sizes' => $job->sizes,
                'size_w' => $job->size_w,
                'size_d' => $job->size_d,
                'size_h' => $job->size_h,
            ])),
            'size_label' => JobRules::sizesLabel($job->sizes, $job->size_w, $job->size_d, $job->size_h),
            'provided_extensions' => is_array($job->provided_extensions) ? $job->provided_extensions : [],
            'ext_stl' => in_array('STL', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_3mf' => in_array('3MF', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_obj' => in_array('OBJ', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_step' => in_array('STEP', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_stp' => in_array('STP', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_gcode' => in_array('GCODE', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_fbx' => in_array('FBX', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_dwg' => in_array('DWG', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ext_pdf' => in_array('PDF', is_array($job->provided_extensions) ? $job->provided_extensions : [], true),
            'ownership_requested' => (bool) $job->ownership_requested,
            'revision_enabled' => (bool) $job->revision_enabled,
            'revision_count' => $job->revision_count,
            'revision_cost' => $job->revision_cost,
            'contact_hours_from' => self::contactHoursPart($job->contact_hours, 0),
            'contact_hours_to' => self::contactHoursPart($job->contact_hours, 1),
            'bids_count' => (int) ($job->bids_count ?? 0),
            'view_count' => (int) ($job->view_count ?? 0),
            'upload_token' => (string) ($job->upload_token ?? ''),
            'images' => $images,
            'archives' => $archives,
            'deliveries' => $deliveries,
            'privacy_visible' => $canViewPersonal,
            'created_at' => optional($job->created_at)?->format('Y-m-d H:i:s') ?? $job->getRawOriginal('created_at'),
            'updated_at' => optional($job->updated_at)?->format('Y-m-d H:i:s') ?? $job->getRawOriginal('updated_at'),
        ];
        $extList = is_array($job->provided_extensions) ? $job->provided_extensions : [];
        foreach ($extList as $extToken) {
            $extToken = strtoupper(ltrim((string) $extToken, '.'));
            if ($extToken === '') {
                continue;
            }
            $payload['ext_'.strtolower($extToken)] = true;
        }

        if ($canViewPersonal) {
            foreach (PrivacyRules::personalKeys() as $key) {
                $payload[$key] = $job->{$key};
            }
        } else {
            foreach (PrivacyRules::personalKeys() as $key) {
                $payload[$key] = null;
            }
            $payload['privacy_blocked'] = true;
        }

        if ($includeBids) {
            $bids = $job->relationLoaded('bids') ? $job->bids : collect();
            $sorted = $bids->sort(static function ($a, $b): int {
                $cmp = CompanyRules::compareListing($a->company ?? null, $b->company ?? null);

                return $cmp !== 0 ? $cmp : ((int) $b->id <=> (int) $a->id);
            })->values();
            $payload['bids'] = $sorted->map(static fn ($bid) => CompanyPresenter::presentBid($bid))->all();
        }

        return $payload;
    }

    public static function envelope(array $payload): array
    {
        return array_merge($payload, [
            'success' => true,
            'data' => $payload,
        ]);
    }

    private static function awardedBid(MakerJob $job): mixed
    {
        if ($job->awarded_bid_id === null) {
            return null;
        }
        if ($job->relationLoaded('awardedBid') && $job->awardedBid) {
            return $job->awardedBid;
        }
        if ($job->relationLoaded('bids')) {
            foreach ($job->bids as $bid) {
                if ((int) $bid->id === (int) $job->awarded_bid_id) {
                    return $bid;
                }
            }
        }

        return null;
    }

    public static function awardedCompanyId(MakerJob $job): ?int
    {
        $bid = self::awardedBid($job);
        if ($bid === null || $bid->company_id === null) {
            return null;
        }

        return (int) $bid->company_id;
    }

    public static function awardedCompanyName(MakerJob $job): ?string
    {
        $bid = self::awardedBid($job);
        if ($bid === null) {
            return null;
        }
        if ($bid->relationLoaded('company') && $bid->company) {
            return (string) $bid->company->name;
        }

        return null;
    }

    private static function contactHoursPart(mixed $hours, int $index): ?string
    {
        $raw = trim((string) ($hours ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/(\d{1,2}:\d{2})\s*[~\-\x{2013}\x{2014}]\s*(\d{1,2}:\d{2})/u', $raw, $m)) {
            return $index === 0 ? $m[1] : $m[2];
        }
        if (preg_match_all('/\d{1,2}:\d{2}/', $raw, $all) && isset($all[0][$index])) {
            return $all[0][$index];
        }

        return null;
    }
}
