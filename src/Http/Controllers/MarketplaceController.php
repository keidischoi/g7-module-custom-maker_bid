<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Services\MarketplaceService;
use Modules\Custom\MakerBids\Support\ArrayPaginator;
use Modules\Custom\MakerBids\Support\DomainException;

class MarketplaceController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(private readonly MarketplaceService $market) {}

    public function notices(Request $request): JsonResponse
    {
        $items = $this->market->notices((int) $request->user()->id);

        return response()->json(ArrayPaginator::paginate($items, $request, 'page', 10));
    }

    public function readNotice(Request $request, int $id): JsonResponse
    {
        $this->market->markRead((int) $request->user()->id, $id);

        return response()->json(['ok' => true]);
    }

    public function compare(int $id): JsonResponse
    {
        return response()->json(['data' => $this->market->compare($id)]);
    }

    public function rejectBid(Request $request, int $id, int $bidId): JsonResponse
    {
        try {
            $this->market->rejectBid((int) $request->user()->id, $id, $bidId);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->market->complete(
                (int) $request->user()->id,
                $id,
                (int) $request->input('score', 5),
                $request->input('comment')
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }

    public function work(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->market->setWork(
                (int) $request->user()->id,
                $id,
                (string) $request->input('work_status', 'producing'),
                $request->input('tracking_no'),
                $request->input('carrier')
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data]);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->market->messages((int) $request->user()->id, $id)]);
    }

    public function postMessage(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->market->postMessage((int) $request->user()->id, $id, (string) $request->input('body', ''));
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data], 201);
    }

    public function claim(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->market->claim((int) $request->user()->id, $id, (string) $request->input('reason', ''));
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data], 201);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->market->report((int) $request->user()->id, $id, (string) $request->input('reason', ''));
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data], 201);
    }

    public function export(int $id): JsonResponse
    {
        return response()->json(['data' => $this->market->exportJob($id)]);
    }

    public function closeExpired(): JsonResponse
    {
        return response()->json(['data' => ['closed' => $this->market->closeExpired()]]);
    }

    public function runSchedule(): JsonResponse
    {
        return response()->json(['data' => $this->market->runSchedule()]);
    }

    public function reviews(int $id): JsonResponse
    {
        return response()->json(['data' => $this->market->reviewsForJob($id)]);
    }

    public function companyReviews(int $id): JsonResponse
    {
        return response()->json(['data' => $this->market->reviewsForCompany($id)]);
    }
}

