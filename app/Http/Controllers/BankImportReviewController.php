<?php

namespace App\Http\Controllers;

use App\Models\BankImportReview;
use App\Services\BankImportReviewService;
use App\Services\BankSyncReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankImportReviewController extends Controller
{
    public function index(BankImportReviewService $service): JsonResponse
    {
        return response()->json(BankImportReview::query()->where('status', 'open')->orderByDesc('seen_at')->orderByDesc('id')->paginate(25)->through(fn ($review) => $service->summary($review)));
    }

    public function show(string $key, BankImportReviewService $service): JsonResponse
    {
        return response()->json($service->details(BankImportReview::query()->where('key', $key)->firstOrFail()));
    }

    public function accept(Request $request, string $key, BankImportReviewService $service): JsonResponse
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'], 'keeperKey' => ['required', 'string', 'max:255'],
            'existingRevisions' => ['required', 'array', 'min:1', 'max:100'], 'existingRevisions.*' => ['required', 'string', 'size:64'],
            'linkSource' => ['required', 'string', 'max:255'], 'scaleSplit' => ['sometimes', 'boolean'],
        ]);
        $result = $service->accept($key, $data);
        $report = app(BankSyncReport::class)->normalize(session('sparen.sync_report'));
        session(['sparen.sync_report' => $report]);

        return response()->json([...$result, 'report' => $report, 'reviewCount' => BankImportReview::query()->where('status', 'open')->count()]);
    }
}
