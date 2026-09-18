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
            $data = $this->market->claim(
                (int) $request->user()->id,
                $id,
                (string) $request->input('reason', ''),
                (string) $request->input('factor', '')
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data], 201);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->market->report(
                (int) $request->user()->id,
                $id,
                (string) $request->input('reason', ''),
                (string) $request->input('factor', 'fraud')
            );
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $data], 201);
    }

    public function myDisputes(Request $request): JsonResponse
    {
        $items = $this->market->listMineDisputes((int) $request->user()->id);
        $payload = ArrayPaginator::paginate($items, $request, 'page', 10);
        $payload['meta']['factors'] = \Modules\Custom\MakerBids\Support\DisputeRules::factorOptions();

        return response()->json($payload);
    }

    public function export(Request $request, int $id)
    {
        $doc = (string) $request->query('doc', 'all');
        if (! in_array($doc, ['all', 'request', 'quote'], true)) {
            $doc = 'all';
        }
        if ((string) $request->query('format', 'json') === 'html') {
            $html = $this->market->exportHtml($id, $doc);
            $filename = $doc === 'quote' ? 'quote-'.$id.'.html' : ($doc === 'request' ? 'request-'.$id.'.html' : 'job-'.$id.'-forms.html');

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return response()->json(['data' => $this->market->exportJob($id, $doc)]);
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

