<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsApp\FlowDataHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Flows Data Endpoint.
 * Meta POSTs here when a user opens or progresses through a flow (dynamic data, routing).
 * Must be public, HTTPS in production, respond within 15s.
 */
class FlowDataEndpointController extends Controller
{
    public function __construct(
        protected FlowDataHandler $flowHandler
    ) {
    }

    /**
     * POST /api/webhooks/whatsapp-flows/data
     */
    public function handle(Request $request): JsonResponse
    {
        Log::channel('whatsapp')->debug('Flow data endpoint received', [
            'keys' => array_keys($request->all()),
        ]);

        $version = $request->input('version', '3.0');
        $flowToken = $request->input('flow_token') ?? $request->input('data_exchange.flow_token');
        $screen = $request->input('screen') ?? $request->input('data_exchange.screen') ?? $request->input('flow_action_payload.screen');
        $data = $request->input('data') ?? $request->input('data_exchange.data') ?? $request->input('flow_action_payload.data') ?? [];
        $flowId = $request->input('flow_id');

        // Customer identifier: Meta may send customer_wa_id, from, or we get from initial flow payload
        $customerWaId = $request->input('customer_wa_id')
            ?? $request->input('from')
            ?? $request->input('data.from')
            ?? ($data['from'] ?? null);

        if (! $customerWaId && ! empty($data['user_id'])) {
            $user = User::find($data['user_id']);
            $customerWaId = $user?->phone_number;
        }

        if (! $customerWaId) {
            Log::channel('whatsapp')->warning('Flow data: no customer identifier');
            return response()->json([
                'version' => $version,
                'screen' => 'ERROR',
                'data' => ['error' => 'Could not identify customer. Please start the flow from chat.'],
            ], 200);
        }

        $user = User::where('phone_number', $customerWaId)
            ->orWhere('phone_number', ltrim($customerWaId, '+'))
            ->orWhere('phone_number', '+' . ltrim($customerWaId, '+'))
            ->first();

        if (! $user) {
            return response()->json([
                'version' => $version,
                'screen' => 'ERROR',
                'data' => ['error' => 'User not found. Please register for banking first.'],
            ], 200);
        }

        $flowType = $this->flowIdToType($flowId);

        try {
            $response = $this->flowHandler->handle($flowType, $screen, $data, $flowToken, $user);
            return response()->json($response, 200);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Flow data handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'version' => $version,
                'screen' => 'ERROR',
                'data' => ['error' => 'Something went wrong. Please try again.'],
            ], 200);
        }
    }

    protected function flowIdToType(?string $flowId): string
    {
        if (! $flowId) {
            return 'transfer';
        }
        $ids = config('whatsapp.flows.flow_ids', []);
        foreach ($ids as $type => $id) {
            if ($id === $flowId) {
                return $type;
            }
        }
        return 'transfer';
    }
}
