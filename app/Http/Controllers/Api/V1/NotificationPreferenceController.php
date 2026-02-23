<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F8: Alerts & notification preferences (per channel: push, sms, email) */
class NotificationPreferenceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $prefs = DB::table('notification_preferences')
            ->where('user_id', $request->user()->id)
            ->get();

        return $this->success($prefs);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:push,sms,email',
            'transaction_alerts' => 'sometimes|boolean',
            'low_balance_alerts' => 'sometimes|boolean',
            'low_balance_threshold' => 'nullable|numeric|min:0',
        ]);

        $userId = $request->user()->id;
        $channel = $validated['channel'];
        $exists = DB::table('notification_preferences')->where('user_id', $userId)->where('channel', $channel)->first();

        $data = [
            'transaction_alerts' => $validated['transaction_alerts'] ?? $exists->transaction_alerts ?? true,
            'low_balance_alerts' => $validated['low_balance_alerts'] ?? $exists->low_balance_alerts ?? true,
            'low_balance_threshold' => $validated['low_balance_threshold'] ?? $exists->low_balance_threshold ?? null,
            'updated_at' => now(),
        ];

        if ($exists) {
            DB::table('notification_preferences')->where('user_id', $userId)->where('channel', $channel)->update($data);
        } else {
            DB::table('notification_preferences')->insert([
                'user_id' => $userId,
                'channel' => $channel,
                'transaction_alerts' => $data['transaction_alerts'],
                'low_balance_alerts' => $data['low_balance_alerts'],
                'low_balance_threshold' => $data['low_balance_threshold'],
                'created_at' => $data['updated_at'],
                'updated_at' => $data['updated_at'],
            ]);
        }

        return $this->success(DB::table('notification_preferences')->where('user_id', $userId)->where('channel', $channel)->first());
    }
}
