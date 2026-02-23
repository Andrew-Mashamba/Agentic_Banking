<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $tickets = DB::table('support_tickets')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'open';
        $validated['ticket_number'] = 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
        $validated['created_at'] = $validated['updated_at'] = now();

        $id = DB::table('support_tickets')->insertGetId($validated);
        $ticket = DB::table('support_tickets')->find($id);

        return $this->success($ticket, 'Ticket created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = DB::table('support_tickets')->where('user_id', $request->user()->id)->find($id);
        if (! $ticket) {
            return $this->error('Ticket not found', 404);
        }
        $messages = DB::table('support_ticket_messages')->where('support_ticket_id', $id)->orderBy('created_at')->get();
        return $this->success(['ticket' => $ticket, 'messages' => $messages]);
    }
}
