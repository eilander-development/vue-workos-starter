<?php

namespace App\Http\Controllers;

use App\Http\Requests\Import\StoreImportRuleRequest;
use App\Http\Requests\Import\UpdateImportRuleRequest;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\Transaction;
use App\Services\Categories;
use App\Services\CsvTransactionImporter;
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
    ) {}

    public function index(): Response
    {
        return Inertia::render('ImportTransactions', [
            'categories' => $this->categoriesService->list(),
            'rules' => ImportRule::query()->latest()->paginate(100),
            'result' => session('result'),
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
            $matchedTransactions = $this->createRuleMatchQuery($rule)
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
        $transactions = $this->createRuleMatchQuery($rule)
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
        $transaction = $this->createRuleMatchQuery($rule)
            ->where('id', $transactionId)
            ->firstOrFail();

        $category = Category::find($rule->category_id);
        $transaction->update([
            'category_id' => $rule->category_id,
            'budget_id' => $rule->budget_id,
            'rule_id' => $rule->id,
            'type' => $category?->type,
            'icon' => $category?->icon,
            'color' => $category?->color,
        ]);

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
        $category = Category::find($rule->category_id);
        $updateData = [
            'category_id' => $rule->category_id,
            'budget_id' => $rule->budget_id,
            'rule_id' => $rule->id,
            'type' => $category?->type,
            'icon' => $category?->icon,
            'color' => $category?->color,
        ];

        $updated = $this->createRuleMatchQuery($rule)->update($updateData);

        return response()->json(['updated' => $updated]);
    }

    private function createRuleMatchQuery(ImportRule $rule)
    {
        return Transaction::query()
            ->whereNull('category_id')
            ->whereNull('rule_id')
            ->when($rule->type === 'iban', function ($query) use ($rule) {
                return $query->whereNotNull('counterparty_iban')
                    ->where('counterparty_iban', 'like', '%'.$rule->match_value.'%');
            }, function ($query) use ($rule) {
                return $query->where('description', 'like', '%'.$rule->match_value.'%');
            });
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
