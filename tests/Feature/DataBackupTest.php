<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DataBackupService;
use App\Support\BackupZip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

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

test('import overschrijft catalogus en ledger vanuit de zip', function () {
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

    expect(Category::query()->count())->toBe(1)
        ->and(Category::query()->value('name'))->toBe('Oorspronkelijk')
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Transaction::query()->value('description'))->toBe('Oorspronkelijk')
        ->and(User::query()->where('email', 'backup@example.com')->exists())->toBeTrue();

    File::delete($zipPath);
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
