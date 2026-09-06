<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GeoIp\GeoIpDatabase;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use UnexpectedValueException;

/**
 * Downloads the MaxMind GeoLite2 City database for GeoIP lookups.
 */
final class GeoipUpdateCommand extends Command
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geoip:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download The MaxMind GeoLite2 City Database';

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Download GeoLite2-City into the configured database path.
     *
     * CLI only: never invoked from a web request. The extracted file is
     * opened with a GeoIp2 Reader before the destination is touched, and the
     * swap is an atomic `rename()` on the destination filesystem - a
     * concurrent lookup reads either the old or
     * the new file, never a half-written one. No runtime output hint is
     * printed after a successful write: this starter runs PHP-FPM/Sail, not
     * Octane, so workers simply pick up the new file as they are recycled.
     *
     * @return int Command::SUCCESS or Command::FAILURE
     */
    public function __invoke(): int
    {
        $accountId = config()->string('geoip.account_id');
        $licenseKey = config()->string('geoip.license_key');

        if ($accountId === '') {
            $this->error('MaxMind Account ID Is Not Configured');

            return self::FAILURE;
        }

        if ($licenseKey === '') {
            $this->error('MaxMind License Key Is Not Configured');

            return self::FAILURE;
        }

        $destination = GeoIpDatabase::resolvedDatabasePath();
        $downloadUrl = config()->string('geoip.download_url');

        $temporaryDirectory = sys_get_temp_dir().'/geoip-update-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($temporaryDirectory);

        $archivePath = $temporaryDirectory.'/GeoLite2-City.tar.gz';

        try {
            $response = Http::timeout(120)
                ->withBasicAuth($accountId, $licenseKey)
                ->sink($archivePath)
                ->get($downloadUrl, [
                    'suffix' => 'tar.gz',
                ]);

            if (! $response->successful()) {
                $this->error($this->downloadFailureMessage($response));

                return self::FAILURE;
            }

            $databasePath = $this->extractDatabase($archivePath, $temporaryDirectory);

            $this->replaceDatabase($databasePath, $destination);
        } catch (Throwable $exception) {
            $this->error('GeoLite2 City Database Update Failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }

        $this->info('GeoLite2 City Database Updated');

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build a Title Case download-failure message that includes the HTTP status.
     *
     * @param  Response $response the failed HTTP response
     * @return string   the operator-facing error
     */
    private function downloadFailureMessage(Response $response): string
    {
        return 'GeoLite2 City Database Download Failed: HTTP '.$response->status();
    }

    /**
     * Extract the `.mmdb` from a GeoLite2 tar.gz archive.
     *
     * @param  string $archivePath        the downloaded tar.gz path
     * @param  string $temporaryDirectory a writable scratch directory
     * @return string the extracted `.mmdb` path
     */
    private function extractDatabase(string $archivePath, string $temporaryDirectory): string
    {
        try {
            $archive = new PharData($archivePath);
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException('GeoLite2 Archive Could Not Be Opened', 0, $exception);
        }

        $extractPath = $temporaryDirectory.'/extracted';
        File::ensureDirectoryExists($extractPath);
        $archive->extractTo($extractPath, null, true);

        $database = collect(File::allFiles($extractPath))
            ->first(static fn (SplFileInfo $file): bool => $file->getExtension() === 'mmdb');

        if ($database === null) {
            throw new RuntimeException('GeoLite2 Archive Did Not Contain an MMDB');
        }

        return $database->getPathname();
    }

    /**
     * Validate the extracted MMDB, then atomically replace the destination.
     *
     * Validation opens the file with a GeoIp2 Reader, which parses the
     * MaxMind DB metadata the way every later lookup will. A truncated,
     * HTML error page saved as an archive, or otherwise corrupt download
     * throws here and leaves the previously installed database untouched.
     *
     * @param  string $extractedPath the extracted candidate MMDB
     * @param  string $destination   the configured database path
     * @return void
     */
    private function replaceDatabase(string $extractedPath, string $destination): void
    {
        try {
            new Reader($extractedPath);
        } catch (Throwable $exception) {
            throw new RuntimeException('GeoLite2 Database Failed Validation', 0, $exception);
        }

        File::ensureDirectoryExists(dirname($destination));

        /*
         * Stage the copy next to the destination so `rename()` swaps it in
         * atomically on the same filesystem, then clean the stage up if the
         * rename is refused (e.g. a destination directory permission change
         * between the copy and the swap).
         */
        $stagedPath = $destination.'-staged-'.bin2hex(random_bytes(4));

        try {
            File::copy($extractedPath, $stagedPath);

            if (! rename($stagedPath, $destination)) {
                throw new RuntimeException('GeoLite2 Database Could Not Be Replaced');
            }
        } catch (Throwable $exception) {
            File::delete($stagedPath);

            throw $exception;
        }
    }
}
