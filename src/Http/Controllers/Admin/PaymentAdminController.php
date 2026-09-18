<?php

namespace Modules\Custom\MakerBids\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Models\MakerPayment;
use Modules\Custom\MakerBids\Services\PaymentService;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\PaymentRules;

class PaymentAdminController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        $items = $this->payments->listAdmin($status !== '' ? $status : null);

        return response()->json([
            'data' => $items,
            'meta' => [
                'statuses' => PaymentRules::statusOptions(),
                'methods' => PaymentRules::methodOptions(),
                'destinations' => PaymentRules::destinationOptions(),
                'kinds' => PaymentRules::kindOptions(),
                'require_confirmed' => $this->payments->requireConfirmed(),
            ],
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $jobId = $this->jobIdFromPayment($id);
            $actor = $request->user() ? (int) $request->user()->id : 0;
            $data = $this->payments->confirm($actor, $jobId, true, $id);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        try {
            $jobId = $this->jobIdFromPayment($id);
            $actor = $request->user() ? (int) $request->user()->id : 0;
            $data = $this->payments->refund(
                $actor,
                $jobId,
                (string) $request->input('refund_note', $request->input('note', '')),
                $id
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }

    private function jobIdFromPayment(int $id): int
    {
        $row = MakerPayment::query()->find($id);
        if ($row === null) {
            throw new DomainException('결제 내역이 없습니다.', 404);
        }

        return (int) $row->job_id;
    }
}
