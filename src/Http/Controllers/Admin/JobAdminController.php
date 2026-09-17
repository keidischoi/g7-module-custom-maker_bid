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

class JobAdminController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobService $jobs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->jobs->listAdmin($request)]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->findAdmin($id)]);
    }

    public function update(UpdateJobRequest $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => $this->jobs->updateAdmin($id, $request->validated())]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $job = MakerJob::query()->findOrFail($id);
            $job->status = 'quote_request';
            $job->save();
            try {
                $market = app(MarketplaceService::class);
                $market->notify((int) $job->user_id, 'approved', '의뢰가 승인되었습니다.', (string) $job->title, (int) $job->id);
                $market->audit(auth()->id() ? (int) auth()->id() : null, 'job.approve', 'job', (int) $job->id);
            } catch (\Throwable) {
            }

            return response()->json(['data' => $this->jobs->findAdmin($id)]);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }
    }

    public function hold(int $id): JsonResponse
    {
        $data = $this->jobs->hold($id);
        try {
            $job = MakerJob::query()->find($id);
            if ($job) {
                $market = app(MarketplaceService::class);
                $market->notify((int) $job->user_id, 'hold', '의뢰가 보류되었습니다.', (string) $job->title, (int) $job->id);
                $market->audit(auth()->id() ? (int) auth()->id() : null, 'job.hold', 'job', (int) $job->id);
            }
        } catch (\Throwable) {
        }

        return response()->json(['data' => $data]);
    }

    public function cancel(int $id): JsonResponse
    {
        return response()->json(['data' => $this->jobs->cancel($id)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->jobs->destroy($id);

        return response()->json(['ok' => true]);
    }
}
