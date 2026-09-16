<?php

namespace Modules\Custom\MakerBid\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBid\Models\MakerBid;
use Modules\Custom\MakerBid\Models\MakerJobFile;
use Modules\Custom\MakerBid\Services\JobFileService;
use Modules\Custom\MakerBid\Services\JobService;
use Modules\Custom\MakerBid\Support\DomainException;
use Modules\Custom\MakerBid\Support\JobRules;
use Modules\Custom\MakerBid\Support\PrivacyRules;
use Modules\Custom\MakerBid\Support\UploadRules;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobFileController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobFileService $files,
        private readonly JobService $jobs,
    ) {}

    public function store(Request $request, ?int $jobId = null): JsonResponse
    {
        try {
            $file = $this->uploadedFile($request);
            if ($file === null) {
                throw new DomainException('파일을 선택해 주세요.', 422);
            }
            $collection = (string) ($request->input('collection') ?: $request->input('kind') ?: UploadRules::COLLECTION_IMAGES);
            $token = $request->input('upload_token') ?: $request->input('token');
            $row = $this->files->store(
                (int) $request->user()->id,
                $file,
                $collection,
                is_string($token) ? $token : null,
                $jobId ?? ($request->filled('job_id') ? (int) $request->input('job_id') : null),
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json($row->toAttachmentArray(), 201);
    }

    public function destroy(Request $request, string $hash): JsonResponse
    {
        try {
            if (ctype_digit($hash)) {
                $row = MakerJobFile::query()->find((int) $hash);
                if ($row) {
                    $hash = (string) $row->hash;
                }
            }
            $this->files->destroyOwned((int) $request->user()->id, $hash);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['ok' => true]);
    }

    public function download(Request $request, string $hash): BinaryFileResponse|JsonResponse
    {
        try {
            $row = $this->files->findByHash($hash);
            $this->assertCanDownload($request, $row);
            $path = $this->files->absolutePath($row);
            if ($path === null) {
                throw new DomainException('파일을 찾을 수 없습니다.', 404);
            }
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->file($path, [
            'Content-Type' => $row->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($row->original_filename).'"',
        ]);
    }

    private function assertCanDownload(Request $request, MakerJobFile $row): void
    {
        $job = $row->job;
        $ctx = $this->jobs->viewerFromRequest($request);

        if ($job && JobRules::isHiddenFromPublic((string) $job->status)) {
            if (! $ctx['isAdmin'] && $ctx['userId'] !== (int) $job->user_id) {
                throw new DomainException('파일을 찾을 수 없습니다.', 404);
            }
        }

        if ($row->collection !== UploadRules::COLLECTION_ARCHIVES) {
            return;
        }

        if (! $job) {
            if ($ctx['userId'] !== (int) $row->user_id && ! $ctx['isAdmin']) {
                throw new DomainException('파일을 찾을 수 없습니다.', 403);
            }

            return;
        }

        $awardedUserId = null;
        if ($job->awarded_bid_id) {
            $awardedUserId = $job->awardedBid?->user_id;
            if ($awardedUserId === null) {
                $bid = MakerBid::query()->find($job->awarded_bid_id);
                $awardedUserId = $bid?->user_id;
            }
        }

        if (! PrivacyRules::canViewArchives(
            $ctx['userId'],
            $job->user_id,
            (string) $job->status,
            $awardedUserId,
            $ctx['isAdmin'],
        )) {
            throw new DomainException('제작 의뢰가 확정된 뒤에 파일을 볼 수 있습니다.', 403);
        }
    }

    private function uploadedFile(Request $request): ?UploadedFile
    {
        foreach (['file', 'uploadFile', 'image', 'archive'] as $key) {
            $file = $request->file($key);
            if ($file instanceof UploadedFile && $file->isValid()) {
                return $file;
            }
        }
        foreach ($request->allFiles() as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                return $file;
            }
            if (is_array($file)) {
                foreach ($file as $inner) {
                    if ($inner instanceof UploadedFile && $inner->isValid()) {
                        return $inner;
                    }
                }
            }
        }

        return null;
    }
}
