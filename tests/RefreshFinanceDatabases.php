<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshFinanceDatabases
{
    use RefreshDatabase;

    /** @var list<string> */
    protected $connectionsToTransact = ['sqlite', 'catalog'];

    protected function afterRefreshingDatabase()
    {
        $this->artisan('migrate:fresh', [
            '--database' => 'catalog',
            '--path' => 'database/migrations/catalog',
        ]);
    }
}
