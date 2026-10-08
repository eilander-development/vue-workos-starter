<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshFinanceDatabases
{
    use RefreshDatabase {
        migrateDatabases as private migrateLedgerDatabase;
    }

    /** @var list<string> */
    protected $connectionsToTransact = ['ledger', 'catalog'];

    protected function migrateDatabases(): void
    {
        $this->migrateLedgerDatabase();

        $this->artisan('migrate:fresh', [
            '--database' => 'catalog',
            '--path' => 'database/migrations/catalog',
        ])->assertExitCode(0);
    }
}
