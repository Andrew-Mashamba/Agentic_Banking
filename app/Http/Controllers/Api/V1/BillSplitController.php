<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F7.5: Split bills */
class BillSplitController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $list = DB::table('bill_splits')
            ->where('created_by_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        $participant = DB::table('bill_split_members')
            ->where('user_id', $request->user()->id)
            ->pluck('bill_split_id');
        $splits = $participant->isEmpty() ? [] : DB::table('bill_splits')->whereIn('id', $participant)->orderByDesc('created_at')->get();

        return $this->success(['created' => $list, 'participant' => $splits]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total_amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'members' => 'required|array|min:1',
            'members.*.user_id' => 'required|exists:users,id',
            'members.*.amount_owed' => 'required|numeric|min:0',
        ]);

        $id = DB::table('bill_splits')->insertGetId([
            'created_by_id' => $request->user()->id,
            'total_amount' => $validated['total_amount'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        foreach ($validated['members'] as $m) {
            DB::table('bill_split_members')->insert([
                'bill_split_id' => $id,
                'user_id' => $m['user_id'],
                'amount_owed' => $m['amount_owed'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->success(DB::table('bill_splits')->find($id), 'Bill split created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('bill_splits')->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        $isCreator = (int) $row->created_by_id === $request->user()->id;
        $isMember = DB::table('bill_split_members')->where('bill_split_id', $id)->where('user_id', $request->user()->id)->exists();
        if (! $isCreator && ! $isMember) {
            return $this->error('Not found', 404);
        }
        $members = DB::table('bill_split_members')->where('bill_split_id', $id)->get();
        return $this->success(['bill_split' => $row, 'members' => $members]);
    }
}
