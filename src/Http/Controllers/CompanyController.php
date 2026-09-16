<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBids\Http\Requests\ApplyCompanyRequest;
use Modules\Custom\MakerBids\Services\CompanyService;
use Modules\Custom\MakerBids\Support\DomainException;

class CompanyController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly CompanyService $companies,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->companies->listPublic()]);
    }

    public function formDefaults(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->companies->formDefaults($request->user())]);
    }

    public function store(ApplyCompanyRequest $request): JsonResponse
    {
        try {
            $row = $this->companies->apply((int) $request->user()->id, $request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $row = $this->companies->mine((int) $request->user()->id);

        return response()->json(['data' => $row]);
    }
}
