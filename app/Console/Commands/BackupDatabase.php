<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

#[Signature('app:backup-database {--keep=14 : Days to keep older backups} {--binary= : The mysqldump program (default: MYSQLDUMP_BINARY or "mysqldump" on PATH)}')]
#[Description('Back up the database to storage/app/private/backups (mysqldump for MySQL, a file copy for SQLite)')]
class BackupDatabase extends Command
{
    public const DIRECTORY = 'backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);
        $name = self::DIRECTORY.'/'.$connection.'-'.now()->format('Y-m-d-His');

        $path = match ($config['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($config, $disk->path($name.'.sql.gz')),
            'sqlite' => $this->copySqlite($config, $disk->path($name.'.sqlite')),
            default => null,
        };

        if ($path === null) {
            $this->error(in_array($config['driver'], ['mysql', 'mariadb', 'sqlite'], true)
                ? 'The backup failed (see above), or the SQLite database is in memory.'
                : "No backup for the {$config['driver']} driver.");

            return self::FAILURE;
        }

        $this->prune((int) $this->option('keep'));
        $this->info('Backed up to '.$path);

        return self::SUCCESS;
    }

    /**
     * mysqldump in one consistent snapshot, then gzipped. No shell: the arguments go to mysqldump as they are,
     * and the password through the environment rather than the command line.
     *
     * @param  array<string, mixed>  $config
     */
    private function dumpMysql(array $config, string $target): ?string
    {
        $plain = substr($target, 0, -3);

        $process = new Process([
            $this->option('binary') ?: config('database.mysqldump'), '--single-transaction', '--quick', '--routines', '--no-tablespaces',
            '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
            '--result-file='.$plain, (string) $config['database'],
        ], null, ['MYSQL_PWD' => (string) $config['password']], null, 3600);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($plain);
            $this->error(trim($process->getErrorOutput()) ?: 'mysqldump failed.');

            return null;
        }

        $this->gzip($plain, $target);

        return $target;
    }

    /**
     * Compress a file 1 MB at a time and remove the original.
     */
    private function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
        unlink($source);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function copySqlite(array $config, string $target): ?string
    {
        $source = (string) $config['database'];

        if ($source === ':memory:' || ! is_file($source)) {
            return null;
        }

        return copy($source, $target) ? $target : null;
    }

    private function prune(int $keepDays): void
    {
        $disk = Storage::disk('local');

        collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $file): bool => $disk->lastModified($file) < now()->subDays($keepDays)->getTimestamp())
            ->each(fn (string $file) => $disk->delete($file));
    }
}
