<?php

namespace App\Http\Controllers;

use App\Services\ReservationReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationReviewController extends Controller
{
    public function show(Request $request, string $key, ReservationReviewService $service): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        return response()->json($service->review($key, $data['search'] ?? ''));
    }

    public function resolve(Request $request, string $key, ReservationReviewService $service): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:match,expire'],
            'revision' => ['required', 'string', 'size:64'],
            'bookedKey' => ['required_if:action,match', 'nullable', 'string', 'max:255'],
            'bookedRevision' => ['required_if:action,match', 'nullable', 'string', 'size:64'],
            'linkChoice' => ['required_if:action,match', 'in:merge,reservation,booked'],
            'scaleSplit' => ['sometimes', 'boolean'],
        ]);

        return response()->json($service->resolve($key, $data));
    }
}
