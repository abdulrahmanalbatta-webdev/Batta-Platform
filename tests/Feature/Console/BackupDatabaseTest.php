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

    public function test_dumps_mysql_without_a_shell_and_gzips_it(): void
    {
        Storage::fake('local');
        // a stand-in mysqldump that writes its arguments and MYSQL_PWD into --result-file
        $bin = sys_get_temp_dir().'/fake-mysqldump-'.uniqid();
        mkdir($bin);
        file_put_contents($bin.'/mysqldump', "#!/bin/sh\nfor a in \"\$@\"; do case \$a in --result-file=*) f=\${a#--result-file=};; esac; done\necho \"-- dump \$* pwd=\$MYSQL_PWD\" > \"\$f\"\n");
        chmod($bin.'/mysqldump', 0755);
        config(['database.connections.dump_test' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'username' => 'root', 'password' => 'p@ss word', 'database' => 'batta'], 'database.default' => 'dump_test']);

        $this->artisan('app:backup-database', ['--binary' => $bin.'/mysqldump'])->assertSuccessful();

        $file = Storage::disk('local')->files('backups')[0];
        $this->assertStringEndsWith('.sql.gz', $file);
        $dump = gzdecode(Storage::disk('local')->get($file));
        $this->assertStringContainsString('--single-transaction', $dump);
        $this->assertStringContainsString('pwd=p@ss word', $dump);
        $this->assertStringNotContainsString('p@ss word --', explode('pwd=', $dump)[0]);
    }

    public function test_an_in_memory_database_cannot_be_backed_up(): void
    {
        Storage::fake('local');
        config(['database.connections.memory_test' => ['driver' => 'sqlite', 'database' => ':memory:'], 'database.default' => 'memory_test']);

        $this->artisan('app:backup-database')->assertFailed();
    }
}
