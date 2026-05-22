<?php

namespace App\Services;

use App\Services\Concerns\ValidatesServiceConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoCardless
{
    use ValidatesServiceConfig;

    public function getApiKey(): string
    {
        return $this->requiredConfigString(
            'services.gocardless.api_key',
            'GoCardless toegangstoken ontbreekt. Voeg GO_CARDLESS_ACCESS_TOKEN toe aan je .env.'
        );
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.gocardless.base_url', 'https://api.gocardless.com'), '/');
    }

    public function fetchMerchant(): array
    {
        $response = $this->request('GET', '/creditors');
        $creditors = $response->json('creditors', []);

        if (! is_array($creditors) || count($creditors) === 0) {
            throw new RuntimeException('Geen GoCardless-crediteur gevonden. Controleer je live access token en account.');
        }

        return $creditors[0];
    }

    protected function request(string $method, string $path, array $data = []): Response
    {
        $apiKey = $this->getApiKey();

        $builder = Http::withToken($apiKey)
            ->withHeaders([
                'GoCardless-Version' => (string) config('services.gocardless.version', '2015-07-06'),
            ])
            ->accept('application/json')
            ->retry(2, 100);

        $url = $this->baseUrl().$path;
        $response = match (strtoupper($method)) {
            'POST' => $builder->post($url, $data),
            'PUT' => $builder->put($url, $data),
            'PATCH' => $builder->patch($url, $data),
            'DELETE' => $builder->delete($url, $data),
            default => $builder->get($url, $data),
        };

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('GoCardless API-fout: '.$message);
        }

        return $response;
    }
}
