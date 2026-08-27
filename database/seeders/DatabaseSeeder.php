<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Catalogus staat in database/catalog.sqlite (git). Niet opnieuw seeden.
        // Ledger (transacties, users) blijft lokaal in database/ledger.sqlite.
    }
}
