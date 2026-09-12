<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataBackupService;
use App\Support\BackupZip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

function makeBackupZip(): string
{
    $path = sys_get_temp_dir().'/finance-backup-test-'.uniqid().'.zip';
    app(DataBackupService::class)->exportTo($path);

    return $path;
}

test('gasten kunnen geen backup exporteren', function () {
    $this->post('/api/sparen/backup/export')->assertRedirect('/login');
});

test('export geeft een downloadbare zip en verwijdert het serverbestand', function () {
    $user = User::factory()->create();
    Category::query()->create([
        'key' => 'cat-export',
        'name' => 'Exportgroep',
        'slug' => 'exportgroep',
        'type' => 'expense',
    ]);
    Transaction::query()->create([
        'key' => 'tx-export',
        'description' => 'Exportmutatie',
        'amount' => -12.5,
        'date' => '2026-09-01',
        'type' => 'expense',
        'is_pending' => false,
    ]);

    $response = $this->actingAs($user)->post('/api/sparen/backup/export');
    $response->assertOk()->assertHeader('content-disposition');
    expect($response->headers->get('content-type'))->toContain('zip')
        ->and($response->headers->get('content-disposition'))->toContain('attachment');

    $content = $response->getContent();
    expect($content)->toBeString()->toStartWith("PK");

    $zipPath = sys_get_temp_dir().'/finance-backup-download-'.uniqid().'.zip';
    file_put_contents($zipPath, $content);
    $archive = BackupZip::open($zipPath);
    $manifest = json_decode((string) $archive->get('manifest.json'), true);
    expect($manifest['format'])->toBe(DataBackupService::FORMAT)
        ->and($manifest['connections']['catalog']['categories'])->toBe(1)
        ->and($manifest['connections']['ledger']['transactions'])->toBe(1)
        ->and($manifest['connections']['ledger']['users'])->toBe(1);

    $leftovers = File::glob(storage_path('app/private/backups/finance-backup-*.zip')) ?: [];
    expect($leftovers)->toBeEmpty();
    File::delete($zipPath);
});

test('import voegt toe en wist bestaande extra rijen niet', function () {
    $user = User::factory()->create([
        'email' => 'backup@example.com',
        'name' => 'Backup User',
    ]);
    Category::query()->create([
        'key' => 'cat-keep',
        'name' => 'Oorspronkelijk',
        'slug' => 'oorspronkelijk',
        'type' => 'expense',
    ]);
    Transaction::query()->create([
        'key' => 'tx-keep',
        'description' => 'Oorspronkelijk',
        'amount' => -10,
        'date' => '2026-09-01',
        'type' => 'expense',
        'is_pending' => false,
    ]);

    $zipPath = makeBackupZip();

    Category::query()->create([
        'key' => 'cat-extra',
        'name' => 'Later toegevoegd',
        'slug' => 'later-toegevoegd',
        'type' => 'expense',
    ]);
    Transaction::query()->where('key', 'tx-keep')->update(['description' => 'Gewijzigd']);

    $upload = new UploadedFile($zipPath, 'finance-backup.zip', 'application/zip', null, true);

    $this->actingAs($user)
        ->post('/api/sparen/backup/import', ['file' => $upload])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Category::query()->count())->toBe(2)
        ->and(Category::query()->where('key', 'cat-keep')->value('name'))->toBe('Oorspronkelijk')
        ->and(Category::query()->where('key', 'cat-extra')->exists())->toBeTrue()
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->value('description'))->toBe('Gewijzigd')
        ->and(User::query()->where('email', 'backup@example.com')->exists())->toBeTrue();

    File::delete($zipPath);
});

test('import preview telt nieuw gelijk en conflict zonder te schrijven', function () {
    $user = User::factory()->create(['email' => 'preview@example.com']);
    Category::query()->create([
        'key' => 'cat-keep',
        'name' => 'Oorspronkelijk',
        'slug' => 'oorspronkelijk',
        'type' => 'expense',
    ]);
    $zipPath = makeBackupZip();
    Category::query()->where('key', 'cat-keep')->update(['name' => 'Gewijzigd']);

    $upload = new UploadedFile($zipPath, 'finance-backup.zip', 'application/zip', null, true);

    $this->actingAs($user)
        ->post('/api/sparen/backup/import', ['file' => $upload, 'preview' => '1'])
        ->assertOk()
        ->assertJsonPath('summary.preview', true)
        ->assertJsonPath('summary.has_conflicts', true);

    expect(Category::query()->where('key', 'cat-keep')->value('name'))->toBe('Gewijzigd');

    File::delete($zipPath);
});

