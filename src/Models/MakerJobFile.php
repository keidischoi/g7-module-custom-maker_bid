<?php

namespace Modules\Custom\MakerBid\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Custom\MakerBid\Support\UploadRules;

class MakerJobFile extends Model
{
    protected $table = 'maker_job_files';

    protected $fillable = [
        'job_id', 'user_id', 'upload_token', 'collection', 'disk', 'path',
        'original_filename', 'mime_type', 'size', 'hash', 'sort_order',
    ];

    protected $casts = [
        'job_id' => 'integer',
        'user_id' => 'integer',
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(MakerJob::class, 'job_id');
    }

    public function isImage(): bool
    {
        return $this->collection === UploadRules::COLLECTION_IMAGES
            || str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Attachment-shaped payload for G7 FileUploader.
     *
     * @return array<string, mixed>
     */
    public function toAttachmentArray(): array
    {
        $url = '/api/modules/custom-maker_bid/files/'.$this->hash;

        return [
            'id' => (int) $this->id,
            'hash' => (string) $this->hash,
            'original_filename' => (string) $this->original_filename,
            'mime_type' => (string) ($this->mime_type ?: ''),
            'size' => (int) $this->size,
            'size_formatted' => '',
            'collection' => (string) $this->collection,
            'order' => (int) $this->sort_order,
            'download_url' => $url,
            'url' => $url,
            'thumbnail_url' => $this->isImage() ? $url : null,
            'is_image' => $this->isImage(),
            'job_id' => $this->job_id,
        ];
    }
}
