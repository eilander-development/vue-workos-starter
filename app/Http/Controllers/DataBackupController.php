<?php

namespace App\Http\Controllers;

use App\Services\DataBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DataBackupController extends Controller
{
    public function __construct(protected DataBackupService $backups) {}

    public function export(): BinaryFileResponse|JsonResponse
    {
        $path = $this->backups->newExportPath();

        try {
            $result = $this->backups->exportTo($path);
        } catch (Throwable $e) {
            File::delete($path);

            return response()->json([
                'message' => $e->getMessage() ?: 'Export mislukt.',
            ], 500);
        }

        return response()
            ->download($result['path'], $result['filename'], [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $validated['file'];
        if (! $file instanceof UploadedFile) {
            return response()->json([
                'message' => 'Kies een .zip-backup.',
            ], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'zip') {
            return response()->json([
                'message' => 'Kies een .zip-backup.',
            ], 422);
        }

        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '') {
            return response()->json([
                'message' => 'Upload kon niet worden gelezen.',
            ], 422);
        }

        try {
            $summary = $this->backups->importFrom($realPath);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Backup gezet. Alle data is overschreven.',
            'summary' => $summary,
        ]);
    }
}
