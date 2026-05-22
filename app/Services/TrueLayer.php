<?php

namespace App\Services;

use App\Services\Concerns\ValidatesServiceConfig;
use Illuminate\Support\Facades\Http;

class TrueLayer
{
    use ValidatesServiceConfig;

    public function clientId(): string
    {
        return $this->requiredConfigString('services.truelayer.client_id', 'TRUELAYER_CLIENT_ID ontbreekt in .env.');
    }

    public function clientSecret(): string
    {
        return $this->requiredConfigString('services.truelayer.client_secret', 'TRUELAYER_CLIENT_SECRET ontbreekt in .env.');
    }

    public function redirectUri(): string
    {
        return $this->requiredConfigString('services.truelayer.redirect_uri', 'TRUELAYER_REDIRECT_URI ontbreekt in .env.');
    }

    public function authUrl(string $state): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'accounts balance transactions cards offline_access',
            'state' => $state,
        ]);

        return 'https://auth.truelayer.com/?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://auth.truelayer.com/connect/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('TrueLayer token exchange mislukt: '.$response->body());
        }

        return $response->json();
    }

    public function accounts(string $accessToken): array
    {
        return $this->dataApiGet($accessToken, '/data/v1/accounts');
    }

    public function balances(string $accessToken, string $accountId): array
    {
        return $this->dataApiGet($accessToken, '/data/v1/accounts/'.$accountId.'/balance');
    }

    public function transactions(string $accessToken, string $accountId): array
    {
        return $this->dataApiGet($accessToken, '/data/v1/accounts/'.$accountId.'/transactions');
    }

    protected function dataApiGet(string $accessToken, string $path): array
    {
        $response = Http::withToken($accessToken)
            ->accept('application/json')
            ->get('https://api.truelayer.com'.$path);

        if (! $response->successful()) {
            throw new RuntimeException('TrueLayer API-fout: '.$response->body());
        }

        return $response->json();
    }
}
