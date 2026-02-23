<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F11.4: Book branch or call-back appointment */
class AppointmentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $list = DB::table('appointments')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('scheduled_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:branch,callback',
            'branch_id' => 'required_if:type,branch|nullable|exists:branches,id',
            'scheduled_at' => 'required|date|after:now',
            'notes' => 'nullable|string|max:500',
        ]);

        $id = DB::table('appointments')->insertGetId([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'branch_id' => $validated['branch_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'scheduled',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('appointments')->find($id), 'Appointment created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('appointments')->where('user_id', $request->user()->id)->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = DB::table('appointments')->where('user_id', $request->user()->id)->where('id', $id)->delete();
        if (! $deleted) {
            return $this->error('Not found', 404);
        }
        return $this->success(null, 'Appointment cancelled');
    }
}
