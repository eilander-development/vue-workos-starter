<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

$authMiddleware = ['auth'];
if (! app()->environment('local')) {
    $authMiddleware[] = ValidateSessionWithWorkOS::class;
}

Route::middleware($authMiddleware)->group(function () {
    Route::redirect('settings', '/instellingen/profiel');
    Route::redirect('settings/profile', '/instellingen/profiel');
    Route::redirect('settings/appearance', '/instellingen/uiterlijk');

    // SPA settings forms post to these endpoints.
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
