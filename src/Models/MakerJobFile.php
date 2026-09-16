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
        return UploadRules::isImageCollection((string) $this->collection)
            || str_starts_with((string) $this->mime_type, 'image/');
    }

    /**
     * Attachment-shaped payload for G7 FileUploader.
     *
     * G7 reads `response.data.data ?? response.data` after unwrapping the HTTP
     * body, so callers should wrap this with {@see toUploaderPayload()}.
     *
     * @return array<string, mixed>
     */
    public function toAttachmentArray(): array
    {
        $url = '/api/modules/custom-maker_bids/files/'.$this->hash;
        $isImage = $this->isImage();
        $size = (int) $this->size;

        return [
            'id' => (int) $this->id,
            'hash' => (string) $this->hash,
            'original_filename' => (string) $this->original_filename,
            'mime_type' => (string) ($this->mime_type ?: ($isImage ? 'image/png' : 'application/octet-stream')),
            'size' => $size,
            'size_formatted' => self::formatSize($size),
            'collection' => (string) $this->collection,
            'order' => (int) $this->sort_order,
            'download_url' => $url,
            'url' => $url,
            'thumbnail_url' => $isImage ? $url : '',
            'is_image' => $isImage,
            'meta' => [],
            'job_id' => $this->job_id,
        ];
    }

    /**
     * Dual-shaped body so FileUploader accepts both axios-style and
     * already-unwrapped G7Core.api.post results.
     *
     * @return array<string, mixed>
     */
    public function toUploaderPayload(): array
    {
        $att = $this->toAttachmentArray();

        return array_merge($att, [
            'success' => true,
            'data' => $att,
        ]);
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return number_format($bytes / (1024 * 1024), 2).' MB';
    }
}
