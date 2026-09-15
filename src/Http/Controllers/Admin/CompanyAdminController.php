<?php

namespace Modules\Custom\MakerBid\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBid\Http\Requests\Admin\RejectCompanyRequest;
use Modules\Custom\MakerBid\Http\Requests\Admin\StoreCompanyRequest;
use Modules\Custom\MakerBid\Services\CompanyService;
use Modules\Custom\MakerBid\Support\DomainException;

class CompanyAdminController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly CompanyService $companies,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->companies->listAdmin($request)]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        try {
            $row = $this->companies->storeAdmin($request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row], 201);
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $row = $this->companies->approve($id);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row]);
    }

    public function reject(RejectCompanyRequest $request, int $id): JsonResponse
    {
        try {
            $row = $this->companies->reject($id, $request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->companies->destroy($id);

        return response()->json(['ok' => true]);
    }
}
