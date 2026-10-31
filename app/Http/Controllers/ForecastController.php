<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use App\Services\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ForecastController extends Controller
{
    public function __construct(private ForecastService $forecasts) {}

    public function index(): JsonResponse
    {
        abort_unless(Schema::connection('catalog')->hasTable('forecasts'), 503,
            'Prognoses is nog niet beschikbaar. De database moet eerst worden bijgewerkt.');

        return response()->json([
            'minMonth' => $this->forecasts->firstFutureMonth(),
            'forecasts' => Forecast::query()->orderByDesc('id')->get()->map(fn ($f) => $this->forecasts->payload($f, false)),
        ]);
    }

    public function source(Request $request): JsonResponse
    {
        $data = $request->validate(['startMonth' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before:2200-01']]);

        return response()->json($this->forecasts->source($data['startMonth']));
    }

    public function show(Forecast $forecast): JsonResponse
    {
        return response()->json($this->forecasts->payload($forecast));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->forecasts->payload($this->forecasts->save($this->validated($request), $request->user()?->name)), 201);
    }

    public function update(Request $request, Forecast $forecast): JsonResponse
    {
        $data = $this->validated($request, true);

        return response()->json($this->forecasts->payload($this->forecasts->save($data, $request->user()?->name, $forecast)));
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:5000'],
            'startMonth' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before:2200-01'],
            'sourceHash' => ['required', 'string', 'size:64'], 'copiedFrom' => ['nullable', 'uuid'],
            'revision' => [$updating ? 'required' : 'sometimes', 'string', 'size:64'],
            'rules' => ['present', 'array', 'max:50'],
            'rules.*.id' => ['required', 'string', 'distinct', 'max:100'],
            'rules.*.itemId' => ['required', 'string', 'max:100'],
            'rules.*.effectiveMonth' => ['required', 'date_format:Y-m'],
            'rules.*.operation' => ['required', Rule::in(['delta', 'percent', 'set'])],
            'rules.*.value' => ['required', 'numeric', 'decimal:0,2', 'between:-999999999,999999999'],
            'rules.*.enabled' => ['required', 'boolean'],
            'settings' => ['sometimes', 'array', 'max:500'],
            'settings.*.itemId' => ['required', 'string', 'distinct', 'max:100'],
            'settings.*.basis' => ['required', Rule::in(['profile', 'latest'])],
            'settings.*.included' => ['required', 'boolean'],
            'settings.*.excludedMonths' => ['present', 'array', 'max:12'],
            'settings.*.excludedMonths.*' => ['required', 'date_format:Y-m'],
        ]);
    }

    public function apply(Request $request, Forecast $forecast): JsonResponse
    {
        $data = $request->validate(['revision' => ['required', 'string', 'size:64']]);

        return response()->json($this->forecasts->payload($this->forecasts->apply($forecast, $data['revision'])));
    }

    public function revert(Request $request, Forecast $forecast): JsonResponse
    {
        $data = $request->validate(['fromMonth' => ['required', 'date_format:Y-m']]);

        return response()->json($this->forecasts->payload($this->forecasts->revert($forecast, $data['fromMonth'])));
    }

    public function history(string $itemId): JsonResponse
    {
        return response()->json($this->forecasts->history($itemId));
    }
}
