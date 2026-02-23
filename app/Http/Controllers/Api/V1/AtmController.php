<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F11.3: Locate ATMs */
class AtmController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('atms')->where('status', 'active');

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $list = (clone $query)->get()->map(function ($row) use ($lat, $lng) {
                $row->distance = $row->latitude && $row->longitude
                    ? round(sqrt(pow(($row->latitude - $lat) * 111, 2) + pow(($row->longitude - $lng) * 111, 2)), 2)
                    : null;
                return $row;
            })->sortBy('distance')->values()->take(100);
        } else {
            $list = $query->orderBy('id')->limit(100)->get();
        }

        return $this->success($list);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = DB::table('atms')->where('status', 'active')->find($id);
        if (! $row) {
            return $this->error('Not found', 404);
        }
        return $this->success($row);
    }
}
