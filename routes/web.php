<?php

use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\EnableBankingController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware(['auth', ValidateSessionWithWorkOS::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/yearly-expenses-chart', [DashboardController::class, 'yearlyExpensesChart'])->name('dashboard.yearly_expenses_chart');
    Route::get('/dashboard/data', [DashboardController::class, 'dashboardData'])->name('dashboard.data');
    Route::post('/dashboard/dynamic-budgets', [DashboardController::class, 'storeDynamicBudgets'])->name('dashboard.dynamic_budgets.store');
    Route::get('/expenses/{category?}', [ExpensesController::class, 'index'])->name('expenses');
    Route::get('/income/{category?}', [IncomeController::class, 'index'])->name('income');
    Route::get('/savings/{category?}', [SavingsController::class, 'index'])->name('savings');
    Route::get('/transactions', [TransactionsController::class, 'index'])->name('transactions');
    Route::get('/imports/transactions', [ImportController::class, 'index'])->name('imports.transactions');
    Route::get('/imports/rules', [ImportController::class, 'rulesIndex'])->name('imports.rules');
    Route::post('/imports/transactions', [ImportController::class, 'import'])->name('imports.transactions.import');
    Route::post('/imports/transactions/rules', [ImportController::class, 'storeRule'])->name('imports.transactions.rules.store');
    Route::patch('/imports/transactions/rules/{rule}', [ImportController::class, 'updateRule'])->name('imports.transactions.rules.update');
    Route::delete('/imports/transactions/rules/{rule}', [ImportController::class, 'destroyRule'])->name('imports.transactions.rules.destroy');
    Route::get('/imports/transactions/rules/{rule}/transactions', [ImportController::class, 'ruleTransactions'])->name('imports.transactions.rules.transactions');
    Route::get('/imports/transactions/rules/{rule}/similar-transactions', [ImportController::class, 'similarRuleTransactions'])->name('imports.transactions.rules.similar_transactions');
    Route::post('/imports/transactions/rules/{rule}/transactions/{transaction}/apply', [ImportController::class, 'applyRuleToTransaction'])->name('imports.transactions.rules.transactions.apply');
    Route::post('/imports/transactions/rules/{rule}/apply', [ImportController::class, 'applyRule'])->name('imports.transactions.rules.apply');
    Route::post('/imports/transactions/rules/{rule}/apply-all-matches', [ImportController::class, 'applyRuleToAllMatches'])->name('imports.transactions.rules.apply_all_matches');
    Route::post('/imports/transactions/rules/{rule}/reassign-matched-budget', [ImportController::class, 'reassignMatchedRuleBudget'])->name('imports.transactions.rules.reassign_matched_budget');
    Route::post('/transactions/{transactionId}/assign', [TransactionsController::class, 'assign'])->name('transactions.assign');
    Route::post('/transactions/bulk-reassign-budget', [TransactionsController::class, 'bulkReassignBudget'])->name('transactions.bulk_reassign_budget');
    Route::delete('/transactions/source', [TransactionsController::class, 'destroyBySource'])->name('transactions.destroy_by_source');
    Route::delete('/transactions/{transaction}', [TransactionsController::class, 'destroy'])->name('transactions.destroy');
    Route::get('/categories', [CategoriesController::class, 'index'])->name('categories');
    Route::post('/categories', [CategoriesController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/{category}/budgets', [CategoriesController::class, 'storeBudget'])->name('categories.budgets.store');
    Route::delete('/categories/{category}/budgets/{budget}', [CategoriesController::class, 'destroyBudget'])->name('categories.budgets.destroy');

    Route::get('/enabled-banking', [EnableBankingController::class, 'index'])->name('enabled_banking');
    Route::get('/enabled-banking/connect', [EnableBankingController::class, 'connect'])->name('enabled_banking.connect');
    Route::get('/enabled-banking/balance', [EnableBankingController::class, 'balance'])->name('enabled_banking.balance');
    Route::get('/enabled-banking/accounts', [EnableBankingController::class, 'accounts'])->name('enabled_banking.accounts');
    Route::get('/enabled-banking/accounts/{accountId}/transactions', [EnableBankingController::class, 'transactions'])->name('enabled_banking.transactions');
    Route::post('/enabled-banking/import-transactions', [EnableBankingController::class, 'importTransactions'])->name('enabled_banking.import_transactions');
    Route::get('/enabled-banking/auth_redirect', [EnableBankingController::class, 'handleCallback'])->name('enabled_banking.callback');
    Route::post('/enabled-banking/disconnect', [EnableBankingController::class, 'disconnect'])->name('enabled_banking.disconnect');

});

// TrueLayer routes removed

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
