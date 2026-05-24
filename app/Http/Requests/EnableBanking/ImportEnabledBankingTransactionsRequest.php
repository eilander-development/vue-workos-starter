<?php

namespace App\Http\Requests\EnableBanking;

use Illuminate\Foundation\Http\FormRequest;

class ImportEnabledBankingTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array'],
            'transactions.*.posted_at' => ['nullable', 'string'],
            'transactions.*.date' => ['nullable', 'string'],
            'transactions.*.description' => ['nullable', 'string'],
            'transactions.*.amount' => ['required', 'numeric'],
            'transactions.*.currency' => ['nullable', 'string'],
            'transactions.*.merchant' => ['nullable', 'string'],
            'transactions.*.raw' => ['nullable', 'array'],
        ];
    }
}

