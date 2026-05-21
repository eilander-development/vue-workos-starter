<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\Transaction;
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
            'rules' => ImportRule::query()->latest()->get(),
            'result' => session('result'),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:iban,description'],
            'match_value' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'budget_id' => ['required', 'integer', 'exists:budgets,id'],
        ]);
        ImportRule::create($data);
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

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);
        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath()));
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
            if (Transaction::where('source_hash', $hash)->exists()) { $stats['duplicates']++; continue; }

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
            ];

            if ($matchedRule) {
                $category = Category::find($matchedRule->category_id);
                $payload['category_id'] = $matchedRule->category_id;
                $payload['budget_id'] = $matchedRule->budget_id;
                $payload['type'] = $category?->type;
                $payload['icon'] = $category?->icon;
                $payload['color'] = $category?->color;
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
