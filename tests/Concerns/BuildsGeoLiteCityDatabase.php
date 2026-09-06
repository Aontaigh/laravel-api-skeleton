<?php

declare(strict_types=1);

namespace Tests\Concerns;

use RuntimeException;

/**
 * Builds a minimal but fully valid GeoLite2-City MMDB in the MaxMind DB
 * binary format.
 *
 * The file carries a real search tree and data section for exactly one
 * address (8.8.8.8, city "Mountain View", country "US"), so
 * `GeoIp2\Database\Reader` opens it and resolves lookups for that address
 * while every other address throws `AddressNotFoundException`. Tests use it
 * to exercise real Reader behaviour without committing a multi-megabyte
 * database to the repository.
 */
trait BuildsGeoLiteCityDatabase
{
    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Write the fixture database to a temporary file.
     *
     * @return string the path of the written MMDB
     */
    private function writeGeoLiteCityDatabase(): string
    {
        $bytes = $this->searchTree().str_repeat("\x00", 16).$this->dataRecord().$this->metadata();

        $path = sys_get_temp_dir().'/GeoLite2-City-fixture-'.bin2hex(random_bytes(4)).'.mmdb';

        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Failed To Write GeoLite2 Fixture');
        }

        return $path;
    }

    /**
     * Encode a UTF-8 string value (type 2).
     *
     * @param  string $value the string to encode
     * @return string the encoded bytes
     */
    private function mmdbString(string $value): string
    {
        $length = strlen($value);

        if ($length > 28) {
            throw new RuntimeException('Fixture Strings Must Stay Short Enough For The One Byte Control');
        }

        return chr((2 << 5) | $length).$value;
    }

    /**
     * Encode a uint16 value (type 5) using the fewest bytes that fit.
     *
     * @param  int    $value the value to encode
     * @return string the encoded bytes
     */
    private function mmdbUint16(int $value): string
    {
        if ($value < 0 || $value > 0xFFFF) {
            throw new RuntimeException('Fixture Uint16 Values Must Stay In Range');
        }

        if ($value < 0x100) {
            return chr((5 << 5) | 1).chr($value);
        }

        return chr((5 << 5) | 2).pack('n', $value);
    }

    /**
     * Encode a uint32 value (type 6).
     *
     * @param  int    $value the value to encode
     * @return string the encoded bytes
     */
    private function mmdbUint32(int $value): string
    {
        if ($value < 0 || $value > 0xFFFFFFFF) {
            throw new RuntimeException('Fixture Uint32 Values Must Stay In Range');
        }

        return chr((6 << 5) | 4).pack('N', $value);
    }

    /**
     * Encode a map (type 7) from ordered key-value pairs.
     *
     * @param  array<string, string> $encodedEntries already-encoded values keyed by literal key
     * @return string                the encoded bytes
     */
    private function mmdbMap(array $encodedEntries): string
    {
        $count = count($encodedEntries);

        if ($count > 28) {
            throw new RuntimeException('Fixture Maps Must Stay Short Enough For The One Byte Control');
        }

        $bytes = chr((7 << 5) | $count);

        foreach ($encodedEntries as $key => $encodedValue) {
            $bytes .= $this->mmdbString($key).$encodedValue;
        }

        return $bytes;
    }

    /**
     * Encode an array (type 11) of already-encoded values.
     *
     * @param  list<string> $encodedItems the encoded item bytes
     * @return string       the encoded bytes
     */
    private function mmdbArray(array $encodedItems): string
    {
        $count = count($encodedItems);

        if ($count > 28) {
            throw new RuntimeException('Fixture Arrays Must Stay Short Enough For The One Byte Control');
        }

        /*
         * Type 11 does not fit the three-bit control encoding, so it is
         * written in extended form: a type-0 control byte carrying the size,
         * then the extended type number (11 minus the seven-entry offset).
         */
        return chr($count).chr(11 - 7).implode('', $encodedItems);
    }

    /**
     * Encode a 24-bit search tree record.
     *
     * @param  int    $value the node number, empty marker, or data pointer
     * @return string the three encoded bytes
     */
    private function mmdbRecord24(int $value): string
    {
        return substr(pack('N', $value), 1, 3);
    }

    /**
     * Build the 32-node IPv4 search tree holding a single route to 8.8.8.8.
     *
     * Each node n follows bit n of the address: the matching branch walks to
     * the next node and the final node stores the data pointer; every
     * non-matching branch stores the empty marker (`nodeCount`), so all other
     * addresses resolve to "not found".
     *
     * @return string the 192-byte tree
     */
    private function searchTree(): string
    {
        $nodeCount = 32;
        $dataPointer = $nodeCount + 16;
        $packedAddress = inet_pton('8.8.8.8');

        if ($packedAddress === false) {
            throw new RuntimeException('Failed To Pack The Fixture Address');
        }

        $bytes = '';

        for ($node = 0; $node < $nodeCount; $node++) {
            $bit = (ord($packedAddress[$node >> 3]) >> (7 - ($node % 8))) & 1;

            $match = $node === $nodeCount - 1 ? $dataPointer : $node + 1;

            $left = $bit === 0 ? $match : $nodeCount;
            $right = $bit === 0 ? $nodeCount : $match;

            $bytes .= $this->mmdbRecord24($left).$this->mmdbRecord24($right);
        }

        return $bytes;
    }

    /**
     * Build the data section record for 8.8.8.8.
     *
     * @return string the encoded city/country map
     */
    private function dataRecord(): string
    {
        $city = $this->mmdbMap([
            'names' => $this->mmdbMap([
                'en' => $this->mmdbString('Mountain View'),
            ]),
        ]);

        $country = $this->mmdbMap([
            'iso_code' => $this->mmdbString('US'),
        ]);

        return $this->mmdbMap([
            'city' => $city,
            'country' => $country,
        ]);
    }

    /**
     * Build the metadata block, marker included.
     *
     * @return string the encoded metadata
     */
    private function metadata(): string
    {
        return "\xAB\xCD\xEFMaxMind.com".$this->mmdbMap([
            'binary_format_major_version' => $this->mmdbUint16(2),
            'binary_format_minor_version' => $this->mmdbUint16(0),
            'build_epoch' => $this->mmdbUint32(1735689600),
            'database_type' => $this->mmdbString('GeoLite2-City'),
            'description' => $this->mmdbMap([
                'en' => $this->mmdbString('Fixture'),
            ]),
            'ip_version' => $this->mmdbUint16(4),
            'languages' => $this->mmdbArray([$this->mmdbString('en')]),
            'node_count' => $this->mmdbUint32(32),
            'record_size' => $this->mmdbUint16(24),
        ]);
    }
}
