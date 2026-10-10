<?php

namespace App\Jobs;

use App\Actions\PlatformData\PlatformTables;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\ExportReady;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * A ZIP of the platform's data, one JSON file per table, written to the private disk; the member who asked
 * gets a bell notification and an email when it's ready. Secrets and passwords are left out.
 */
class ExportPlatformData implements ShouldQueue
{
    use Queueable;

    /**
     * Exports are written to the private default disk, which the web servers and the queue workers share.
     */
    public static function disk(): string
    {
        return config('filesystems.default');
    }

    public const DIRECTORY = 'exports';

    /**
     * Exports are deleted after this many days (scheduled in routes/console.php).
     */
    public const KEEP_DAYS = 7;

    /**
     * A big platform can take a while.
     */
    public int $timeout = 600;

    public function __construct(public User $requester) {}

    public function handle(): void
    {
        $disk = Storage::disk(self::disk());
        $name = 'batta-export-'.now()->format('Y-m-d-His').'.zip';

        // built in a temporary file, then stored on the disk (which may be a bucket rather than a folder)
        $archive = tempnam(sys_get_temp_dir(), 'export-zip');
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }

        $counts = [];
        $temporary = [];

        foreach ([...PlatformTables::BUSINESS, 'users', 'activities'] as $table) {
            $file = tempnam(sys_get_temp_dir(), 'export');
            $temporary[] = $file;
            $counts[$table] = $this->writeTable($table, $file);
            $zip->addFile($file, "{$table}.json");
        }

        $zip->addFromString('settings.json', json_encode($this->publicSettings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('manifest.json', json_encode([
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'requested_by' => $this->requester->email,
            'rows' => $counts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $disk->putFileAs(self::DIRECTORY, new File($archive), $name);
        array_map('unlink', [...$temporary, $archive]);

        Activity::create(['user_id' => $this->requester->id, 'action' => 'exported', 'subject_name' => $name]);
        $this->requester->notify(new ExportReady($name, $disk->size(self::DIRECTORY.'/'.$name)));
    }

    /**
     * Stream one table into a JSON array file, 500 rows at a time; returns the number of rows.
     */
    private function writeTable(string $table, string $file): int
    {
        $hidden = ['password', 'remember_token', 'known_devices', 'two_factor_secret', 'two_factor_recovery_codes'];
        $handle = fopen($file, 'w');
        fwrite($handle, '[');
        $count = 0;

        foreach (DB::table($table)->orderBy('id')->lazy(500) as $row) {
            $row = array_diff_key((array) $row, array_flip($hidden));
            fwrite($handle, ($count ? ',' : '')."\n".json_encode($row, JSON_UNESCAPED_UNICODE));
            $count++;
        }

        fwrite($handle, "\n]\n");
        fclose($handle);

        return $count;
    }

    /**
     * The settings, with secrets only saying whether they were set.
     *
     * @return array<string, mixed>
     */
    private function publicSettings(): array
    {
        return app(PlatformSettings::class)->forClient();
    }
}
