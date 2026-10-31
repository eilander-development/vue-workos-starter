<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ForecastCalculator
{
    /** The same integer-cent calculation is used by the interactive preview. */
    public function calculate(array $source, array $rules, array $settings = []): array
    {
        $prepared = (new ForecastAssumptions)->prepare($source, $settings);
        $months = $prepared['months'];
        $specs = $prepared['specs'];
        $ordered = collect($rules)->filter(fn ($rule) => $rule['enabled'] ?? true)->sortBy('effectiveMonth', SORT_STRING);

        foreach ($ordered as $rule) {
            $item = collect($source['items'])->firstWhere('id', $rule['itemId']);
            if (! $item || $item['type'] === 'sparen') {
                throw ValidationException::withMessages(['rules' => 'Kies een bestaande inkomsten- of uitgavenpost.']);
            }
            $next = collect($source['boundaries'][$rule['itemId']] ?? [])
                ->first(fn ($month) => $month > $rule['effectiveMonth']);
            $spec = [
                'ruleId' => $rule['id'], 'itemId' => $rule['itemId'], 'name' => $item['name'],
                'effectiveMonth' => $rule['effectiveMonth'], 'endMonth' => $next,
                'beforeAmounts' => [], 'amounts' => [], 'scheduledMonths' => [], 'firstPayment' => null,
            ];

            foreach ($months as &$month) {
                if ($month['month'] < $rule['effectiveMonth']) {
                    continue;
                }
                $slot = (int) substr($month['month'], 5, 2);
                $cell = &$month['items'][$rule['itemId']];
                $before = $cell['amount'];
                $after = $before;
                $active = ! $next || $month['month'] < $next;
                if ($cell['scheduled'] && $active) {
                    $after = $this->adjust($before, $rule['operation'], (float) $rule['value']);
                    if ($after < 0 || $after > 999999999) {
                        throw ValidationException::withMessages(['rules' => "De wijziging van {$item['name']} levert een ongeldig bedrag op. Bedragen moeten tussen €0 en €999.999.999 liggen."]);
                    }
                    $cell['amount'] = $after;
                    $cell['adjusted'] = $after != $before;
                    if ($after !== $before && ! $spec['firstPayment']) {
                        $spec['firstPayment'] = $month['month'];
                    }
                }
                if (! array_key_exists($slot, $spec['amounts'])) {
                    $spec['beforeAmounts'][$slot] = $before;
                    $spec['amounts'][$slot] = $after;
                    if ($cell['scheduled']) {
                        $spec['scheduledMonths'][] = $slot;
                    }
                }
                unset($cell);
            }
            unset($month);
            $specs[] = $spec;
        }

        foreach ($months as &$month) {
            foreach ($month['items'] as &$cell) {
                if ($cell['omitted'] ?? false) {
                    $cell['amount'] = 0;
                    $cell['scheduled'] = false;
                }
            }
            unset($cell);
        }
        unset($month);

        $result = [];
        foreach (array_slice($months, 0, 12) as $index => $month) {
            $result[] = [
                'month' => $month['month'],
                'before' => $this->totals($source['months'][$index]['items'], $source['items']),
                'after' => $this->totals($month['items'], $source['items']),
                'beforeItems' => $source['months'][$index]['items'],
                'afterItems' => $month['items'],
                'warnings' => collect($source['items'])->filter(fn ($item) => isset($source['months'][$index]['items'][$item['id']]['excludedCost']))
                    ->map(fn ($item) => [
                        'itemId' => $item['id'], 'name' => $item['name'], 'group' => $item['group'],
                        ...$source['months'][$index]['items'][$item['id']]['excludedCost'],
                    ])->values()->all(),
            ];
        }

        return ['months' => $result, 'specs' => $specs, 'items' => $source['items'], 'version' => 3, 'settings' => $settings];
    }

    private function adjust(float $amount, string $operation, float $value): float
    {
        $cents = (int) round($amount * 100);

        return match ($operation) {
            'percent' => round($cents * (1 + $value / 100)) / 100,
            'delta' => ($cents + round($value * 100)) / 100,
            'set' => round($value * 100) / 100,
        };
    }

    private function totals(array $cells, array $items): array
    {
        $totals = ['income' => 0, 'expenses' => 0, 'savings' => 0];
        foreach ($items as $item) {
            $key = match ($item['type']) {
                'inkomsten' => 'income', 'sparen' => 'savings', default => 'expenses',
            };
            $totals[$key] += (int) round($cells[$item['id']]['amount'] * 100);
        }
        $totals['free'] = $totals['income'] - $totals['expenses'] - $totals['savings'];

        return array_map(fn ($cents) => $cents / 100, $totals);
    }
}
