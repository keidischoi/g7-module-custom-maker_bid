<?php

namespace Modules\Custom\MakerBids\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Custom\MakerBids\Support\UploadRules;

class MakerJobFile extends Model
{
    protected $table = 'maker_job_files';

    protected $fillable = [
        'job_id', 'user_id', 'upload_token', 'collection', 'disk', 'path',
        'original_filename', 'mime_type', 'size', 'hash', 'sort_order',
        'expires_at', 'purged_at',
    ];

    protected $casts = [
        'job_id' => 'integer',
        'user_id' => 'integer',
        'size' => 'integer',
        'sort_order' => 'integer',
        'expires_at' => 'datetime',
        'purged_at' => 'datetime',
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

    public function isExpired(): bool
    {
        $purged = $this->attributes['purged_at'] ?? null;
        if ($purged) {
            return true;
        }
        $expires = $this->attributes['expires_at'] ?? null;
        if (! $expires) {
            return false;
        }
        if (is_object($expires) && method_exists($expires, 'isPast')) {
            return $expires->isPast();
        }

        return strtotime((string) $expires) < time();
    }

    public function isPurged(): bool
    {
        $purged = $this->attributes['purged_at'] ?? null;
        $path = (string) ($this->attributes['path'] ?? $this->path ?? '');

        return $purged !== null || $path === '';
    }

    /**
     * Attachment-shaped payload for G7 FileUploader.
     *
     * @return array<string, mixed>
     */
    public function toAttachmentArray(): array
    {
        $expired = $this->isExpired();
        $url = $expired ? '' : '/api/modules/custom-maker_bids/files/'.$this->hash;
        $isImage = $this->isImage();
        $size = (int) $this->size;
        $expiresRaw = $this->attributes['expires_at'] ?? null;
        if (is_object($expiresRaw) && method_exists($expiresRaw, 'format')) {
            $expiresStr = $expiresRaw->format('Y-m-d H:i:s');
        } elseif ($expiresRaw) {
            $expiresStr = (string) $expiresRaw;
        } else {
            $expiresStr = null;
        }

        $name = (string) $this->original_filename;
        $base = UploadRules::toUploaderFile([
            'id' => (int) $this->id,
            'hash' => (string) $this->hash,
            'original_filename' => $name,
            'mime_type' => (string) ($this->mime_type ?: ($isImage ? 'image/png' : 'application/octet-stream')),
            'size' => $size,
            'collection' => (string) $this->collection,
            'order' => (int) $this->sort_order,
            'download_url' => $url,
            'url' => $url,
            'thumbnail_url' => ($isImage && ! $expired) ? $url : '',
            'is_image' => $isImage,
        ]);

        return array_merge($base, [
            'size_formatted' => self::formatSize($size),
            'is_expired' => $expired,
            'is_purged' => $this->isPurged(),
            'expires_at' => $expiresStr,
            'meta' => [],
            'job_id' => $this->job_id,
        ]);
    }

    /**
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
