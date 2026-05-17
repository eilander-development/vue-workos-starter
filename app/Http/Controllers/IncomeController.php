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
        $categories = \App\Services\Categories::list();

        return Inertia::render('Income', [
            'categories' => $categories,
            'selected' => collect($categories)->firstWhere('slug', $category) ?? $categories[1],
        ]);
    }
}
