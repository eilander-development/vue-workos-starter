<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BudgetsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([ 'auth', ValidateSessionWithWorkOS::class,])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/budgets', [BudgetsController::class, 'index'])->name('budgets');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
