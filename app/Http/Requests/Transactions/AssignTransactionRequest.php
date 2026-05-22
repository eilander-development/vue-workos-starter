<?php

namespace App\Http\Requests\Transactions;

use Illuminate\Foundation\Http\FormRequest;

class AssignTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categoryId' => ['required', 'integer'],
            'budgetId' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:expense,income,saving'],
            'icon' => ['required', 'string'],
            'color' => ['required', 'string'],
        ];
    }
}
