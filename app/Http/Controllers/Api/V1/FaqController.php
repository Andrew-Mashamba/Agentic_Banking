<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaqController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('faqs')->where('is_active', true)->orderBy('sort_order');
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        $faqs = $query->get();

        return $this->success($faqs);
    }
}
