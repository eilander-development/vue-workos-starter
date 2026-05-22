<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:iban,description'],
            'match_value' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'budget_id' => ['required', 'integer', 'exists:budgets,id'],
            'transaction_id' => ['sometimes', 'integer', 'exists:transactions,id'],
        ];
    }
}
