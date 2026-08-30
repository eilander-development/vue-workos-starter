<?php

namespace App\Console\Commands;

use App\Services\TransactionClassifier;
use Illuminate\Console\Command;

class ReclassifyPotDepositsCommand extends Command
{
    protected $signature = 'sparen:reclassify-pot-deposits';

    protected $description = 'Ontkoppel spaaropnames en pot-overboekingen van begrotingsposten';

    public function handle(TransactionClassifier $classifier): int
    {
        $updated = $classifier->reclassifyPotDeposits();

        $this->info("{$updated} spaar-overboekingen herclassificeerd.");

        return self::SUCCESS;
    }
}
