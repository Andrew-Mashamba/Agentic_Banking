<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppDataExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Internal API: export user's WhatsApp-related data (GDPR portability).
 * User context via X-User-Id.
 */
class WhatsAppDataExportController extends Controller
{
    public function __invoke(Request $request, WhatsAppDataExportService $export): JsonResponse
    {
        $userId = $request->header('X-User-Id');
        if (! $userId) {
            return response()->json(['error' => 'X-User-Id required'], 400);
        }
        $user = User::find($userId);
        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $data = $export->exportForUser($user);
        return response()->json(['data' => $data]);
    }
}
