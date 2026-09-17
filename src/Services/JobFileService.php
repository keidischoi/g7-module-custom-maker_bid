<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Models\MakerJobFile;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\UploadRules;

class JobFileService
{
    public function newUploadToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function store(
        int $userId,
        UploadedFile $file,
        string $collection,
        ?string $token = null,
        ?int $jobId = null,
        bool $asAdmin = false,
    ): MakerJobFile {
        $collection = $this->normalizeCollection($collection);

        $name = (string) $file->getClientOriginalName();
        if (! UploadRules::isAllowedExtension($collection, $name)) {
            $hint = UploadRules::isProtectedCollection($collection)
                ? '허용되지 않는 납품/첨부 확장자입니다.'
                : '이미지 파일만 올릴 수 있습니다.';
            throw new DomainException($hint, 422);
        }
        if ($collection === UploadRules::COLLECTION_LOGOS) {
            $this->assertLogoDimensions($file);
        }

        $job = $this->resolveJob($userId, $token, $jobId, $collection, $asAdmin);
        if ($collection === UploadRules::COLLECTION_LOGOS) {
            $this->replaceExistingLogos($userId, $token, $job?->id);
        }
        $dir = $job
            ? 'custom-maker-bids/jobs/'.$job->id.'/'.$collection
            : 'custom-maker-bids/staging/'.($token ?: ('u'.$userId));

        $stored = $file->store($dir, 'public');
        if (! is_string($stored) || $stored === '') {
            throw new DomainException('파일 저장에 실패했습니다.', 500);
        }

        $sort = (int) MakerJobFile::query()
            ->when($job, fn ($q) => $q->where('job_id', $job->id))
            ->when(! $job && $token, fn ($q) => $q->where('upload_token', $token)->where('user_id', $userId))
            ->where('collection', $collection)
            ->max('sort_order');

        $attrs = [
            'job_id' => $job?->id,
            'user_id' => $userId,
            'upload_token' => $token,
            'collection' => $collection,
            'disk' => 'public',
            'path' => $stored,
            'original_filename' => $name,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'hash' => $this->uniqueHash(),
            'sort_order' => $sort + 1,
        ];

        if ($this->shouldExpire($collection, $job) && Schema::hasColumn('maker_job_files', 'expires_at')) {
            $attrs['expires_at'] = UploadRules::deliveryExpiresAt();
        }

        return MakerJobFile::query()->create($attrs);
    }

    public function claimToken(int $userId, string $token, int $jobId): void
    {
        MakerJobFile::query()
            ->where('user_id', $userId)
            ->where('upload_token', $token)
            ->whereNull('job_id')
            ->update(['job_id' => $jobId]);
    }

    public function destroyOwned(int $userId, string $hash): void
    {
        $row = MakerJobFile::query()->where('hash', $hash)->firstOrFail();
        if ((int) $row->user_id !== $userId) {
            throw new DomainException('본인이 올린 파일만 삭제할 수 있습니다.', 403);
        }
        $this->deleteRow($row);
    }

    public function destroyAdmin(string $hash): void
    {
        $row = MakerJobFile::query()->where('hash', $hash)->firstOrFail();
        $this->deleteRow($row);
    }

