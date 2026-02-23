<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** F1.7, F1.8: KYC submission & document upload */
class KycController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $submissions = DB::table('kyc_submissions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->success($submissions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => 'sometimes|in:draft,pending']);

        $submission = DB::table('kyc_submissions')->insertGetId([
            'user_id' => $request->user()->id,
            'status' => $validated['status'] ?? 'draft',
            'submitted_at' => $request->input('status') === 'pending' ? now() : null,
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);

        $row = DB::table('kyc_submissions')->find($submission);

        return $this->success($row, 'KYC submission created', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $sub = DB::table('kyc_submissions')->where('user_id', $request->user()->id)->find($id);
        if (! $sub) {
            return $this->error('Not found', 404);
        }
        $documents = DB::table('kyc_documents')->where('kyc_submission_id', $id)->get();
        return $this->success(['submission' => $sub, 'documents' => $documents]);
    }

    public function addDocument(Request $request, int $id): JsonResponse
    {
        $sub = DB::table('kyc_submissions')->where('user_id', $request->user()->id)->find($id);
        if (! $sub) {
            return $this->error('Not found', 404);
        }
        $validated = $request->validate([
            'document_type' => 'required|string|max:64',
            'file_path' => 'required|string|max:1024',
        ]);
        DB::table('kyc_documents')->insert([
            'kyc_submission_id' => $id,
            'document_type' => $validated['document_type'],
            'file_path' => $validated['file_path'],
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);
        return $this->success(DB::table('kyc_documents')->where('kyc_submission_id', $id)->get(), 'Document added', 201);
    }
}
