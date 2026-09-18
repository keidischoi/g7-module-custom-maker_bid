<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Services\JobService;
use Modules\Custom\MakerBids\Services\PaymentService;
use Modules\Custom\MakerBids\Support\ArrayPaginator;
use Modules\Custom\MakerBids\Support\DomainException;

class PaymentController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly JobService $jobs,
    ) {}

    public function mine(Request $request): JsonResponse
    {
        $items = $this->payments->listMine((int) $request->user()->id);
        $payload = ArrayPaginator::paginate($items, $request, 'page', 10);
        $payload['meta']['require_confirmed'] = $this->payments->requireConfirmed();

        return response()->json($payload);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $ctx = $this->jobs->viewerFromRequest($request);
            $data = $this->payments->forJob(
                $id,
                (int) ($ctx['userId'] ?? 0),
                (bool) ($ctx['isAdmin'] ?? false)
            );
            if ($data === null) {
                throw new DomainException('결제 내역이 없습니다.', 404);
            }
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->payments->report(
                (int) $request->user()->id,
                $id,
                (string) $request->input('depositor_name', ''),
                (string) $request->input('memo', '')
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $ctx = $this->jobs->viewerFromRequest($request);
            $data = $this->payments->confirm(
                (int) ($ctx['userId'] ?? 0),
                $id,
                (bool) ($ctx['isAdmin'] ?? false)
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }
}
