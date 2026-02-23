<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Services\BankingAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Transfer::whereHas('fromAccount', fn ($q) => $q->where('user_id', $user->id))
            ->with(['fromAccount', 'toAccount', 'beneficiary']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('account_id')) {
            $account = Account::where('user_id', $user->id)->find($request->account_id);
            if ($account) {
                $query->where('from_account_id', $account->id);
            }
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $transfers = $query->latest()->paginate($perPage);

        return $this->success($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'nullable|exists:accounts,id',
            'beneficiary_id' => 'nullable|exists:beneficiaries,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'type' => 'required|in:own,same_bank,interbank,swift,mobile_wallet,utility,bill,merchant,cardless,loan,tax,insurance,bulk',
            'reference' => 'nullable|string|max:128',
            'scheduled_at' => 'nullable|date',
            'swift_details' => 'nullable|array',
        ]);

        $user = $request->user();
        $from = Account::where('user_id', $user->id)->find($validated['from_account_id']);
        if (! $from) {
            return $this->error('From account not found', 404);
        }

        if (isset($validated['beneficiary_id'])) {
            $ben = Beneficiary::where('user_id', $user->id)->find($validated['beneficiary_id']);
            if (! $ben) {
                return $this->error('Beneficiary not found', 404);
            }
        }

        $validated['currency'] = $validated['currency'] ?? $from->currency;
        $validated['status'] = $request->has('scheduled_at') ? 'scheduled' : 'pending';

        $transfer = Transfer::create($validated);

        BankingAuditService::log('transfer', 'transfer', $transfer->id, [
            'amount' => $transfer->amount,
            'currency' => $transfer->currency,
            'type' => $transfer->type,
            'reference' => $transfer->reference,
            'status' => $transfer->status,
        ]);

        return $this->success($transfer->load(['fromAccount', 'toAccount', 'beneficiary']), 'Transfer created', 201);
    }

    public function show(Request $request, Transfer $transfer): JsonResponse
    {
        if ($transfer->fromAccount->user_id !== $request->user()->id) {
            return $this->error('Forbidden', 403);
        }

        return $this->success($transfer->load(['fromAccount', 'toAccount', 'beneficiary']));
    }
}
