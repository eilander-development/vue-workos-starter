<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoCardless
{
    public function getApiKey(): string
    {
        $apiKey = config('services.gocardless.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('GoCardless toegangstoken ontbreekt. Voeg GO_CARDLESS_ACCESS_TOKEN toe aan je .env.');
        }

        return trim($apiKey);
    }

    public function environment(): string
    {
        $environment = config('services.gocardless.environment');

        if (is_string($environment) && trim($environment) !== '') {
            return trim($environment);
        }

        $apiKey = $this->getApiKey();

        return str_starts_with($apiKey, 'live_') ? 'production' : 'sandbox';
    }

    public function baseUrl(): string
    {
        return match ($this->environment()) {
            'production' => 'https://api.gocardless.com',
            default => 'https://api-sandbox.gocardless.com',
        };
    }

    public function fetchMerchant(): array
    {
        $response = $this->request('GET', '/merchants');

        $merchants = $response->json('merchants', []);

        if (! is_array($merchants) || count($merchants) === 0) {
            throw new RuntimeException('Geen GoCardless-merchant gevonden. Controleer je API-key.');
        }

        return $merchants[0];
    }

    protected function request(string $method, string $path, array $data = []): Response
    {
        $apiKey = $this->getApiKey();

        $builder = Http::withBasicAuth($apiKey, '')
            ->accept('application/json')
            ->retry(2, 100);

        $url = $this->baseUrl() . $path;
        $response = match (strtoupper($method)) {
            'POST' => $builder->post($url, $data),
            'PUT' => $builder->put($url, $data),
            'PATCH' => $builder->patch($url, $data),
            'DELETE' => $builder->delete($url, $data),
            default => $builder->get($url, $data),
        };

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('GoCardless API-fout: ' . $message);
        }

        return $response;
    }
}
