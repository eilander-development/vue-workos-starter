<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\EnableBankingSession;

class EnableBankingSessionStore
{
    public function current(): ?EnableBankingSession
    {
        $this->markExpiredSessions();
        $this->adoptHttpSession();

        return EnableBankingSession::query()->usable()->latest('id')->first();
    }

    public function latest(): ?EnableBankingSession
    {
        $this->adoptHttpSession();

        return EnableBankingSession::query()->latest('id')->first();
    }

    public function sessionId(): ?string
    {
        return $this->current()?->session_id;
    }

    public function remember(string $sessionId, array $sessionData = []): EnableBankingSession
    {
        EnableBankingSession::query()
            ->where('session_id', '!=', $sessionId)
            ->where('status', 'authorized')
            ->update(['status' => 'replaced']);

        $accounts = $this->extractAccounts($sessionData);
        $validUntil = EnableBankingSession::validUntilFromPayload($sessionData);

        $attributes = [
            'status' => 'authorized',
        ];

        $aspspName = data_get($sessionData, 'aspsp.name');
        if (is_string($aspspName) && $aspspName !== '') {
            $attributes['aspsp_name'] = $aspspName;
        }

        $aspspCountry = data_get($sessionData, 'aspsp.country');
        if (is_string($aspspCountry) && $aspspCountry !== '') {
            $attributes['aspsp_country'] = $aspspCountry;
        }

        if ($validUntil) {
            $attributes['valid_until'] = $validUntil;
        }

        if ($accounts !== []) {
            $attributes['accounts'] = $accounts;
        }

        $session = EnableBankingSession::query()->updateOrCreate(
            ['session_id' => $sessionId],
            $attributes
        );

        session([
            'eb_session_id' => $sessionId,
            'eb_cached_accounts' => $session->accounts ?? [],
        ]);

        return $session->refresh();
    }

    public function markExpired(string $sessionId): void
    {
        EnableBankingSession::query()
            ->where('session_id', $sessionId)
            ->update(['status' => 'expired']);

        session()->forget(['eb_session_id', 'eb_cached_accounts']);
    }

    public function disconnect(): void
    {
        EnableBankingSession::query()
            ->where('status', 'authorized')
            ->update(['status' => 'disconnected']);

        BankAccount::query()->update(['status' => 'disconnected']);

        session()->forget(['eb_session_id', 'eb_cached_accounts', 'eb_oauth_state']);
    }

    private function markExpiredSessions(): void
    {
        EnableBankingSession::query()
            ->where('status', 'authorized')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now())
            ->update(['status' => 'expired']);
    }

    private function adoptHttpSession(): void
    {
        if (EnableBankingSession::query()->usable()->exists()) {
            return;
        }

        $sessionId = session('eb_session_id');
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $this->remember($sessionId, [
            'accounts' => session('eb_cached_accounts') ?? [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractAccounts(array $sessionData): array
    {
        foreach (['accounts_data', 'accounts'] as $key) {
            $list = $sessionData[$key] ?? null;
            if (! is_array($list) || $list === [] || ! is_array($list[0] ?? null)) {
                continue;
            }

            return $list;
        }

        return [];
    }
}
