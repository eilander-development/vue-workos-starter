<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ForecastAssumptions
{
    public function normalizeSettings(array $source, array $settings): array
    {
        if (($source['version'] ?? 0) < 5) {
            return $settings;
        }

        return array_map(function (array $setting) {
            // Older bulk 'latest amount' actions also wrote a false cadence.
            // Only a deliberate cadence choice may override the current source.
            if (($setting['basis'] ?? '') === 'latest' && ($setting['everyMonth'] ?? null) === false
                && ! ($setting['rememberBasis'] ?? false) && ! array_key_exists('cadenceOverride', $setting)) {
                unset($setting['everyMonth']);
            }

            return $setting;
        }, $settings);
    }

    public function prepare(array $source, array $settings): array
    {
        $settings = $this->normalizeSettings($source, $settings);
        $months = $source['months'];
        $specs = [];
        $items = collect($source['items'])->keyBy('id');
        foreach ($settings as $setting) {
            if (! $items->has($setting['itemId'])) {
                throw ValidationException::withMessages(['settings' => 'Een ingestelde begrotingspost bestaat niet meer. Haal actuele gegevens op.']);
            }
        }
        $overrides = collect($settings)->keyBy('itemId');
        foreach ($source['items'] as $item) {
            $setting = array_replace([
                'itemId' => $item['id'], 'basis' => $item['forecastBasis'] ?? (($source['version'] ?? 3) >= 4 ? 'latest' : 'profile'),
                'everyMonth' => $item['forecastMonthly'] ?? false, 'included' => true, 'excludedMonths' => [],
                'costMode' => 'auto',
            ], $overrides->get($item['id'], []));
            if ($setting['basis'] === 'profile' && ! array_key_exists('everyMonth', $overrides->get($item['id'], []))) {
                $setting['everyMonth'] = false;
            }
            $excluded = $setting['excludedMonths'] ?? [];
            foreach ($excluded as $month) {
                if ($month < $source['startMonth'] || $month > $source['months'][11]['month']) {
                    throw ValidationException::withMessages(['settings' => 'Maanduitsluitingen moeten binnen de twaalf prognosemaanden liggen.']);
                }
            }
            $next = collect($source['boundaries'][$item['id']] ?? [])->first(fn ($month) => $month > $source['startMonth']);
            $latest = $item['latestFixed'] ?? ($setting['costMode'] === 'fixed' ? ($item['latestUnfiltered'] ?? null) : null);
            $spec = [
                'ruleId' => 'basis-'.$item['id'], 'itemId' => $item['id'], 'name' => $item['name'],
                'effectiveMonth' => $source['startMonth'], 'endMonth' => $next,
                'beforeAmounts' => [], 'amounts' => [], 'scheduledMonths' => [], 'firstPayment' => null,
                'label' => 'Laatste vaste bedrag'.($latest && $latest['sourceMonth'] ? ' uit '.$latest['sourceMonth'] : ''),
            ];
            foreach ($months as &$month) {
                $cell = &$month['items'][$item['id']];
                if ($item['type'] === 'uitgaven' && $setting['costMode'] === 'fixed') {
                    $cell['amount'] = $cell['rawAmount'] ?? $cell['excludedCost']['amount'] ?? $cell['amount'];
                    $cell['scheduled'] = $cell['rawScheduled'] ?? $cell['amount'] > 0;
                    unset($cell['excludedCost']);
                } elseif ($item['type'] === 'uitgaven' && $setting['costMode'] === 'extra') {
                    $cell['analysisExtra'] = ! isset($cell['excludedCost']);
                    if ($cell['amount'] > 0) {
                        $cell['excludedCost'] = [
                            'amount' => $cell['amount'], 'kind' => ($cell['sourceMonth'] ?? '') === $month['month'] || ($cell['datedChange'] ?? false) ? 'planned' : 'historical',
                            'sourceMonth' => $cell['sourceMonth'] ?? null, 'reason' => 'marked_extra', 'entries' => [],
                        ];
                    }
                }
                $slot = (int) substr($month['month'], 5, 2);
                $before = $cell['amount'];
                $eligible = $cell['scheduled'] || ($setting['everyMonth'] && ! isset($cell['excludedCost']));
                $explicit = ($cell['sourceMonth'] ?? '') >= $source['startMonth'];
                if ($setting['basis'] === 'latest' && $latest !== null && $eligible && ! $explicit && (! $next || $month['month'] < $next)) {
                    $cell['amount'] = $latest['amount'];
                    $cell['sourceMonth'] = $latest['sourceMonth'];
                    $cell['basis'] = 'latest';
                    $cell['scheduled'] = true;
                } elseif ($setting['everyMonth'] && $latest !== null && ! $cell['scheduled'] && ! isset($cell['excludedCost']) && ! $explicit && (! $next || $month['month'] < $next)) {
                    $cell['amount'] = $latest['amount'];
                    $cell['sourceMonth'] = $latest['sourceMonth'];
                    $cell['basis'] = 'latest';
                    $cell['scheduled'] = true;
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
                if (! $setting['included'] || in_array($month['month'], $excluded, true) || ($cell['analysisExtra'] ?? false)) {
                    $cell['omitted'] = true;
                }
                unset($cell);
            }
            unset($month);
            if ($spec['firstPayment']) {
                $specs[] = $spec;
            }
        }

        return ['months' => $months, 'specs' => $specs, 'settings' => $settings];
    }
}
