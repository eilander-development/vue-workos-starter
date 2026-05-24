<?php

namespace App\Http\Controllers;

use App\Http\Requests\Import\StoreImportRuleRequest;
use App\Http\Requests\Import\UpdateImportRuleRequest;
use App\Http\Requests\Import\ReassignMatchedRuleBudgetRequest;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\Transaction;
use App\Services\Categories;
use App\Services\CsvTransactionImporter;
use App\Services\ImportRuleMatcherQuery;
use App\Support\PaginationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function __construct(
        protected Categories $categoriesService,
        protected CsvTransactionImporter $csvTransactionImporter,
        protected ImportRuleMatcherQuery $importRuleMatcherQuery,
    ) {}

    public function index(): Response
    {
        return Inertia::render('ImportTransactions', [
            'categories' => $this->categoriesService->list(),
            'result' => session('result'),
        ]);
    }

    public function rulesIndex(): Response
    {
        return Inertia::render('ImportRules', [
            'categories' => $this->categoriesService->list(),
            'rules' => ImportRule::query()->latest()->paginate(100),
        ]);
    }

    public function storeRule(StoreImportRuleRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $ruleData = [
            'type' => $validated['type'],
            'match_value' => $validated['match_value'],
            'category_id' => $validated['category_id'],
            'budget_id' => $validated['budget_id'],
        ];

        $rule = ImportRule::create($ruleData);

        if (! empty($validated['transaction_id'])) {
            Transaction::where('id', $validated['transaction_id'])->update([
                'rule_id' => $rule->id,
            ]);
        }

        if ($request->wantsJson()) {
            $matchedTransactions = $this->importRuleMatcherQuery->forRule($rule)
                ->orderByDesc('date')
                ->paginate(100, ['*'], 'page', $request->integer('page', 1));

            return response()->json([
                'rule' => $rule,
                'matchedTransactions' => $matchedTransactions->items(),
                'pagination' => PaginationData::fromPaginator($matchedTransactions),
            ]);
        }

        return back()->with('success', 'Koppelregel succesvol aangemaakt.');
    }

    public function updateRule(UpdateImportRuleRequest $request, int $ruleId): RedirectResponse
    {
        $data = $request->validated();
        $rule = ImportRule::findOrFail($ruleId);
        $rule->update($data);

        return back()->with('success', 'Koppelregel succesvol bijgewerkt.');
    }

    public function destroyRule(int $ruleId): RedirectResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $rule->delete();

        return back()->with('success', 'Koppelregel succesvol verwijderd.');
    }

    public function ruleTransactions(Request $request, int $ruleId): JsonResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $transactions = $rule->transactions()
            ->with(['category', 'budget'])
            ->orderByDesc('date')
            ->paginate(100, ['*'], 'page', $request->integer('page', 1));

        $transactionData = $transactions->getCollection()->map(function (Transaction $transaction) {
            return [
                'id' => $transaction->id,
                'date' => $transaction->date?->format('d-m-Y'),
                'description' => $transaction->description,
                'counterparty_iban' => $transaction->counterparty_iban,
                'amount' => (float) $transaction->amount,
                'category_name' => $transaction->category?->name,
                'budget_name' => $transaction->budget?->name,
                'type' => $transaction->type,
            ];
        });

        return response()->json([
            'rule' => $rule,
            'transactions' => $transactionData,
            'pagination' => PaginationData::fromPaginator($transactions),
        ]);
    }

    public function similarRuleTransactions(Request $request, int $ruleId): JsonResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $transactions = $this->importRuleMatcherQuery->forRule($rule, true)
            ->with(['category', 'budget'])
            ->orderByDesc('date')
            ->paginate(100, ['*'], 'page', $request->integer('page', 1));

        $transactionData = $transactions->getCollection()->map(function (Transaction $transaction) {
            return [
                'id' => $transaction->id,
                'date' => $transaction->date?->format('d-m-Y'),
                'description' => $transaction->description,
                'counterparty_iban' => $transaction->counterparty_iban,
                'amount' => (float) $transaction->amount,
                'category_name' => $transaction->category?->name,
                'budget_name' => $transaction->budget?->name,
                'type' => $transaction->type,
            ];
        });

        return response()->json([
            'rule' => $rule,
            'transactions' => $transactionData,
            'pagination' => PaginationData::fromPaginator($transactions),
        ]);
    }

    public function applyRuleToTransaction(Request $request, int $ruleId, int $transactionId): JsonResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $transaction = $this->importRuleMatcherQuery->forRule($rule)
            ->where('id', $transactionId)
            ->firstOrFail();

        $transaction->update($this->ruleAssignmentData($rule));

        return response()->json([
            'transaction' => [
                'id' => $transaction->id,
                'category_id' => $transaction->category_id,
                'budget_id' => $transaction->budget_id,
                'rule_id' => $transaction->rule_id,
            ],
        ]);
    }

    public function applyRule(Request $request, int $ruleId): JsonResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $updated = $this->importRuleMatcherQuery->forRule($rule)->update($this->ruleAssignmentData($rule));

        return response()->json(['updated' => $updated]);
    }

    public function applyRuleToAllMatches(Request $request, int $ruleId): JsonResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $updated = $this->importRuleMatcherQuery->forRule($rule, false)->update($this->ruleAssignmentData($rule));

        return response()->json(['updated' => $updated]);
    }

    public function reassignMatchedRuleBudget(ReassignMatchedRuleBudgetRequest $request, int $ruleId): JsonResponse
    {
        $data = $request->validated();
        $rule = ImportRule::findOrFail($ruleId);
        $rule->update([
            'budget_id' => $data['budget_id'],
        ]);
        $assignmentData = $this->ruleAssignmentData($rule, $data['budget_id']);

        // Werk ook reeds gekoppelde transacties van deze regel bij,
        // zodat de "Transacties voor koppelregel" direct het nieuwe budget toont.
        $rule->transactions()->update($assignmentData);

        $updated = $this->importRuleMatcherQuery->forRule($rule)->update($assignmentData);

        return response()->json(['updated' => $updated]);
    }

    private function ruleAssignmentData(ImportRule $rule, ?int $budgetId = null): array
    {
        $category = Category::find($rule->category_id);

        return [
            'category_id' => $rule->category_id,
            'budget_id' => $budgetId ?? $rule->budget_id,
            'rule_id' => $rule->id,
            'type' => $category?->type,
            'icon' => $category?->icon,
            'color' => $category?->color,
        ];
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);
        $path = $request->file('file')->getRealPath();
        $stats = $this->csvTransactionImporter->import($path);

        return redirect('/imports/transactions')
            ->with('result', $stats)
            ->with('success', 'Transacties succesvol geïmporteerd.');
    }
}
