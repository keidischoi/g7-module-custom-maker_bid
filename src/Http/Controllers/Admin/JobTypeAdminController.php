<?php

namespace Modules\Custom\MakerBid\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Http\Concerns\RespondsWithDomainErrors;
use Modules\Custom\MakerBid\Http\Requests\Admin\StoreJobTypeRequest;
use Modules\Custom\MakerBid\Http\Requests\Admin\UpdateJobTypeRequest;
use Modules\Custom\MakerBid\Services\JobTypeService;
use Modules\Custom\MakerBid\Support\DomainException;

class JobTypeAdminController extends Controller
{
    use RespondsWithDomainErrors;

    public function __construct(
        private readonly JobTypeService $types,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->types->listAdmin()->map->toOptionArray()->values()->all(),
        ]);
    }

    public function store(StoreJobTypeRequest $request): JsonResponse
    {
        try {
            $row = $this->types->create($request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row->toOptionArray()], 201);
    }

    public function update(UpdateJobTypeRequest $request, int $id): JsonResponse
    {
        try {
            $row = $this->types->update($id, $request->validated());
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row->toOptionArray()]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->types->destroy($id);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['ok' => true]);
    }

    public function move(Request $request, int $id): JsonResponse
    {
        $direction = (string) $request->input('direction', 'down');
        if (! in_array($direction, ['up', 'down'], true)) {
            $direction = 'down';
        }
        try {
            $row = $this->types->move($id, $direction);
        } catch (DomainException $e) {
            return $this->domainError($e);
        }

        return response()->json(['data' => $row->toOptionArray()]);
    }
}
