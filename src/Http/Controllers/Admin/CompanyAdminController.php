<?php

namespace Modules\Custom\MakerBid\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Custom\MakerBid\Models\MakerCompany;

class CompanyAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => MakerCompany::query()->latest()->limit(200)->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $row = MakerCompany::query()->create([
            'user_id' => (int) $request->input('user_id'),
            'name' => $request->input('name', '업체'),
            'type' => $request->input('type', 'print_3d'),
            'status' => $request->input('status', 'pending'),
            'note' => $request->input('note'),
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function approve(int $id): JsonResponse
    {
        $row = MakerCompany::query()->findOrFail($id);
        $row->status = 'approved';
        $row->save();

        return response()->json(['data' => $row]);
    }

    public function destroy(int $id): JsonResponse
    {
        MakerCompany::query()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
