<?php

namespace App\DTOs;

class TransactionDTO
{
    public ?string $id;
    public ?string $account_id;
    public float $amount;
    public string $currency;
    public ?string $description;
    public ?string $posted_at;
    public ?string $reference;
    public ?string $merchant;
    public ?string $type;
    public ?string $date;
    public ?int $categoryId;
    public ?int $budgetId;
    public ?string $sourceType;
    public array $raw;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->account_id = $data['account_id'] ?? $data['accountId'] ?? null;
        $this->amount = isset($data['amount']) ? (float) $data['amount'] : ((float) ($data['transactionAmount']['amount'] ?? 0));
        $this->currency = $data['currency'] ?? ($data['transactionAmount']['currency'] ?? '');
        $this->description = $data['description'] ?? $data['narrative'] ?? null;
        $this->posted_at = $data['postedAt'] ?? $data['posted_at'] ?? null;
        $this->reference = $data['reference'] ?? null;
        $this->merchant = $data['merchantName'] ?? $data['counterparty'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->date = $data['date'] ?? $data['posted_at'] ?? $data['postedAt'] ?? null;
        $this->categoryId = isset($data['categoryId']) ? (int) $data['categoryId'] : null;
        $this->budgetId = isset($data['budgetId']) ? (int) $data['budgetId'] : null;
        $this->sourceType = $data['sourceType'] ?? $data['source_type'] ?? null;
        $this->raw = $data;
    }

    public static function fromEnableBanking(array $rawTransaction): self
    {
        // 1. Zorg voor een vangnet als de data nóg een niveau dieper genest zit
        $t = $rawTransaction['raw'] ?? $rawTransaction;

        // 2. Haal het rauwe bedrag en de valuta op uit het object
        $rawAmount = $t['transaction_amount']['amount'] ?? 0.0;
        $currency = $t['transaction_amount']['currency'] ?? 'EUR';
        
        // Converteer de string (bijv. "0.50") naar een zuivere float
        $amount = (float)$rawAmount;

        // 3. CRUCIAAL VOOR PS2: Als het een afschrijving (DBIT) is, maken we het bedrag negatief
        $indicator = $t['credit_debit_indicator'] ?? 'CRDT';
        if ($indicator === 'DBIT' && $amount > 0) {
            $amount = -$amount;
        }

        // 4. Bepaal de omschrijving (pakt de eerste regel uit de remittance array, of valt terug op bank_transaction_code)
        $remittance = $t['remittance_information'] ?? [];
        $description = !empty($remittance) && is_array($remittance) 
            ? $remittance[0] 
            : ($t['bank_transaction_code']['description'] ?? 'Banktransactie');

        // 5. Bepaal de naam van de tegenpartij (Creditor bij afschrijving, Debtor bij bijschrijving)
        $merchant = $t['creditor']['name'] ?? ($t['debtor']['name'] ?? null);

        // 6. Bouw en retourneer de DTO met de exacte veldnamen die je Vue-template verwacht
        return new self([
            'id'          => $t['entry_reference'] ?? ($t['transaction_id'] ?? bin2hex(random_bytes(8))),
            'account_id'  => $t['debtor_account']['iban'] ?? null,
            'amount'      => $amount,
            'currency'    => $currency,
            'description' => $description,
            'posted_at'   => $t['booking_date'] ?? ($t['transaction_date'] ?? date('Y-m-d')),
            'reference'   => $t['entry_reference'] ?? null,
            'merchant'    => $merchant,
            'raw'         => $rawTransaction // Behoud de rauwe data voor debuggen
        ]);
    }

    public static function fromModel(\App\Models\Transaction $transaction): self
    {
        return new self([
            'id' => $transaction->id,
            'account_id' => $transaction->account_id ?? null,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency ?? 'EUR',
            'description' => $transaction->description,
            'posted_at' => optional($transaction->date)->format('Y-m-d'),
            'date' => optional($transaction->date)->format('Y-m-d'),
            'reference' => $transaction->reference ?? null,
            'merchant' => $transaction->merchant ?? null,
            'type' => $transaction->type,
            'categoryId' => $transaction->category_id,
            'budgetId' => $transaction->budget_id,
            'sourceType' => $transaction->source_type,
            'raw' => $transaction->toArray(),
        ]);
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id ?? null,
            'account_id'  => $this->account_id ?? null,
            'amount'      => $this->amount ?? 0,
            'currency'    => $this->currency ?? 'EUR',
            'description' => $this->description ?? 'Geen omschrijving',
            'posted_at'   => $this->posted_at ?? '-',
            'date'        => $this->date ?? $this->posted_at ?? null,
            'reference'   => $this->reference ?? null,
            'merchant'    => $this->merchant ?? null,
            'type'        => $this->type ?? null,
            'categoryId'  => $this->categoryId,
            'budgetId'    => $this->budgetId,
            'sourceType'  => $this->sourceType,
            'raw'         => $this->raw ?? []
        ];
    }
}
