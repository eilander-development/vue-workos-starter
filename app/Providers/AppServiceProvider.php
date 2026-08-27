<?php

namespace App\Providers;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\InputInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
    }

    public function boot(): void
    {
        $this->ensureSqliteFile(config('database.connections.catalog.database'));
        $this->ensureSqliteFile(config('database.connections.sqlite.database'));

        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if ($event->command !== 'migrate') {
                return;
            }

            $input = $event->input;
            if (! $input instanceof InputInterface) {
                return;
            }

            $database = $input->getOption('database');
            $path = $input->getOption('path');
            if ($database === 'catalog' || $path === 'database/migrations/catalog') {
                return;
            }

            $this->ensureSqliteFile(config('database.connections.catalog.database'));
            $this->ensureSqliteFile(config('database.connections.sqlite.database'));

            Artisan::call('migrate', [
                '--database' => 'catalog',
                '--path' => 'database/migrations/catalog',
                '--force' => true,
            ]);
        });
    }

    private function ensureSqliteFile(mixed $path): void
    {
        if (! is_string($path) || $path === '' || $path === ':memory:') {
            return;
        }

        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && ! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (! is_file($path)) {
            touch($path);
        }
    }
}
