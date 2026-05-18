<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([ 'auth', ValidateSessionWithWorkOS::class,])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/expenses/{category?}', [ExpensesController::class, 'index'])->name('expenses');
    Route::get('/income/{category?}', [IncomeController::class, 'index'])->name('income');
    Route::get('/savings/{category?}', [SavingsController::class, 'index'])->name('savings');
    Route::get('/transactions', [TransactionsController::class, 'index'])->name('transactions');
    Route::post('/transactions/{transactionId}/assign', [TransactionsController::class, 'assign'])->name('transactions.assign');
    Route::get('/categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoriesController::class, 'store'])->name('categories.store');
    Route::post('/categories/{category}/budgets', [CategoriesController::class, 'storeBudget'])->name('categories.budgets.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
