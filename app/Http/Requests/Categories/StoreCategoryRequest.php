<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:expense,income,saving,uncategorized'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'budgets' => ['nullable', 'array'],
            'budgets.*.name' => ['nullable', 'string', 'max:255'],
            'budgets.*.budget' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
