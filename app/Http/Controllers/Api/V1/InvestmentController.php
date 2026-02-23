<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F6: Investment products, investments, securities, dividends, dividend reinvestment */
class InvestmentController extends BaseApiController
{
    public function products(Request $request): JsonResponse
    {
        $products = DB::table('investment_products')->where('is_active', true)->get();
        return $this->success($products);
    }

    public function index(Request $request): JsonResponse
    {
        $list = DB::table('investments')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($list);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'investment_product_id' => 'required|exists:investment_products,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $id = DB::table('investments')->insertGetId([
            'user_id' => $request->user()->id,
            'investment_product_id' => $validated['investment_product_id'],
            'amount' => $validated['amount'],
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        return $this->success(DB::table('investments')->find($id), 'Investment created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $inv = DB::table('investments')->where('user_id', $request->user()->id)->find($id);
        if (! $inv) {
            return $this->error('Not found', 404);
        }
        return $this->success($inv);
    }

    public function securities(Request $request): JsonResponse
    {
        $list = DB::table('securities_holdings')
            ->where('user_id', $request->user()->id)
            ->get();

        return $this->success($list);
    }

    public function dividends(Request $request): JsonResponse
    {
        $investmentIds = DB::table('investments')->where('user_id', $request->user()->id)->pluck('id');
        $list = DB::table('dividends')
            ->where('investable_type', 'like', '%Investment%')
            ->whereIn('investable_id', $investmentIds)
            ->orderByDesc('paid_at')
            ->get();

        return $this->success($list);
    }

    public function dividendReinvestment(Request $request): JsonResponse
    {
        $prefs = DB::table('dividend_reinvestment_settings')
            ->where('user_id', $request->user()->id)
            ->get();

        return $this->success($prefs);
    }

    public function updateDividendReinvestment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'investable_type' => 'required|string|max:128',
            'investable_id' => 'required|integer|min:1',
        ]);

        $userId = $request->user()->id;
        $now = now();
        $existing = DB::table('dividend_reinvestment_settings')
            ->where('user_id', $userId)
            ->where('investable_type', $validated['investable_type'])
            ->where('investable_id', $validated['investable_id'])
            ->first();

        if ($existing) {
            DB::table('dividend_reinvestment_settings')->where('id', $existing->id)->update([
                'enabled' => $validated['enabled'],
                'updated_at' => $now,
            ]);
        } else {
            DB::table('dividend_reinvestment_settings')->insert([
                'user_id' => $userId,
                'investable_type' => $validated['investable_type'],
                'investable_id' => $validated['investable_id'],
                'enabled' => $validated['enabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->success(DB::table('dividend_reinvestment_settings')->where('user_id', $userId)->get());
    }
}
