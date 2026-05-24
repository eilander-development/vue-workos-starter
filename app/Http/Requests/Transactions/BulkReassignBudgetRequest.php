<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class BulkReassignBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description_prefix' => ['required', 'string', 'min:2'],
            'from_budget_id' => ['required', 'integer'],
            'to_budget_id' => ['required', 'integer', 'different:from_budget_id'],
        ];
    }
}

