<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    public function test_copies_a_sqlite_database_and_prunes_old_backups(): void
    {
        Storage::fake('local');
        $database = tempnam(sys_get_temp_dir(), 'db');
        file_put_contents($database, 'sqlite-bytes');
        config(['database.connections.backup_test' => ['driver' => 'sqlite', 'database' => $database], 'database.default' => 'backup_test']);
        Storage::disk('local')->put('backups/sqlite-old.sqlite', 'old');
        touch(Storage::disk('local')->path('backups/sqlite-old.sqlite'), now()->subDays(20)->getTimestamp());

        $this->artisan('app:backup-database')->assertSuccessful();

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertSame('sqlite-bytes', Storage::disk('local')->get($files[0]));
        unlink($database);
    }

    public function test_an_in_memory_database_cannot_be_backed_up(): void
    {
        Storage::fake('local');

        $this->artisan('app:backup-database')->assertFailed();
    }
}
