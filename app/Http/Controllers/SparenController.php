<?php

namespace App\Http\Controllers;

use App\Services\EnableBanking;
use App\Services\EnableBankingDataService;
use App\Services\EnabledBankingTransactionImporter;
use App\Services\SparenStateService;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SparenController extends Controller
{
    public function __construct(
        protected SparenStateService $state,
        protected EnableBanking $enableBanking,
        protected EnableBankingDataService $enableBankingData,
        protected EnabledBankingTransactionImporter $importer,
    ) {}

    public function app()
    {
        return view('sparen');
    }

    public function state(): JsonResponse
    {
        return response()->json($this->state->build());
    }

    public function persist(Request $request): JsonResponse
    {
        $this->state->persist($request->all());

        return response()->json($this->state->build());
    }

    public function persistBudgetItem(Request $request, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'group' => ['required', 'string'],
            'estimated' => ['nullable', 'numeric'],
            'actual' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'monthId' => ['nullable', 'string'],
            'year' => ['nullable', 'integer'],
            'monthlyAmounts' => ['nullable', 'array'],
            'monthlyAmounts.*' => ['numeric'],
            'monthlyEntries' => ['nullable', 'array'],
            'monthlyEntries.*' => ['nullable', 'array'],
            'monthlyEntries.*.*.id' => ['nullable', 'string'],
            'monthlyEntries.*.*.description' => ['nullable', 'string'],
            'monthlyEntries.*.*.amount' => ['nullable', 'numeric'],
        ]);

        $this->state->persistBudgetItem(['id' => $itemId, ...$data]);

        return response()->json($this->state->build());
    }

    public function destroyBudgetItem(string $itemId): JsonResponse
    {
        if (! $this->state->deleteBudgetItem($itemId)) {
            return response()->json(['error' => 'Begrotingspost niet gevonden'], 404);
        }

        return response()->json($this->state->build());
    }

    public function persistTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.date' => ['nullable', 'date'],
            'items.*.time' => ['nullable', 'string'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.amount' => ['nullable', 'numeric'],
            'items.*.type' => ['nullable', 'string'],
            'items.*.categoryGroup' => ['nullable', 'string'],
            'items.*.budgetItemId' => ['nullable', 'string'],
            'items.*.accountIban' => ['nullable', 'string'],
            'items.*.counterparty' => ['nullable', 'string'],
            'items.*.isPending' => ['nullable', 'boolean'],
            'items.*.matchedRuleId' => ['nullable', 'string'],
            'items.*.source' => ['nullable', 'string'],
        ]);

        $this->state->persistTransactions($data['items']);

        return response()->json(['ok' => true]);
    }

    public function persistTransaction(Request $request, string $txId): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric'],
            'type' => ['nullable', 'string'],
            'categoryGroup' => ['nullable', 'string'],
            'budgetItemId' => ['nullable', 'string'],
            'accountIban' => ['nullable', 'string'],
            'counterparty' => ['nullable', 'string'],
            'isPending' => ['nullable', 'boolean'],
            'matchedRuleId' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
        ]);

        $this->state->persistTransactions([['id' => $txId, ...$data]]);

        return response()->json(['ok' => true]);
    }

    public function destroyTransaction(string $txId): JsonResponse
    {
        if (! $this->state->deleteTransaction($txId)) {
            return response()->json(['error' => 'Transactie niet gevonden'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function persistRule(Request $request, string $ruleId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'keyword' => ['required', 'string'],
            'matchField' => ['nullable', 'string'],
            'targetGroup' => ['nullable', 'string'],
            'targetBudgetItemId' => ['nullable', 'string'],
            'targetType' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $this->state->persistRule(['id' => $ruleId, ...$data]);

        return response()->json(['ok' => true]);
    }

    public function destroyRule(string $ruleId): JsonResponse
    {
        if (! $this->state->deleteRule($ruleId)) {
            return response()->json(['error' => 'Koppelregel niet gevonden'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function persistCategoryRecord(Request $request, string $categoryId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'type' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'color' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'isDefault' => ['nullable', 'boolean'],
        ]);

        $this->state->persistCategory(['id' => $categoryId, ...$data]);

        return response()->json(['ok' => true]);
    }

    public function destroyCategoryRecord(string $categoryId): JsonResponse
    {
        if (! $this->state->deleteCategory($categoryId)) {
            return response()->json(['error' => 'Rubriek niet gevonden'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function persistSavingsGoal(Request $request, string $goalId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'accountIban' => ['nullable', 'string'],
            'bankName' => ['nullable', 'string'],
            'targetAmount' => ['nullable', 'numeric'],
            'initialAmount' => ['nullable', 'numeric'],
            'monthlyContribution' => ['nullable', 'numeric'],
            'color' => ['nullable', 'string'],
            'iconName' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'categoryBudgetItemId' => ['nullable', 'string'],
            'kind' => ['nullable', 'string', 'in:goal,pot'],
        ]);

        $this->state->persistSavingsGoal(['id' => $goalId, ...$data]);

        return response()->json(['ok' => true]);
    }

    public function destroySavingsGoal(string $goalId): JsonResponse
    {
        if (! $this->state->deleteSavingsGoal($goalId)) {
            return response()->json(['error' => 'Spaardoel niet gevonden'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function syncBank(): JsonResponse
    {
        $sessionId = session('eb_session_id');
        if (! $sessionId) {
            $redirectUri = config('services.enablebanking.redirect_uri') ?? url('/enabled-banking/auth_redirect');
            $auth = $this->enableBanking->initAuth($redirectUri, 'ING', 'NL');
            if (isset($auth['generated_state'])) {
                session(['eb_oauth_state' => $auth['generated_state']]);
            }

            return response()->json([
                'needsConnect' => true,
                'url' => $auth['url'] ?? null,
            ], 409);
        }

        $list = session('eb_cached_accounts');
        if (! $list) {
            $sessionData = $this->enableBanking->getSessionData($sessionId);
            $list = $sessionData['accounts_data'] ?? ($sessionData['accounts'] ?? []);
            session(['eb_cached_accounts' => $list]);
        }

        $mapped = $this->enableBankingData->mapAccountBalances(is_array($list) ? $list : [], $this->enableBanking);
        $today = Carbon::now('Europe/Amsterdam')->toDateString();
        $imported = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0];

        foreach ($mapped as $index => $account) {
            $iban = data_get($account, 'raw.account_id.iban')
                ?? data_get($account, 'raw.iban')
                ?? null;
            $uid = $account['accountId'] ?? null;
            $record = BankAccount::query()->updateOrCreate(
                ['key' => $uid ? 'eb-'.$uid : 'eb-checking-'.$index],
                [
                    'name' => $account['name'] ?? 'ING Betaalrekening',
                    'bank_name' => data_get($account, 'raw.servicer.name', 'ING Bank'),
                    'iban' => $iban,
                    'type' => 'checking',
                    'balance' => $account['balance'] ?? 0,
                    'available_balance' => $account['available'] ?? ($account['balance'] ?? 0),
                    'currency' => $account['currency'] ?? 'EUR',
                    'status' => 'connected',
                    'enable_banking_uid' => $uid,
                    'last_synced_at' => now(),
                ]
            );

            if ($record->sync_count_date?->toDateString() !== $today) {
                $record->sync_count_today = 0;
                $record->sync_count_date = $today;
            }
            $record->sync_count_today++;
            $record->save();

            if (! $uid) {
                continue;
            }

            $transactions = $this->enableBanking->getAllTransactions($uid, [
                'date_from' => now()->subDays(90)->format('Y-m-d'),
            ]);

            foreach ($transactions as &$row) {
                $row['account_iban'] = $iban;
            }
            unset($row);

            $this->dumpRawTransactions($iban, $transactions);

            $stats = $this->importer->import($transactions);
            foreach ($stats as $key => $value) {
                $imported[$key] = ($imported[$key] ?? 0) + $value;
            }
        }

        return response()->json([
            'needsConnect' => false,
            'imported' => $imported,
            'state' => $this->state->build(),
        ]);
    }

    /**
     * Bewaar lokaal een steekproef van de ruwe bankrespons, zodat te controleren is
     * welke velden ING meestuurt (onder andere voor de boekingstijd).
     */
    private function dumpRawTransactions(?string $iban, array $transactions): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $sample = array_map(fn (array $row) => [
            'date' => $row['date'] ?? null,
            'time' => $row['time'] ?? null,
            'description' => $row['description'] ?? null,
            'raw' => $row['raw'] ?? null,
        ], array_slice($transactions, 0, 15));

        $payload = [
            'synced_at' => now()->toDateTimeString(),
            'iban' => $iban,
            'total' => count($transactions),
            'with_time' => count(array_filter($transactions, fn (array $row) => ! empty($row['time']))),
            'sample' => $sample,
        ];

        file_put_contents(
            storage_path('app/eb-last-sync.json'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
