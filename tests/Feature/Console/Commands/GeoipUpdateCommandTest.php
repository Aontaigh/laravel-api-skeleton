<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\GeoipUpdateCommand;
use App\Services\GeoIp\GeoIpDatabase;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Phar;
use PharData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Tests\Concerns\BuildsGeoLiteCityDatabase;
use Tests\TestCase;

/**
 * Feature tests for `geoip:update`.
 */
#[CoversClass(GeoipUpdateCommand::class)]
#[CoversClass(GeoIpDatabase::class)]
final class GeoipUpdateCommandTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use BuildsGeoLiteCityDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /*
     * Credential Guard Tests
     * ----------------------
     */

    /**
     * Fail when the MaxMind account id is empty.
     */
    #[Test]
    public function it_fails_when_the_account_id_is_missing(): void
    {
        // Arrange

        config([
            'geoip.account_id' => '',
            'geoip.license_key' => 'test-license',
        ]);

        // Act

        $exitCode = Artisan::call('geoip:update');

        // Assert

        $this->assertSame(ConsoleCommand::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'MaxMind Account ID Is Not Configured',
            Artisan::output(),
        );
    }

    /*
     * Download And Install Tests
     * --------------------------
     */

    /**
     * Fail when the MaxMind license key is empty.
     */
    #[Test]
    public function it_fails_when_the_license_key_is_missing(): void
    {
        // Arrange

        config([
            'geoip.account_id' => '123456',
            'geoip.license_key' => '',
        ]);

        // Act

        $exitCode = Artisan::call('geoip:update');

        // Assert

        $this->assertSame(ConsoleCommand::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'MaxMind License Key Is Not Configured',
            Artisan::output(),
        );
    }

    /**
     * Surface a failed MaxMind download without extracting an archive.
     */
    #[Test]
    public function it_fails_when_the_download_is_rejected(): void
    {
        // Arrange

        config([
            'geoip.account_id' => '123456',
            'geoip.license_key' => 'test-license',
            'geoip.download_url' => 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download',
        ]);

        Http::fake([
            'download.maxmind.com/*' => Http::response('Denied', 401),
        ]);

        // Act

        $exitCode = Artisan::call('geoip:update');

        // Assert

        $this->assertSame(ConsoleCommand::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'GeoLite2 City Database Download Failed: HTTP 401',
            Artisan::output(),
        );
    }

    /*
     * Schedule Tests
     * --------------
     */

    /**
     * Write the extracted MMDB to the configured destination.
     */
    #[Test]
    public function it_writes_the_database_to_the_configured_destination(): void
    {
        // Arrange

        $destination = storage_path('framework/testing/geoip/GeoLite2-City.mmdb');
        File::delete($destination);

        config([
            'geoip.account_id' => '123456',
            'geoip.license_key' => 'test-license',
            'geoip.database' => $destination,
            'geoip.download_url' => 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download',
        ]);

        $fixture = $this->writeGeoLiteCityDatabase();
        $archive = $this->makeGeoLiteArchive((string) file_get_contents($fixture));
        $body = file_get_contents($archive);
        $this->assertNotFalse($body);

        Http::fake([
            'download.maxmind.com/*' => Http::response($body, 200),
        ]);

        // Act

        $exitCode = Artisan::call('geoip:update');
        $output = Artisan::output();

        // Assert

        $this->assertSame(ConsoleCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('GeoLite2 City Database Updated', $output);
        $this->assertFileExists($destination);

        File::delete($destination);
        File::delete($archive);
    }

    /**
     * Keep the existing database when the extracted MMDB fails validation.
     *
     * The command must open the extracted file with a GeoIp2 Reader before
     * anything touches the destination, so a corrupt download can never
     * clobber a working database.
     */
    #[Test]
    public function it_keeps_the_existing_database_when_the_extraction_fails_validation(): void
    {
        // Arrange

        $destination = storage_path('framework/testing/geoip/GeoLite2-City.mmdb');
        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, 'existing-database');

        config([
            'geoip.account_id' => '123456',
            'geoip.license_key' => 'test-license',
            'geoip.database' => $destination,
            'geoip.download_url' => 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download',
        ]);

        $archive = $this->makeGeoLiteArchive('not-a-real-mmdb');
        $body = file_get_contents($archive);
        $this->assertNotFalse($body);

        Http::fake([
            'download.maxmind.com/*' => Http::response($body, 200),
        ]);

        // Act

        $exitCode = Artisan::call('geoip:update');
        $output = Artisan::output();

        // Assert

        $this->assertSame(ConsoleCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('GeoLite2 City Database Update Failed', $output);
        $this->assertSame('existing-database', (string) file_get_contents($destination));

        File::delete($destination);
        File::delete($archive);
    }

    /**
     * Register `geoip:update` weekly in `staging` and `production` only.
     */
    #[Test]
    public function it_schedules_a_weekly_update_for_staging_and_production(): void
    {
        // Arrange

        $schedule = $this->app->make(Schedule::class);

        // Act

        $event = collect($schedule->events())->first(
            static function (ScheduledEvent $scheduled): bool {
                return is_string($scheduled->command)
                    && str_contains($scheduled->command, 'geoip:update');
            },
        );

        // Assert

        $this->assertInstanceOf(ScheduledEvent::class, $event);
        $this->assertSame('15 3 * * 0', $event->expression);
        $this->assertSame('UTC', $event->timezone);
        $this->assertSame(['staging', 'production'], $event->environments);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertFalse($event->runsInEnvironment('testing'));
        $this->assertFalse($event->runsInEnvironment('local'));
        $this->assertTrue($event->runsInEnvironment('staging'));
        $this->assertTrue($event->runsInEnvironment('production'));

        config(['geoip.account_id' => '']);
        $this->assertFalse($event->filtersPass($this->app));

        config(['geoip.account_id' => '123456', 'geoip.license_key' => '']);
        $this->assertFalse($event->filtersPass($this->app));

        config(['geoip.account_id' => '123456', 'geoip.license_key' => 'test-license']);
        $this->assertTrue($event->filtersPass($this->app));
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Build a tar.gz whose archive contains the given MMDB contents.
     *
     * @param  string $databaseContents the raw MMDB bytes to pack
     * @return string the archive path
     */
    private function makeGeoLiteArchive(string $databaseContents): string
    {
        $directory = sys_get_temp_dir().'/geoip-fixture-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($directory.'/GeoLite2-City_20260101');
        File::put($directory.'/GeoLite2-City_20260101/GeoLite2-City.mmdb', $databaseContents);

        $archive = sys_get_temp_dir().'/GeoLite2-City-'.bin2hex(random_bytes(4)).'.tar.gz';

        $phar = new PharData(substr($archive, 0, -3));
        $phar->buildFromDirectory($directory);
        $phar->compress(Phar::GZ);

        File::deleteDirectory($directory);
        File::delete(substr($archive, 0, -3));

        return $archive;
    }
}
