<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DataBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class DataBackupController extends Controller
{
    public function __construct(protected DataBackupService $backups) {}

    public function export(): Response|JsonResponse
    {
        $path = $this->backups->newExportPath();

        try {
            $result = $this->backups->exportTo($path);
            $bytes = File::get($result['path']);
        } catch (Throwable $e) {
            File::delete($path);

            return response()->json([
                'message' => $e->getMessage() ?: 'Export mislukt.',
            ], 500);
        }

        File::delete($result['path']);

        return response($bytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'preview' => ['sometimes'],
            'resolutions' => ['sometimes'],
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

        $preview = $request->boolean('preview');
        $resolutions = $this->importResolutions($request->input('resolutions'));

        try {
            $summary = $this->backups->importFrom($realPath, null, [
                'preview' => $preview,
                'resolutions' => $resolutions,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $preview
                ? 'Vergelijking klaar. Nieuwe rijen worden toegevoegd; bestaande blijven tenzij je de backup kiest.'
                : 'Import klaar. Nieuwe rijen zijn toegevoegd, bestaande data is niet gewist.',
            'summary' => $summary,
        ]);
    }

    public function setupImport(Request $request): RedirectResponse
    {
        if (User::query()->exists()) {
            abort(404);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $file = $validated['file'];
        if (! $file instanceof UploadedFile || strtolower($file->getClientOriginalExtension()) !== 'zip') {
            throw ValidationException::withMessages([
                'file' => 'Kies een .zip-backup.',
            ]);
        }

        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '') {
            throw ValidationException::withMessages([
                'file' => 'Upload kon niet worden gelezen.',
            ]);
        }

        try {
            $this->backups->importFrom($realPath);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'file' => $e->getMessage(),
            ]);
        }

        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'file' => 'Backup bevat geen gebruiker.',
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return redirect()->route('login')->with('status', 'Backup gezet. Log in met '.$user->email.'.');
    }

    /**
     * @return array<string, 'live'|'backup'>
     */
    private function importResolutions(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $resolutions = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && in_array($value, ['live', 'backup'], true)) {
                $resolutions[$key] = $value;
            }
        }

        return $resolutions;
    }
}
