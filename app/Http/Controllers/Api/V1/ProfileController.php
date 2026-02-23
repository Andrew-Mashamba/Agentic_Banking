<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->only(['id', 'name', 'email', 'phone_number', 'role', 'status', 'primary_account_id']);

        return $this->success($user);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'nullable|string|max:32',
        ]);

        $user = $request->user();
        $oldPhone = $user->phone_number;
        $user->update($validated);

        if (array_key_exists('phone_number', $validated) && $oldPhone !== $validated['phone_number'] && $oldPhone) {
            try {
                app(\App\Services\WhatsApp\WhatsAppPhoneMigrationService::class)->migrate($user->fresh(), $oldPhone, $validated['phone_number'] ?? '');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::channel('whatsapp')->warning('Phone migration failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return $this->success($request->user()->fresh()->only(['id', 'name', 'email', 'phone_number', 'role', 'status']));
    }
}
