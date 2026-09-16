<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    ): MakerJobFile {
        if ($collection === UploadRules::COLLECTION_ARCHIVES) {
            $collection = UploadRules::COLLECTION_ARCHIVES;
        } elseif ($collection === UploadRules::COLLECTION_LOGOS) {
            $collection = UploadRules::COLLECTION_LOGOS;
        } else {
            $collection = UploadRules::COLLECTION_IMAGES;
        }

        $name = (string) $file->getClientOriginalName();
        if (! UploadRules::isAllowedExtension($collection, $name)) {
            $hint = $collection === UploadRules::COLLECTION_ARCHIVES
                ? '압축 파일만 올릴 수 있습니다. (zip/tar/gz 등)'
                : '이미지 파일만 올릴 수 있습니다.';
            throw new DomainException($hint, 422);
        }
        if ($collection === UploadRules::COLLECTION_LOGOS) {
            $this->assertLogoDimensions($file);
        }

        $job = $this->resolveJob($userId, $token, $jobId);
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

        return MakerJobFile::query()->create([
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
        ]);
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
    public function forJob(int $jobId, ?string $collection = null)
    {
        $q = MakerJobFile::query()->where('job_id', $jobId)->orderBy('sort_order')->orderBy('id');
        if ($collection) {
            $q->where('collection', $collection);
        }

        return $q->get();
    }

    public function absolutePath(MakerJobFile $file): ?string
    {
        try {
            $path = Storage::disk($file->disk ?: 'public')->path($file->path);
        } catch (\Throwable) {
            return null;
        }

        return is_string($path) && is_file($path) ? $path : null;
    }

    private function resolveJob(int $userId, ?string $token, ?int $jobId): ?MakerJob
    {
        if ($jobId) {
            $job = MakerJob::query()->find($jobId);
            if ($job && (int) $job->user_id === $userId) {
                return $job;
            }
        }
        if ($token) {
            return MakerJob::query()
                ->where('user_id', $userId)
                ->where('upload_token', $token)
                ->first();
        }

        return null;
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
            Storage::disk($row->disk ?: 'public')->delete($row->path);
        } catch (\Throwable) {
        }
        $row->delete();
    }
}
