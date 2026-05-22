<?php

namespace App\Contracts\Repositories;

use App\Models\Transaction;

interface TransactionRepositoryInterface
{
    public function saveTransaction(int $transactionId, array $data): Transaction;
}
