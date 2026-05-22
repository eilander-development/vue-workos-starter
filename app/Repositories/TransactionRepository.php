<?php

namespace App\Repositories;

use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Models\Transaction;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function saveTransaction(int $transactionId, array $data): Transaction
    {
        $transaction = Transaction::find($transactionId) ?? new Transaction;

        $transaction->fill($data);

        if (! $transaction->exists) {
            $transaction->id = $transactionId;
        }

        $transaction->save();

        return $transaction;
    }
}
