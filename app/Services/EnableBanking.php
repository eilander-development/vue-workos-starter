<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
use GuzzleHttp\Client;
use RuntimeException;

class EnableBanking
{
    protected Client $http;
    protected string $baseUri;
    protected string $applicationId;
    protected ?string $keyPath;
    protected string $scope;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client();
        $config = config('services.enablebanking', []);
        $this->baseUri = rtrim($config['base_uri'] ?? 'https://api.enablebanking.com', '/');
        $this->applicationId = strval($config['application_id'] ?? env('ENABLE_BANKING_APPLICATION_ID', ''));
        $this->keyPath = $config['keypath'] ?? env('ENABLE_BANKING_KEYPATH');
        $this->scope = strval($config['scope'] ?? env('ENABLE_BANKING_SCOPE', 'accounts balances transactions'));
    }

    protected function assertConfigured(): void
    {
        if (!$this->applicationId || !$this->keyPath) {
            throw new RuntimeException('EnableBanking configuration is incomplete.');
        }
    }

    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function generateJwt(): string
    {
        $this->assertConfigured();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->applicationId
        ];

        $now = time();
        $payload = [
            'iss' => 'enablebanking.com',
            'sub' => $this->applicationId,
            'aud' => 'api.enablebanking.com',
            'iat' => $now,
            'exp' => $now + 3600,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $base = self::base64UrlEncode(json_encode($header)) . '.' . self::base64UrlEncode(json_encode($payload));
        $privateKey = openssl_pkey_get_private(file_get_contents($this->keyPath));
        $signature = '';
        openssl_sign($base, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $base . '.' . self::base64UrlEncode($signature);
    }

    /**
     * Wissel de gekregen code om voor een sessie. 
     * Deze response bevat direct de live accounts!
     */
    public function authorizeSession(string $code): array
    {
        $jwt = $this->generateJwt();

        $response = $this->http->post("{$this->baseUri}/sessions", [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'json' => [
                'code' => $code
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Haal sessie-informatie op via het session_id (conform GET /sessions/{session_id})
     */
    public function getSessionData(string $sessionId): array
    {
        $jwt = $this->generateJwt();

        $response = $this->http->get("{$this->baseUri}/sessions/{$sessionId}", [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Accept'        => 'application/json',
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Start een nieuwe banksessie en retourneert de redirect URL én de gegenereerde state.
     */
    public function initAuth(string $redirectUri, string $bankName = 'ING', string $country = 'NL'): array
    {
        $jwt = $this->generateJwt();
        $state = bin2hex(random_bytes(16)); // Genereer de unieke state hier

        $response = $this->http->post("{$this->baseUri}/auth", [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'json' => [
                'access' => [
                    'valid_until' => date('Y-m-d\TH:i:s\Z', strtotime('+90 days'))
                ],
                'aspsp' => [
                    'name'    => $bankName,
                    'country' => $country
                ],
                'state'        => $state, // Stuur de state naar de bank
                'redirect_url' => $redirectUri,
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        // Voeg de gegenereerde state toe aan de return array zodat de controller erbij kan
        $data['generated_state'] = $state;

        return $data;
    }

    /**
     * Haal het actuele live saldo op van een specifieke rekening.
     */
    public function getBalances(string $accountId): array
    {
        $jwt = $this->generateJwt();

        $response = $this->http->get("{$this->baseUri}/accounts/{$accountId}/balances", [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Accept'        => 'application/json',
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Haal alle gekoppelde bankrekeningen op via het unieke Session ID.
     */
    public function getAccounts(string $sessionId): array
    {
        $jwt = $this->generateJwt();

        // FIX: In productie MOET je de accounts ophalen via het specifieke sessie-ID
        $response = $this->http->get("{$this->baseUri}/sessions/{$sessionId}/accounts", [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Accept'        => 'application/json',
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Haal transacties op voor een specifieke rekening.
     */
    public function getTransactions(string $accountId, array $queryParams = []): array
    {
        $jwt = $this->generateJwt();

        $response = $this->http->get("{$this->baseUri}/accounts/{$accountId}/transactions", [
            'headers' => [
                'Authorization' => "Bearer {$jwt}",
                'Accept'        => 'application/json',
            ],
            'query' => $queryParams
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getAllTransactions(string $accountId, array $queryParams = []): array
    {
        $items = [];
        $params = $queryParams;
        $continuationKey = null;

        do {
            if ($continuationKey) {
                $params['continuation_key'] = $continuationKey;
            } else {
                unset($params['continuation_key']);
            }

            $transactionsData = $this->getTransactions($accountId, $params);
            $list = $transactionsData['transactions'] ?? [];

            foreach ($list as $t) {
                $items[] = TransactionDTO::fromEnableBanking($t)->toArray();
            }

            $continuationKey = $transactionsData['continuation_key'] ?? null;
        } while ($continuationKey !== null);

        return $items;
    }
}
