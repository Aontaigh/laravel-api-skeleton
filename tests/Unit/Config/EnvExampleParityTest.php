<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Ensures the committed environment contract stays aligned with application config.
 *
 * Every key read from the app's custom config files must appear in `.env.example`
 * (active or commented) so operators and agents discover it without opening
 * `config/`. `.env.ci` mirrors the same section structure for CI parity.
 */
#[CoversNothing]
final class EnvExampleParityTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Collect every `env()` key referenced by the application config files under test.
     *
     * @return list<string> the deduplicated, sorted key names
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
     * Parse active and commented assignment keys from an env file.
     *
     * @param  string       $path absolute path to the env file
     * @return list<string> the deduplicated key names found in the file
     */
    private function keysInEnvFile(string $path): array
    {
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $keys = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\s*#?\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $matches) === 1) {
                $keys[] = $matches[1];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Collect the active declarations in an env file, with the line each appears on.
     *
     * A commented `# KEY=` placeholder documents the contract without declaring
     * a value, so it cannot collide with a declaration.
     *
     * @param  string                   $path absolute path to the env file
     * @return array<string, list<int>> key => the 1-based lines that declare it
     */
    private function activeDeclarations(string $path): array
    {
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $declarations = [];

        foreach (explode("\n", $content) as $index => $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/', $line, $matches) === 1) {
                $declarations[$matches[1]][] = $index + 1;
            }
        }

        return $declarations;
    }

    /**
     * Collect the keys a file declares with an empty value.
     *
     * Laravel resolves the reserved words `empty` and `(empty)` to the empty
     * string in any case, and the loader strips a value's quotes and inline
     * comment, so `KEY="" # retain` declares the same nothing as `KEY=`.
     *
     * @param  string                   $path absolute path to the env file
     * @return array<string, list<int>> key => the 1-based lines that declare it empty
     */
    private function emptyDeclarations(string $path): array
    {
        $content = file_get_contents($path);
        $this->assertIsString($content);

        $declarations = [];

        foreach (explode("\n", $content) as $index => $line) {
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            if (in_array(strtolower($this->declaredValue($matches[2])), ['', 'empty', '(empty)'], true)) {
                $declarations[$matches[1]][] = $index + 1;
            }
        }

        return $declarations;
    }

    /**
     * Read the value a declaration carries after the loader's quoting and comments.
     *
     * An unquoted `#` starts an inline comment wherever it appears; a quoted
     * value ends at its first closing quote, so `KEY="" # note` still declares
     * the empty string.
     *
     * @param  string $value the raw right-hand side of a declaration
     * @return string the value with quotes and any inline comment removed
     */
    private function declaredValue(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed !== '' && ($trimmed[0] === '"' || $trimmed[0] === "'")) {
            $quote = $trimmed[0];

            for ($index = 1; $index < strlen($trimmed); $index++) {
                if ($quote === '"' && $trimmed[$index] === '\\') {
                    $index++;

                    continue;
                }

                if ($trimmed[$index] === $quote) {
                    return substr($trimmed, 1, $index - 1);
                }
            }

            return $trimmed;
        }

        $comment = strpos($trimmed, '#');

        return trim($comment === false ? $trimmed : substr($trimmed, 0, $comment));
    }

    /**
     * Collect every config default that an empty declaration would shadow.
     *
     * `env('KEY', 'default')` returns the default only when the key is absent.
     * A template that declares the key with an empty value makes Laravel hand
     * back the empty string instead, so the default never applies. A read with
     * no default, or whose default is itself empty, has nothing to lose.
     *
     * @return array<string, array{location: string, default: string}> key => where the default is stated
     */
    private function shadowableConfigDefaults(): array
    {
        $files = glob(base_path('config/*.php'));
        $this->assertIsArray($files);

        $defaults = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertIsString($content);

            $matched = preg_match_all(
                '/\b(?:env|Env::get)\(\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s*,\s*/',
                $content,
                $matches,
                PREG_OFFSET_CAPTURE,
            );
            $this->assertIsInt($matched);

            if ($matched === 0) {
                continue;
            }

            foreach ($matches[1] as $index => [$key, $keyOffset]) {
                $default = $this->argumentText($content, $matches[0][$index][1] + strlen($matches[0][$index][0]));

                if (in_array($default, ['', "''", '""'], true)) {
                    continue;
                }

                $line = substr_count(substr($content, 0, $keyOffset), "\n") + 1;

                $defaults[$key] ??= [
                    'location' => str_replace(base_path().'/', '', $file).':'.$line,
                    'default' => $default,
                ];
            }
        }

        ksort($defaults);

        return $defaults;
    }

    /**
     * Read the argument that starts at the given offset, up to its own end.
     *
     * The scan stops at the first comma or closing bracket at depth zero and
     * steps over string literals, so a default that nests a call, an array, or
     * a comma inside a string is captured whole rather than cut at the inner
     * bracket.
     *
     * @param  string $content the file's contents
     * @param  int    $offset  where the argument starts
     * @return string the argument text, trimmed
     */
    private function argumentText(string $content, int $offset): string
    {
        $length = strlen($content);
        $depth = 0;
        $quote = null;
        $index = $offset;

        for (; $index < $length; $index++) {
            $character = $content[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '(' || $character === '[' || $character === '{') {
                $depth++;
            } elseif ($character === ')' || $character === ']' || $character === '}') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                break;
            }
        }

        return trim(substr($content, $offset, $index - $offset));
    }

    /**
     * Read the titled section names from an env file's divider blocks.
     *
     * @param  string       $path absolute path to the env file
     * @return list<string> the section titles in file order
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
     * Key extraction recognises every dotenv shape, not only SCREAMING_SNAKE_CASE.
     *
     * An extractor that only accepts `[A-Z]` as a key's first character misses
     * keys the dotenv format allows (`_PRIVATE`, `privateKey`) and shapes a
     * hand edit produces (leading whitespace, space before `=`), so a malformed
     * or unconventional key would slip past the parity check entirely.
     */
    #[Test]
    public function key_extraction_matches_the_dotenv_recognition_shapes(): void
    {
        // Arrange

        $fixture = tempnam(sys_get_temp_dir(), 'env-parity-');
        $this->assertIsString($fixture);

        file_put_contents($fixture, implode("\n", [
            '_PRIVATE=1',
            'privateKey=2',
            '  INDENTED_KEY=3',
            'SPACED_KEY =4',
            '# _COMMENTED_PRIVATE=5',
            '# A note line with no assignment at all',
        ]));

        // Act

        $keys = $this->keysInEnvFile($fixture);

        unlink($fixture);

        // Assert

        $this->assertSame(
            ['_PRIVATE', 'privateKey', 'INDENTED_KEY', 'SPACED_KEY', '_COMMENTED_PRIVATE'],
            $keys,
        );
    }

    /**
     * Every section divider is separated from the content above it.
     *
     * A scripted reorder that joins sections without the blank line produces
     * `FILESYSTEM_DISK=local` running straight into the next divider - the
     * anatomy breach the `create-env-file` skill names, and easy to write
     * with `join`. The only legal non-blank predecessors are the name line
     * inside a three-line divider block and the file header's comment block
     * above the first section.
     */
    #[Test]
    public function env_files_separate_sections_with_one_blank_line(): void
    {
        $divider = '/^# -{69}$/';

        foreach (['.env.example', '.env.ci'] as $file) {
            $lines = explode("\n", (string) file_get_contents(base_path($file)));
            $firstDivider = null;
            $violations = [];

            foreach ($lines as $index => $line) {
                if (preg_match($divider, $line) !== 1) {
                    continue;
                }

                $firstDivider ??= $index;

                if ($index === $firstDivider) {
                    continue;
                }

                $previous = $lines[$index - 1] ?? '';
                $beforeThat = $lines[$index - 2] ?? '';

                $isNameLine = $previous !== ''
                    && str_starts_with($previous, '# ')
                    && preg_match($divider, $beforeThat) === 1;

                if ($previous !== '' && ! $isNameLine) {
                    $violations[] = $index + 1;
                }
            }

            // Act + Assert

            $this->assertSame([], $violations, "{$file} has dividers with no blank line above them at lines: ".implode(', ', $violations));
        }
    }

    /**
     * Client build keys precede the vendor boundary; security edge is last.
     */
    #[Test]
    public function env_example_follows_canonical_section_order(): void
    {
        // Arrange

        $sections = $this->sectionNames(base_path('.env.example'));

        $sailPorts = array_search('Sail Ports', $sections, true);
        $frontend = array_search('Frontend', $sections, true);
        $geoIp = array_search('GeoIP', $sections, true);
        $cors = array_search('CORS', $sections, true);
        $securityHeaders = array_search('Security Headers', $sections, true);

        // Assert

        $this->assertNotFalse($sailPorts);
        $this->assertNotFalse($frontend);
        $this->assertNotFalse($geoIp);
        $this->assertNotFalse($cors);
        $this->assertNotFalse($securityHeaders);
        $this->assertSame(1, $sailPorts);
        $this->assertLessThan($geoIp, $frontend);
        $this->assertLessThan($cors, $geoIp);
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

    /**
     * No env file declares the same key on more than one line.
     *
     * Dotenv keeps the last declaration, so a duplicate hides the earlier value
     * from anyone reading the file top-down and turns an edit to the first line
     * into a silent no-op.
     */
    #[Test]
    public function env_files_do_not_declare_a_key_twice(): void
    {
        foreach (['.env.example', '.env.ci'] as $file) {
            // Arrange

            $declarations = $this->activeDeclarations(base_path($file));
            $duplicates = [];

            // Act

            foreach ($declarations as $key => $lines) {
                if (count($lines) > 1) {
                    $duplicates[] = $key.' (lines '.implode(', ', $lines).')';
                }
            }

            // Assert

            $this->assertSame(
                [],
                $duplicates,
                "{$file} declares these keys more than once, and the last definition wins: ".implode(', ', $duplicates),
            );
        }
    }

    /**
     * Empty declarations never hide a config default.
     *
     * `env('KEY', 'default')` returns the default only when the key is absent.
     * A template that declares the key empty makes Laravel hand back the empty
     * string instead, so the default never applies - and the symptom appears
     * far from the line that caused it.
     */
    #[Test]
    public function empty_declarations_do_not_shadow_config_defaults(): void
    {
        // Arrange

        $defaults = $this->shadowableConfigDefaults();

        foreach (['.env.example', '.env.ci'] as $file) {
            $empty = $this->emptyDeclarations(base_path($file));
            $shadowed = [];

            // Act

            foreach ($empty as $key => $lines) {
                if (! isset($defaults[$key])) {
                    continue;
                }

                $shadowed[] = $key.' at '.$file.':'.$lines[0]
                    .' shadows '.$defaults[$key]['default'].' from '.$defaults[$key]['location'];
            }

            // Assert

            $this->assertSame(
                [],
                $shadowed,
                'Declare a non-empty value or remove the empty declaration: '.implode(', ', $shadowed),
            );
        }
    }
}
