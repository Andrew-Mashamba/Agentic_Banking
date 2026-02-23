<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $branches = DB::table('branches')->where('status', 'active')->get();

        return $this->success($branches);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $branch = DB::table('branches')->where('status', 'active')->find($id);
        if (! $branch) {
            return $this->error('Branch not found', 404);
        }

        return $this->success($branch);
    }
}
