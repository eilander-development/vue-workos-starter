<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ForecastAssumptions
{
    public function prepare(array $source, array $settings): array
    {
        $months = $source['months'];
        $specs = [];
        $items = collect($source['items'])->keyBy('id');
        foreach ($settings as $setting) {
            $item = $items->get($setting['itemId']);
            if (! $item) {
                throw ValidationException::withMessages(['settings' => 'Een ingestelde begrotingspost bestaat niet meer. Haal actuele gegevens op.']);
            }
            $excluded = $setting['excludedMonths'] ?? [];
            foreach ($excluded as $month) {
                if ($month < $source['startMonth'] || $month > $source['months'][11]['month']) {
                    throw ValidationException::withMessages(['settings' => 'Maanduitsluitingen moeten binnen de twaalf prognosemaanden liggen.']);
                }
            }
            $next = collect($source['boundaries'][$item['id']] ?? [])->first(fn ($month) => $month > $source['startMonth']);
            $latest = $item['latestFixed'] ?? null;
            $spec = [
                'ruleId' => 'basis-'.$item['id'], 'itemId' => $item['id'], 'name' => $item['name'],
                'effectiveMonth' => $source['startMonth'], 'endMonth' => $next,
                'beforeAmounts' => [], 'amounts' => [], 'scheduledMonths' => [], 'firstPayment' => null,
                'label' => 'Laatste vaste bedrag'.($latest && $latest['sourceMonth'] ? ' uit '.$latest['sourceMonth'] : ''),
            ];
            foreach ($months as &$month) {
                $cell = &$month['items'][$item['id']];
                $slot = (int) substr($month['month'], 5, 2);
                $before = $cell['amount'];
                $eligible = $cell['scheduled'];
                $explicit = ($cell['sourceMonth'] ?? '') >= $source['startMonth'];
                if ($setting['basis'] === 'latest' && $latest !== null && $eligible && ! $explicit && (! $next || $month['month'] < $next)) {
                    $cell['amount'] = $latest['amount'];
                    $cell['sourceMonth'] = $latest['sourceMonth'];
                    $cell['basis'] = 'latest';
                }
                if (! array_key_exists($slot, $spec['amounts'])) {
                    $spec['beforeAmounts'][$slot] = $before;
                    $spec['amounts'][$slot] = $cell['amount'];
                    if ($eligible) {
                        $spec['scheduledMonths'][] = $slot;
                    }
                }
                if ($cell['amount'] != $before && ! $spec['firstPayment']) {
                    $spec['firstPayment'] = $month['month'];
                }
                if (! $setting['included'] || in_array($month['month'], $excluded, true)) {
                    $cell['omitted'] = true;
                }
                unset($cell);
            }
            unset($month);
            if ($spec['firstPayment']) {
                $specs[] = $spec;
            }
        }

        return ['months' => $months, 'specs' => $specs];
    }
}