    public function findByHash(string $hash): MakerJobFile
    {
        return MakerJobFile::query()->with('job')->where('hash', $hash)->firstOrFail();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MakerJobFile>
     */
    public function forJob(int $jobId, ?string $collection = null, bool $includeExpired = false)
    {
        $q = MakerJobFile::query()->where('job_id', $jobId)->orderBy('sort_order')->orderBy('id');
        if ($collection) {
            $q->where('collection', $collection);
        }
        if (! $includeExpired && Schema::hasColumn('maker_job_files', 'expires_at')) {
            $q->where(function ($qq) {
                $qq->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->whereNull('purged_at');
        }

        return $q->get();
    }

    public function absolutePath(MakerJobFile $file): ?string
    {
        if ($file->isPurged() || (string) $file->path === '') {
            return null;
        }
        try {
            $path = Storage::disk($file->disk ?: 'public')->path($file->path);
        } catch (\Throwable) {
            return null;
        }

        return is_string($path) && is_file($path) ? $path : null;
    }

    /** Delete storage for expired delivery files; keep DB rows + download logs. */
    public function purgeExpired(): int
    {
        if (! Schema::hasTable('maker_job_files') || ! Schema::hasColumn('maker_job_files', 'expires_at')) {
            return 0;
        }
        $rows = MakerJobFile::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('purged_at')
            ->where('path', '!=', '')
            ->limit(200)
            ->get();
        $count = 0;
        foreach ($rows as $row) {
            try {
                Storage::disk($row->disk ?: 'public')->delete($row->path);
            } catch (\Throwable) {
            }
            $row->path = '';
            if (Schema::hasColumn('maker_job_files', 'purged_at')) {
                $row->purged_at = now();
            }
            $row->save();
            $count++;
        }

        return $count;
    }

    private function normalizeCollection(string $collection): string
    {
        if ($collection === UploadRules::COLLECTION_ARCHIVES) {
            return UploadRules::COLLECTION_ARCHIVES;
        }
        if ($collection === UploadRules::COLLECTION_DELIVERY || $collection === 'deliveries') {
            return UploadRules::COLLECTION_DELIVERY;
        }
        if ($collection === UploadRules::COLLECTION_LOGOS) {
            return UploadRules::COLLECTION_LOGOS;
        }

        return UploadRules::COLLECTION_IMAGES;
    }

    private function shouldExpire(string $collection, ?MakerJob $job): bool
    {
        if ($collection === UploadRules::COLLECTION_DELIVERY) {
            return true;
        }
        // Archives uploaded after award (workspace delivery via archives) also expire.
        return $collection === UploadRules::COLLECTION_ARCHIVES
            && $job
            && in_array((string) $job->status, ['awarded', 'done'], true);
    }

    private function resolveJob(int $userId, ?string $token, ?int $jobId, string $collection = '', bool $asAdmin = false): ?MakerJob
    {
        if ($jobId) {
            $job = MakerJob::query()->find($jobId);
            if (! $job) {
                return null;
            }
            if ($asAdmin || (int) $job->user_id === $userId) {
                return $job;
            }
            if (UploadRules::isProtectedCollection($collection)
                && in_array((string) $job->status, ['awarded', 'done'], true)
                && $this->isAwardedUser($job, $userId)) {
                return $job;
            }

            return null;
        }
        if ($token) {
            return MakerJob::query()
                ->where('user_id', $userId)
                ->where('upload_token', $token)
                ->first();
        }

        return null;
    }

    private function isAwardedUser(MakerJob $job, int $userId): bool
    {
        if (! $job->awarded_bid_id) {
            return false;
        }
        $bid = $job->relationLoaded('awardedBid') && $job->awardedBid
            ? $job->awardedBid
            : MakerBid::query()->find($job->awarded_bid_id);

        return $bid && (int) $bid->user_id === $userId;
    }

    private function uniqueHash(): string
    {
        do {
            $hash = substr(bin2hex(random_bytes(12)), 0, 16);
        } while (MakerJobFile::query()->where('hash', $hash)->exists());

        return $hash;
    }

    private function assertLogoDimensions(UploadedFile $file): void
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $info = is_string($path) && $path !== '' ? @getimagesize($path) : false;
        if (! is_array($info) || ! isset($info[0], $info[1])) {
            throw new DomainException('로고 이미지를 읽을 수 없습니다.', 422);
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if (! UploadRules::isWithinLogoDimensions($width, $height)) {
            throw new DomainException('로고는 최대 '.UploadRules::LOGO_MAX_PX.'×'.UploadRules::LOGO_MAX_PX.' 픽셀입니다.', 422);
        }
    }

    private function replaceExistingLogos(int $userId, ?string $token, ?int $jobId): void
    {
        $q = MakerJobFile::query()
            ->where('user_id', $userId)
            ->where('collection', UploadRules::COLLECTION_LOGOS);
        if ($jobId) {
            $q->where('job_id', $jobId);
        } elseif ($token) {
            $q->where('upload_token', $token)->whereNull('job_id');
        } else {
            return;
        }
        foreach ($q->get() as $row) {
            $this->deleteRow($row);
        }
    }

    private function deleteRow(MakerJobFile $row): void
    {
        try {
            if ((string) $row->path !== '') {
                Storage::disk($row->disk ?: 'public')->delete($row->path);
            }
        } catch (\Throwable) {
        }
        $row->delete();
    }
}
