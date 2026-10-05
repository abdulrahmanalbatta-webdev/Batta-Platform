<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

#[Signature('app:backup-database {--keep=14 : Days to keep older backups}')]
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
            $this->error("No backup for the {$config['driver']} driver (or an in-memory database).");

            return self::FAILURE;
        }

        $this->prune((int) $this->option('keep'));
        $this->info('Backed up to '.$path);

        return self::SUCCESS;
    }

    /**
     * mysqldump in one consistent snapshot, gzipped; the password goes through the environment, not the command line.
     *
     * @param  array<string, mixed>  $config
     */
    private function dumpMysql(array $config, string $target): ?string
    {
        $command = sprintf(
            'mysqldump --single-transaction --quick --routines --no-tablespaces --host=%s --port=%s --user=%s %s | gzip > %s',
            escapeshellarg((string) $config['host']), escapeshellarg((string) $config['port']), escapeshellarg((string) $config['username']),
            escapeshellarg((string) $config['database']), escapeshellarg($target),
        );

        $process = Process::fromShellCommandline('set -o pipefail; '.$command, null, ['MYSQL_PWD' => (string) $config['password']], null, 3600);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($target);
            $this->error(trim($process->getErrorOutput()) ?: 'mysqldump failed.');

            return null;
        }

        return $target;
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
