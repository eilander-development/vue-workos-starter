<?php

namespace App\DTOs;

use App\Support\BankTransactionTime;

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
    public ?string $counterpart_iban;
    public ?string $type;
    public ?string $date;
    public ?string $time;
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
        $this->counterpart_iban = $data['counterpart_iban'] ?? $data['counterparty_iban'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->date = $data['date'] ?? $data['posted_at'] ?? $data['postedAt'] ?? null;
        $this->time = $data['time'] ?? BankTransactionTime::extract($data);
        $this->categoryId = isset($data['categoryId']) ? (int) $data['categoryId'] : null;
        $this->budgetId = isset($data['budgetId']) ? (int) $data['budgetId'] : null;
        $this->sourceType = $data['sourceType'] ?? $data['source_type'] ?? null;
        $this->raw = $data;
    }

    public static function fromEnableBanking(array $rawTransaction): self
    {
        $t = $rawTransaction['raw'] ?? $rawTransaction;

        $rawAmount = $t['transaction_amount']['amount'] ?? 0.0;
        $currency = $t['transaction_amount']['currency'] ?? 'EUR';
        $amount = (float) $rawAmount;

        $indicator = $t['credit_debit_indicator'] ?? 'CRDT';
        if ($indicator === 'DBIT' && $amount > 0) {
            $amount = -$amount;
        }

        $remittance = $t['remittance_information'] ?? [];
        $description = $t['bank_transaction_code']['description'] ?? 'Banktransactie';
        $remittanceIban = null;
        $ignorePatterns = [
            '/^Datum:\s*/iu',
            '/^Valutadatum:\s*/iu',
            '/^Transactie:\s*/iu',
            '/^Kaartnr:\s*/iu',
            '/^Kenmerk:\s*/iu',
            '/^Datum\/Tijd:\s*/iu',
            '/^Machtiging ID:\s*/iu',
            '/^Incassant ID:\s*/iu',
            '/^Pasvolgnr:\s*/iu',
        ];
        $cleanPatterns = [
            '/^Naam:\s*/iu',
            '/^Omschrijving:\s*/iu',
        ];
        $descriptionParts = [];

        if (is_array($remittance)) {
            foreach ($remittance as $line) {
                if (! is_string($line)) {
                    continue;
                }

                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                if (preg_match('/^IBAN:\s*([A-Z0-9]+)/iu', $trimmed, $matches)) {
                    $remittanceIban = trim($matches[1]);
                    continue;
                }

                $shouldIgnore = false;
                foreach ($ignorePatterns as $pattern) {
                    if (preg_match($pattern, $trimmed) === 1) {
                        $shouldIgnore = true;
                        break;
                    }
                }
                if ($shouldIgnore) {
                    continue;
                }

                $cleaned = $trimmed;
                foreach ($cleanPatterns as $pattern) {
                    $cleaned = preg_replace($pattern, '', $cleaned) ?? $cleaned;
                }
                $cleaned = trim($cleaned);
                if ($cleaned !== '') {
                    $descriptionParts[] = $cleaned;
                }
            }
        } elseif (is_string($remittance) && trim($remittance) !== '') {
            $descriptionParts[] = trim($remittance);
        }

        if (! empty($descriptionParts)) {
            $description = implode(' ', $descriptionParts);
        }

        $merchant = $t['creditor']['name'] ?? ($t['debtor']['name'] ?? null);
        $counterpartIban = $remittanceIban ?? $t['creditor_account']['iban'] ?? $t['debtor_account']['iban'] ?? null;

        return new self([
            'id' => $t['entry_reference'] ?? ($t['transaction_id'] ?? bin2hex(random_bytes(8))),
            'account_id' => $t['debtor_account']['iban'] ?? null,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'posted_at' => $t['booking_date'] ?? ($t['transaction_date'] ?? date('Y-m-d')),
            'time' => BankTransactionTime::extract($t),
            'reference' => $t['entry_reference'] ?? null,
            'merchant' => $merchant,
            'counterpart_iban' => $counterpartIban,
            'raw' => $rawTransaction,
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
            'counterpart_iban' => $transaction->counterparty_iban ?? null,
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
            'id' => $this->id ?? null,
            'account_id' => $this->account_id ?? null,
            'amount' => $this->amount ?? 0,
            'currency' => $this->currency ?? 'EUR',
            'description' => $this->description ?? 'Geen omschrijving',
            'posted_at' => $this->posted_at ?? '-',
            'date' => $this->date ?? $this->posted_at ?? null,
            'time' => $this->time,
            'reference' => $this->reference ?? null,
            'merchant' => $this->merchant ?? null,
            'counterpart_iban' => $this->counterpart_iban ?? null,
            'counterparty_iban' => $this->counterpart_iban ?? null,
            'type' => $this->type ?? null,
            'categoryId' => $this->categoryId,
            'budgetId' => $this->budgetId,
            'sourceType' => $this->sourceType,
            'raw' => $this->raw ?? [],
        ];
    }
}
