<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ImportController;
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
    Route::get('/imports/transactions', [ImportController::class, 'index'])->name('imports.transactions');
    Route::post('/imports/transactions', [ImportController::class, 'import'])->name('imports.transactions.import');
    Route::post('/imports/transactions/rules', [ImportController::class, 'storeRule'])->name('imports.transactions.rules.store');
    Route::patch('/imports/transactions/rules/{rule}', [ImportController::class, 'updateRule'])->name('imports.transactions.rules.update');
    Route::delete('/imports/transactions/rules/{rule}', [ImportController::class, 'destroyRule'])->name('imports.transactions.rules.destroy');
    Route::get('/imports/transactions/rules/{rule}/transactions', [ImportController::class, 'ruleTransactions'])->name('imports.transactions.rules.transactions');
    Route::get('/imports/transactions/rules/{rule}/similar-transactions', [ImportController::class, 'similarRuleTransactions'])->name('imports.transactions.rules.similar_transactions');
    Route::post('/imports/transactions/rules/{rule}/transactions/{transaction}/apply', [ImportController::class, 'applyRuleToTransaction'])->name('imports.transactions.rules.transactions.apply');
    Route::post('/imports/transactions/rules/{rule}/apply', [ImportController::class, 'applyRule'])->name('imports.transactions.rules.apply');
    Route::get('/plaid', [App\Http\Controllers\PlaidController::class, 'index'])->name('plaid.index');
    Route::get('/plaid/link-token', [App\Http\Controllers\PlaidController::class, 'linkToken'])->name('plaid.link_token');
    Route::post('/plaid/settings', [App\Http\Controllers\PlaidController::class, 'saveSettings'])->name('plaid.settings.save');
    Route::post('/plaid/connect', [App\Http\Controllers\PlaidController::class, 'connect'])->name('plaid.connect');
    Route::post('/plaid/refresh', [App\Http\Controllers\PlaidController::class, 'refresh'])->name('plaid.refresh');
    Route::get('/gocardless', [App\Http\Controllers\GoCardlessController::class, 'index'])->name('gocardless.index');
    Route::post('/gocardless/connect', [App\Http\Controllers\GoCardlessController::class, 'connect'])->name('gocardless.connect');
    Route::post('/transactions/{transactionId}/assign', [TransactionsController::class, 'assign'])->name('transactions.assign');
    Route::delete('/transactions/{transaction}', [TransactionsController::class, 'destroy'])->name('transactions.destroy');
    Route::get('/categories', [CategoriesController::class, 'index'])->name('categories');
    Route::post('/categories', [CategoriesController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/{category}/budgets', [CategoriesController::class, 'storeBudget'])->name('categories.budgets.store');
    Route::delete('/categories/{category}/budgets/{budget}', [CategoriesController::class, 'destroyBudget'])->name('categories.budgets.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
