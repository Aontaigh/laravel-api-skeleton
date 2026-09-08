<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Ensures the committed environment contract stays aligned with application config.
 *
 * Every key read from the app's custom config files must appear in `.env.example`
 * (active or commented) so operators and agents discover it without opening
 * `config/`. `.env.ci` mirrors the same section structure for CI parity.
 */
final class EnvExampleParityTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Every application config env key is documented in `.env.example`.
     */
    #[Test]
    public function env_example_documents_application_config_keys(): void
    {
        // Arrange

        $documentedKeys = $this->keysInEnvFile(base_path('.env.example'));
        $requiredKeys = $this->applicationConfigEnvKeys();

        // Act

        $missing = array_values(array_diff($requiredKeys, $documentedKeys));

        // Assert

        $this->assertSame([], $missing, 'Add missing keys to `.env.example`: '.implode(', ', $missing));
    }

    /**
     * `.env.ci` carries the same section banners as `.env.example`.
     */
    #[Test]
    public function env_ci_mirrors_example_sections(): void
    {
        // Arrange

        $exampleSections = $this->sectionNames(base_path('.env.example'));
        $ciSections = $this->sectionNames(base_path('.env.ci'));

        // Assert

        $this->assertSame($exampleSections, $ciSections);
    }

    /**
     * Product and integration sections precede Frontend; security edge is last.
     */
    #[Test]
    public function env_example_follows_canonical_section_order(): void
    {
        // Arrange

        $sections = $this->sectionNames(base_path('.env.example'));

        $geoIp = array_search('GeoIP', $sections, true);
        $frontend = array_search('Frontend', $sections, true);
        $cors = array_search('CORS', $sections, true);
        $securityHeaders = array_search('Security Headers', $sections, true);

        // Assert

        $this->assertNotFalse($geoIp);
        $this->assertNotFalse($frontend);
        $this->assertNotFalse($cors);
        $this->assertNotFalse($securityHeaders);
        $this->assertLessThan($frontend, $geoIp);
        $this->assertLessThan($cors, $frontend);
        $this->assertSame(count($sections) - 1, $securityHeaders);
    }

    /**
     * `.env.ci` documents the same keys as `.env.example`.
     */
    #[Test]
    public function env_ci_documents_the_same_keys_as_example(): void
    {
        // Arrange

        $exampleKeys = $this->keysInEnvFile(base_path('.env.example'));
        $ciKeys = $this->keysInEnvFile(base_path('.env.ci'));

        // Act

        $missingFromCi = array_values(array_diff($exampleKeys, $ciKeys));
        $extraInCi = array_values(array_diff($ciKeys, $exampleKeys));

        // Assert

        $this->assertSame([], $missingFromCi, 'Add missing keys to `.env.ci`: '.implode(', ', $missingFromCi));
        $this->assertSame([], $extraInCi, 'Remove extra keys from `.env.ci`: '.implode(', ', $extraInCi));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @return list<string>
     */
    private function applicationConfigEnvKeys(): array
    {
        $keys = [
            'TRUSTED_PROXIES',
            'AUTH_VERIFICATION_EXPIRE',
            'TELESCOPE_ENABLED',
        ];

        $configFiles = [
            'config/api.php',
            'config/cors.php',
            'config/geoip.php',
            'config/security.php',
            'config/useragent.php',
        ];

        foreach ($configFiles as $relativePath) {
            $content = file_get_contents(base_path($relativePath));
            $this->assertIsString($content);

            preg_match_all("/env\('([^']+)'/", $content, $matches);

            foreach ($matches[1] as $key) {
                $keys[] = $key;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function keysInEnvFile(string $path): array
    {
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $keys = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^(?:#\s*)?([A-Z][A-Z0-9_]*)=/', $line, $matches) === 1) {
                $keys[] = $matches[1];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    private function sectionNames(string $path): array
    {
        $lines = explode("\n", (string) file_get_contents($path));
        $divider = '/^# -{69}$/';
        $sections = [];

        foreach ($lines as $index => $line) {
            if ($index === 0 || $index >= count($lines) - 1) {
                continue;
            }

            if (preg_match($divider, $lines[$index - 1]) === 1
                && preg_match($divider, $lines[$index + 1]) === 1) {
                $sections[] = trim($line, '# ');
            }
        }

        return $sections;
    }
}
