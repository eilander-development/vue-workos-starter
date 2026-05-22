<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Throwable;

trait HandlesApiErrors
{
    protected function apiErrorResponse(Throwable $exception, int $status = 422): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
        ], $status);
    }
}

