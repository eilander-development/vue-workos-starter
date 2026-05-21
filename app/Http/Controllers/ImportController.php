<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ImportTransactions', [
            'categories' => \App\Services\Categories::list(),
            'rules' => ImportRule::query()->latest()->paginate(100),
            'result' => session('result'),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:iban,description'],
            'match_value' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'budget_id' => ['required', 'integer', 'exists:budgets,id'],
            'transaction_id' => ['sometimes', 'integer', 'exists:transactions,id'],
        ]);

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
                'pagination' => [
                    'current_page' => $matchedTransactions->currentPage(),
                    'last_page' => $matchedTransactions->lastPage(),
                    'per_page' => $matchedTransactions->perPage(),
                    'total' => $matchedTransactions->total(),
                    'from' => $matchedTransactions->firstItem(),
                    'to' => $matchedTransactions->lastItem(),
                ],
            ]);
        }

        return back();
    }

    public function updateRule(Request $request, int $ruleId): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:iban,description'],
            'match_value' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'budget_id' => ['required', 'integer', 'exists:budgets,id'],
        ]);
        $rule = ImportRule::findOrFail($ruleId);
        $rule->update($data);
        return back();
    }

    public function destroyRule(int $ruleId): RedirectResponse
    {
        $rule = ImportRule::findOrFail($ruleId);
        $rule->delete();
        return back();
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
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
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
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
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
                    ->where('counterparty_iban', 'like', '%' . $rule->match_value . '%');
            }, function ($query) use ($rule) {
                return $query->where('description', 'like', '%' . $rule->match_value . '%');
            });
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);
        $path = $request->file('file')->getRealPath();
        $lines = array_filter(file($path, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES), fn ($line) => trim($line) !== '');
        $delimiter = isset($lines[0]) && str_contains($lines[0], ';') ? ';' : ',';
        $rows = array_map(fn ($line) => str_getcsv($line, $delimiter), $lines);
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $idx = function (array $names) use ($header) {
            foreach ($names as $name) {
                $found = array_search(mb_strtolower($name), $header, true);
                if ($found !== false) return $found;
            }
            return false;
        };
        $iDate = $idx(['datum']);
        $iAmount = $idx(['bedrag', 'bedrag (eur)']);
        $iDesc = $idx(['naam / omschrijving', 'omschrijving']);
        $iIban = $idx(['tegenrekening']);
        $iAfBij = $idx(['af bij']);
        $rules = ImportRule::all();

        $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0];

        foreach ($rows as $row) {
            if (!is_array($row) || count($row) < 3) { continue; }
            $stats['total']++;
            $date = $iDate !== false ? trim((string) ($row[$iDate] ?? '')) : '';
            $amountRaw = $iAmount !== false ? trim((string) ($row[$iAmount] ?? '0')) : '0';
            $afBij = $iAfBij !== false ? mb_strtolower(trim((string) ($row[$iAfBij] ?? ''))) : null;
            $description = $iDesc !== false ? trim((string) ($row[$iDesc] ?? '')) : '';
            $iban = $iIban !== false ? trim((string) ($row[$iIban] ?? '')) : null;
            $amount = (float) str_replace([','], ['.'], preg_replace('/[^0-9,\.-]/', '', $amountRaw));
            if ($afBij === 'af') {
                $amount = -1 * abs($amount);
            } elseif ($afBij === 'bij') {
                $amount = abs($amount);
            }
            $hash = hash('sha256', implode('|', [$date, $amount, $description, $iban]));
            if (Transaction::where('source_hash', $hash)->exists()) { 
                $stats['duplicates']++; 
                continue; 
            }

            $matchedRule = $rules->first(function (ImportRule $rule) use ($iban, $description) {
                if ($rule->type === 'iban') return $iban && str_contains(mb_strtolower($iban), mb_strtolower($rule->match_value));
                return str_contains(mb_strtolower($description), mb_strtolower($rule->match_value));
            });

            $payload = [
                'source_hash' => $hash,
                'amount' => $amount,
                'description' => $description,
                'counterparty_iban' => $iban,
                'date' => $this->normalizeDate($date),
                'type' => $amount < 0 ? 'expense' : ($amount > 0 ? 'income' : null),
            ];

            if ($matchedRule) {
                $category = Category::find($matchedRule->category_id);
                $payload['category_id'] = $matchedRule->category_id;
                $payload['budget_id'] = $matchedRule->budget_id;
                $payload['type'] = $category?->type ?? $payload['type'];
                $payload['icon'] = $category?->icon;
                $payload['color'] = $category?->color;
                $payload['rule_id'] = $matchedRule->id;
                $stats['matched']++;
            } else {
                $stats['unmatched']++;
            }

            Transaction::create($payload);
            $stats['imported']++;
        }

        return redirect('/imports/transactions')->with('result', $stats);
    }

    private function normalizeDate(?string $date): string
    {
        $value = trim((string) $date);
        if ($value === '') {
            return now()->format('Y-m-d');
        }
        if (preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        }
        return date('Y-m-d', strtotime($value));
    }
}
