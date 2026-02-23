<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F2.11: Upcoming payments (scheduled + recurring) */
class UpcomingPaymentsController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accountIds = Account::where('user_id', $user->id)->pluck('id');

        $scheduled = DB::table('transfers')
            ->whereIn('from_account_id', $accountIds)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();

        $recurring = DB::table('recurring_transfers')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->orderBy('next_run_at')
            ->get();

        return $this->success([
            'scheduled_transfers' => $scheduled,
            'recurring_transfers' => $recurring,
        ]);
    }
}
