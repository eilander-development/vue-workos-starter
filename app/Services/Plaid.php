<?php

namespace App\Services;

use App\Models\PlaidSetting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Plaid
{
    public function createLinkToken(User $user): string
    {
        $response = $this->request('link/token/create', [
            'user' => [
                'client_user_id' => (string) $user->id,
            ],
            'client_name' => config('app.name'),
            'products' => ['transactions'],
            'language' => 'nl',
            'country_codes' => ['NL'],
            'webhook' => config('services.plaid.webhook'),
        ]);

        return $response->json('link_token');
    }

    public function exchangePublicToken(string $publicToken): array
    {
        $response = $this->request('item/public_token/exchange', [
            'public_token' => $publicToken,
        ]);

        return $response->json();
    }

    public function fetchTransactions(string $accessToken, string $startDate, string $endDate, int $count = 100, int $offset = 0): array
    {
        $response = $this->request('transactions/get', [
            'access_token' => $accessToken,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'options' => [
                'count' => $count,
                'offset' => $offset,
            ],
        ]);

        return $response->json();
    }

    public function getItem(string $accessToken): array
    {
        $response = $this->request('item/get', [
            'access_token' => $accessToken,
        ]);

        return $response->json();
    }

    protected function request(string $endpoint, array $payload): Response
    {
        $baseUrl = match (config('services.plaid.environment', 'sandbox')) {
            'production' => 'https://production.plaid.com/',
            'development' => 'https://development.plaid.com/',
            default => 'https://sandbox.plaid.com/',
        };

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->timeout(10)
            ->post($baseUrl . $endpoint, array_merge($this->credentials(), $payload));

        if ($response->failed()) {
            throw new RuntimeException('Plaid API request failed: ' . $response->body());
        }

        return $response;
    }

    protected function credentials(): array
    {
        $settings = PlaidSetting::active();

        return [
            'client_id' => $settings?->client_id ?? config('services.plaid.client_id'),
            'secret' => $settings?->secret ?? config('services.plaid.secret'),
        ];
    }
}
