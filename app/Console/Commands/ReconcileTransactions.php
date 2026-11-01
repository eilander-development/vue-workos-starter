<?php

namespace App\Console\Commands;

use App\Services\TransactionLifecycle;
use Illuminate\Console\Command;

class ReconcileTransactions extends Command
{
    protected $signature = 'transactions:reconcile {--apply : Archive proven duplicates; otherwise only report}';

    protected $description = 'Reconcile bank copies and superseded reservations while preserving transaction history and links';

    public function handle(TransactionLifecycle $lifecycle): int
    {
        $this->line(json_encode($lifecycle->reconcileStored((bool) $this->option('apply'))));

        return self::SUCCESS;
    }
}
