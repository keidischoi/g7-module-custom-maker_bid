<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBids\Services\JobTypeService;

class JobTypeController extends Controller
{
    public function __construct(
        private readonly JobTypeService $types,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->types->listPublic()->map->toOptionArray()->values()->all(),
        ]);
    }
}