test('import conflict op catalogus gebruikt backup als dat gekozen is', function () {
    $user = User::factory()->create(['email' => 'choice@example.com']);
    Category::query()->create([
        'key' => 'cat-keep',
        'name' => 'Oorspronkelijk',
        'slug' => 'oorspronkelijk',
        'type' => 'expense',
    ]);
    $zipPath = makeBackupZip();
    Category::query()->where('key', 'cat-keep')->update(['name' => 'Gewijzigd']);

    $upload = new UploadedFile($zipPath, 'finance-backup.zip', 'application/zip', null, true);

    $this->actingAs($user)
        ->post('/api/sparen/backup/import', [
            'file' => $upload,
            'resolutions' => ['catalog.categories' => 'backup'],
        ])
        ->assertOk();

    expect(Category::query()->where('key', 'cat-keep')->value('name'))->toBe('Oorspronkelijk');

    File::delete($zipPath);
});

test('gasten kunnen een backup zetten als er nog geen gebruiker is', function () {
    $user = User::factory()->create([
        'email' => 'setup@example.com',
        'password' => 'old-password',
    ]);
    $zipPath = makeBackupZip();
    $user->delete();

    expect(User::query()->exists())->toBeFalse();

    $upload = new UploadedFile($zipPath, 'finance-backup.zip', 'application/zip', null, true);

    $this->post('/setup/import', [
        'file' => $upload,
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertRedirect('/login');

    $imported = User::query()->where('email', 'setup@example.com')->first();
    expect($imported)->not->toBeNull();
    expect(Hash::check('nieuw-wachtwoord', $imported->password))->toBeTrue();

    File::delete($zipPath);
});

test('setup-import verdwijnt zodra er een gebruiker is', function () {
    User::factory()->create();
    $path = sys_get_temp_dir().'/finance-backup-setup-'.uniqid().'.zip';
    file_put_contents($path, 'PK');
    $upload = new UploadedFile($path, 'finance-backup.zip', 'application/zip', null, true);

    $this->post('/setup/import', [
        'file' => $upload,
        'password' => 'nieuw-wachtwoord',
        'password_confirmation' => 'nieuw-wachtwoord',
    ])->assertNotFound();

    File::delete($path);
});

test('import op één gedeelde database veegt catalogus niet leeg', function () {
    $user = User::factory()->create([
        'email' => 'shared@example.com',
        'name' => 'Shared User',
    ]);
    Category::query()->create([
        'key' => 'cat-shared',
        'name' => 'Gedeeld',
        'slug' => 'gedeeld',
        'type' => 'expense',
    ]);
    Transaction::query()->create([
        'key' => 'tx-shared',
        'description' => 'Gedeelde mutatie',
        'amount' => -8,
        'date' => '2026-09-01',
        'type' => 'expense',
        'is_pending' => false,
    ]);

    $zipPath = makeBackupZip();
    $sharedPath = sys_get_temp_dir().'/finance-shared-'.uniqid('', true).'.sqlite';
    File::put($sharedPath, '');

    config([
        'database.connections.ledger.driver' => 'sqlite',
        'database.connections.ledger.database' => $sharedPath,
        'database.connections.catalog.driver' => 'sqlite',
        'database.connections.catalog.database' => $sharedPath,
    ]);
    DB::purge('ledger');
    DB::purge('catalog');

    $this->artisan('migrate', ['--force' => true]);
    $this->artisan('migrate', [
        '--database' => 'catalog',
        '--path' => 'database/migrations/catalog',
        '--force' => true,
    ]);

    app(DataBackupService::class)->importFrom($zipPath);

    expect(Category::query()->count())->toBe(1)
        ->and(Category::query()->value('name'))->toBe('Gedeeld')
        ->and(Transaction::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'shared@example.com')->exists())->toBeTrue();

    File::delete($zipPath);
    File::delete($sharedPath);
});

test('ongeldige zip wordt geweigerd', function () {
    $user = User::factory()->create();
    $path = sys_get_temp_dir().'/not-a-backup-'.uniqid().'.zip';
    file_put_contents($path, 'nope');
    $upload = new UploadedFile($path, 'finance-backup.zip', 'application/zip', null, true);

    $this->actingAs($user)
        ->post('/api/sparen/backup/import', ['file' => $upload])
        ->assertStatus(422);

    File::delete($path);
});
