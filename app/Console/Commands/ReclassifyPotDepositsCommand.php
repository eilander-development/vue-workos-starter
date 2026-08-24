<?php

namespace App\Console\Commands;

use App\Services\TransactionClassifier;
use Illuminate\Console\Command;

class ReclassifyPotDepositsCommand extends Command
{
    protected $signature = 'sparen:reclassify-pot-deposits';

    protected $description = 'Ontkoppel Naar/Van-overboekingen voor potjes van rubrieken';

    public function handle(TransactionClassifier $classifier): int
    {
        $updated = $classifier->reclassifyPotDeposits();

        $this->info("{$updated} pot-overboekingen herclassificeerd.");

        return self::SUCCESS;
    }
}
