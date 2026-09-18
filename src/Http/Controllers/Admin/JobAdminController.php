<?php

namespace Modules\Custom\MakerBids\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Http\Requests\Admin\UpdateJobRequest;
use Modules\Custom\MakerBids\Models\MakerJob;
use Modules\Custom\MakerBids\Services\JobService;
use Modules\Custom\MakerBids\Services\MarketplaceService;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\JobRules;

class JobAdminController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobService $jobs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->jobs->listAdmin($request)]);
        } catch (\Throwable $e) {
            return response()->json(['data' => []]);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json(['data' => $this->jobs->findAdmin($id)]);
        } catch (\Throwable $e) {
            $job = MakerJob::query()->findOrFail($id);
            return response()->json(['data' => [
                'id' => (int) $job->id,
                'status' => (string) $job->status,
                'status_label' => JobRules::statusLabel((string) $job->status),
                'title' => (string) $job->title,
            ]]);
        }
    }

    public function update(UpdateJobRequest $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => $this->jobs->updateAdmin($id, $request->validated())]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        } catch (\Throwable $e) {
            return $this->setStatusDirect($id, (string) ($request->input('status') ?? ''));
        }
    }

    public function approve(int $id): JsonResponse
    {
        return $this->setStatusDirect($id, 'quote_request', 'approved', '의뢰가 승인되었습니다.');
    }

    public function hold(int $id): JsonResponse
    {
        return $this->setStatusDirect($id, 'hold', 'hold', '의뢰가 보류되었습니다.');
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->setStatusDirect($id, 'cancelled', 'job.cancel', '의뢰가 취소되었습니다.');
    }

    public function pending(int $id): JsonResponse
    {
        return $this->setStatusDirect($id, 'pending', 'job.pending', '의뢰가 승인대기로 변경되었습니다.');
    }

    public function dispute(int $id): JsonResponse
    {
        return $this->setStatusDirect($id, 'disputed', 'job.dispute', '의뢰가 분쟁조정 상태입니다.');
    }

    public function complete(int $id): JsonResponse
    {
        return $this->setStatusDirect($id, 'done', 'job.complete', '의뢰가 완료되었습니다.');
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->jobs->destroy($id);
        } catch (\Throwable) {
            MakerJob::query()->where('id', $id)->delete();
        }

        return response()->json(['ok' => true]);
    }

    private function setStatusDirect(int $id, string $status, ?string $audit = null, ?string $notice = null): JsonResponse
    {
        $allowed = array_values(array_unique(array_merge(JobRules::STATUSES, ['pending', 'disputed', 'quote_request', 'hold', 'cancelled', 'done', 'awarded', 'draft'])));
        if ($status === 'approved') {
            $status = 'quote_request';
        }
        if (! in_array($status, $allowed, true)) {
            $status = JobRules::normalizeListingStatus($status);
        }
        if (! in_array($status, $allowed, true)) {
            return response()->json(['message' => '올바른 상태가 아닙니다.'], 422);
        }
        $job = MakerJob::query()->findOrFail($id);
        $job->status = $status;
        $job->save();
        try {
            if ($notice) {
                $market = app(MarketplaceService::class);
                $market->notify((int) $job->user_id, $audit ?: $status, $notice, (string) $job->title, (int) $job->id);
                $market->audit(auth()->id() ? (int) auth()->id() : null, $audit ?: 'job.status', 'job', (int) $job->id);
            }
        } catch (\Throwable) {
        }

        return response()->json(['data' => [
            'id' => (int) $job->id,
            'status' => (string) $job->status,
            'status_label' => JobRules::statusLabel((string) $job->status),
        ]]);
    }
}
