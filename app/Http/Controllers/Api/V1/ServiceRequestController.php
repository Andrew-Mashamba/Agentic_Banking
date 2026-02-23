<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F10: Service requests (cheque book, stop cheque, address update, etc.) */
class ServiceRequestController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $list = DB::table('service_requests')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:cheque_book,stop_cheque,account_opening,address_update,dormant_reactivation',
            'details' => 'nullable|array',
        ]);

        $id = DB::table('service_requests')->insertGetId([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'details' => json_encode($validated['details'] ?? []),
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('service_requests')->find($id), 'Service request submitted', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('service_requests')->where('user_id', $request->user()->id)->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }
}
