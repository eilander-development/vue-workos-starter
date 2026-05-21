<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\WorkOS\Http\Requests\AuthKitAccountDeletionRequest;

class IncomeController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function index(Request $request, $category = null): Response
    {
        $categories = array_values(\App\Services\Categories::list('income'));
        $selected = collect($categories)->firstWhere('slug', $category) ?? ($categories[0] ?? null);

        return Inertia::render('Income', [
            'categories' => $categories,
            'selected' => $selected,
        ]);
    }
}
