<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserPasswordCommand extends Command
{
    protected $signature = 'user:set-password
        {email? : E-mailadres van de gebruiker}
        {--password= : Nieuw wachtwoord}';

    protected $description = 'Zet het databasewachtwoord van een gebruiker.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->orderBy('id')->first();

        if (! $user) {
            $this->error('Geen gebruiker gevonden.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = $this->secret('Nieuw wachtwoord');
        }
        if (! is_string($password) || strlen($password) < 8) {
            $this->error('Wachtwoord moet minstens 8 tekens zijn.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Wachtwoord gezet voor {$user->email}.");

        return self::SUCCESS;
    }
}
