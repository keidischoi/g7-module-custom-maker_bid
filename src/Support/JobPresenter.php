<?php

namespace Modules\Custom\MakerBid\Support;

use Modules\Custom\MakerBid\Models\MakerJob;
use Modules\Custom\MakerBid\Models\MakerJobFile;

class JobPresenter
{
    /**
     * @param  iterable<int, MakerJobFile>|list<array<string, mixed>>  $files
     * @return array<string, mixed>
     */
    public static function present(
        MakerJob $job,
        bool $canViewPersonal,
        bool $canViewArchives,
        iterable $files = [],
        bool $includeBids = false,
    ): array {
        $type = $job->relationLoaded('jobType') ? $job->jobType : null;
        $typeRow = $type ? $type->toOptionArray() : null;

        $images = [];
        $archives = [];
        foreach ($files as $file) {
            $row = $file instanceof MakerJobFile ? $file->toAttachmentArray() : $file;
            $collection = (string) ($row['collection'] ?? '');
            if ($collection === UploadRules::COLLECTION_ARCHIVES) {
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
            'type' => (string) $job->type,
            'type_id' => $job->type_id !== null ? (int) $job->type_id : null,
            'type_name' => $typeRow['name'] ?? (string) $job->type,
            'type_requires_address' => TypeCatalog::requiresAddress($typeRow, (string) $job->type),
            'title' => (string) $job->title,
            'description' => $job->description,
            'budget' => $job->budget_max ?? $job->budget,
            'budget_min' => $job->budget_min,
            'budget_max' => $job->budget_max ?? $job->budget,
            'budget_label' => JobRules::budgetLabel($job->budget_min, $job->budget_max, $job->budget),
            'status' => (string) $job->status,
            'status_label' => JobRules::statusLabel((string) $job->status),
            'awarded_bid_id' => $job->awarded_bid_id !== null ? (int) $job->awarded_bid_id : null,
            'closes_at' => optional($job->closes_at)?->format('Y-m-d H:i:s') ?? $job->getRawOriginal('closes_at'),
            'rush_fee_enabled' => (bool) $job->rush_fee_enabled,
            'rush_deadline' => JobRules::datetimeLocal($job->rush_deadline) ?? JobRules::datetimeLocal($job->getRawOriginal('rush_deadline')),
            'rush_deadline_label' => JobRules::datetimeLabel($job->rush_deadline) ?? JobRules::datetimeLabel($job->getRawOriginal('rush_deadline')),
            'schedule_premium_enabled' => (bool) $job->schedule_premium_enabled,
            'size_w' => $job->size_w,
            'size_d' => $job->size_d,
            'size_h' => $job->size_h,
            'size_label' => JobRules::sizeLabel($job->size_w, $job->size_d, $job->size_h),
            'provided_extensions' => is_array($job->provided_extensions) ? $job->provided_extensions : [],
            'revision_enabled' => (bool) $job->revision_enabled,
            'revision_count' => $job->revision_count,
            'revision_cost' => $job->revision_cost,
            'bids_count' => (int) ($job->bids_count ?? 0),
            'images' => $images,
            'archives' => $archives,
            'privacy_visible' => $canViewPersonal,
            'created_at' => optional($job->created_at)?->format('Y-m-d H:i:s') ?? $job->getRawOriginal('created_at'),
            'updated_at' => optional($job->updated_at)?->format('Y-m-d H:i:s') ?? $job->getRawOriginal('updated_at'),
        ];

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
            $payload['bids'] = $job->relationLoaded('bids') ? $job->bids : [];
        }

        return $payload;
    }
}
