<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F1.5, F1.6, F12.7: Device binding & trusted device management */
class TrustedDeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $devices = DB::table('trusted_devices')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_used_at')
            ->get();

        return $this->success($devices);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_identifier' => 'required|string|max:256',
            'name' => 'nullable|string|max:255',
        ]);

        $userId = $request->user()->id;
        $exists = DB::table('trusted_devices')
            ->where('user_id', $userId)
            ->where('device_identifier', $validated['device_identifier'])
            ->exists();

        if ($exists) {
            DB::table('trusted_devices')
                ->where('user_id', $userId)
                ->where('device_identifier', $validated['device_identifier'])
                ->update(['last_used_at' => now(), 'updated_at' => now()]);
            $device = DB::table('trusted_devices')
                ->where('user_id', $userId)
                ->where('device_identifier', $validated['device_identifier'])
                ->first();
            return $this->success($device, 'Device updated');
        }

        $id = DB::table('trusted_devices')->insertGetId([
            'user_id' => $userId,
            'device_identifier' => $validated['device_identifier'],
            'name' => $validated['name'] ?? null,
            'last_used_at' => now(),
            'trusted_at' => now(),
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('trusted_devices')->find($id), 'Device registered', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = DB::table('trusted_devices')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            return $this->error('Not found', 404);
        }

        return $this->success(null, 'Device removed');
    }
}
