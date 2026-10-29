<?php

namespace App\Support;

use RuntimeException;

/**
 * Minimal streaming ZIP writer for already-compressed QR PNG files.
 *
 * Why this exists:
 * - GhanaDAMS can have 3,000+ QR PNG files in one Organization.
 * - Building a full temporary ZIP before sending any response can trigger
 *   nginx/FastCGI gateway timeouts.
 * - This writer emits a valid ZIP progressively to the browser.
 *
 * Files are stored (not deflated again), which is efficient for PNG images.
 *
 * Standard ZIP32 is sufficient for the GhanaDAMS QR workload. The class
 * explicitly rejects Zip64-sized archives instead of silently corrupting them.
 */
class StreamingZipWriter
{
    private int $offset = 0;

    /** @var array<int, array<string, int|string>> */
    private array $entries = [];

    private int $flushEveryFiles;

    private int $fileCounter = 0;

    public function __construct(int $flushEveryFiles = 10)
    {
        $this->flushEveryFiles = max(1, $flushEveryFiles);
    }

    public function addFileFromPath(string $entryName, string $absolutePath): void
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("ZIP source file is not readable: {$absolutePath}");
        }

        $size = filesize($absolutePath);

        if ($size === false) {
            throw new RuntimeException("Could not determine ZIP source size: {$absolutePath}");
        }

        $this->guardZip32($size);

        $crcHex = hash_file('crc32b', $absolutePath);

        if ($crcHex === false) {
            throw new RuntimeException("Could not calculate CRC32: {$absolutePath}");
        }

        $crc = (int) hexdec($crcHex);
        [$dosTime, $dosDate] = $this->dosDateTime((int) (@filemtime($absolutePath) ?: time()));

        $entryName = $this->normalizeEntryName($entryName);
        $nameBytes = $entryName;

        $localOffset = $this->offset;

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,     // local file header signature
            20,             // version needed
            0,              // general purpose flags
            0,              // compression method: STORE
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            strlen($nameBytes),
            0               // extra field length
        );

        $this->write($localHeader);
        $this->write($nameBytes);

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Could not open ZIP source file: {$absolutePath}");
        }

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException("Could not read ZIP source file: {$absolutePath}");
                }

                if ($chunk !== '') {
                    $this->write($chunk);
                }
            }
        } finally {
            fclose($handle);
        }

        $this->entries[] = [
            'name' => $entryName,
            'crc' => $crc,
            'size' => $size,
            'time' => $dosTime,
            'date' => $dosDate,
            'offset' => $localOffset,
        ];

        $this->fileCounter++;

        if ($this->fileCounter % $this->flushEveryFiles === 0) {
            $this->flush();
        }
    }

    public function addString(string $entryName, string $contents): void
    {
        $size = strlen($contents);
        $this->guardZip32($size);

        $crc = (int) hexdec(hash('crc32b', $contents));
        [$dosTime, $dosDate] = $this->dosDateTime(time());

        $entryName = $this->normalizeEntryName($entryName);
        $localOffset = $this->offset;

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            strlen($entryName),
            0
        );

        $this->write($localHeader);
        $this->write($entryName);
        $this->write($contents);

        $this->entries[] = [
            'name' => $entryName,
            'crc' => $crc,
            'size' => $size,
            'time' => $dosTime,
            'date' => $dosDate,
            'offset' => $localOffset,
        ];

        $this->fileCounter++;
    }

    public function finish(): void
    {
        $entryCount = count($this->entries);

        if ($entryCount > 0xffff) {
            throw new RuntimeException(
                'This ZIP contains more than 65,535 files and requires Zip64.'
            );
        }

        $centralOffset = $this->offset;

        foreach ($this->entries as $entry) {
            $name = (string) $entry['name'];

            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,              // central directory signature
                20,                      // version made by
                20,                      // version needed
                0,                       // flags
                0,                       // compression: STORE
                (int) $entry['time'],
                (int) $entry['date'],
                (int) $entry['crc'],
                (int) $entry['size'],
                (int) $entry['size'],
                strlen($name),
                0,                       // extra length
                0,                       // comment length
                0,                       // disk number
                0,                       // internal attributes
                0,                       // external attributes
                (int) $entry['offset']
            );

            $this->write($centralHeader);
            $this->write($name);
        }

        $centralSize = $this->offset - $centralOffset;

        $this->guardZip32($centralOffset);
        $this->guardZip32($centralSize);

        $endOfCentralDirectory = pack(
            'VvvvvVVv',
            0x06054b50,
            0,                       // disk number
            0,                       // central dir start disk
            $entryCount,
            $entryCount,
            $centralSize,
            $centralOffset,
            0                        // comment length
        );

        $this->write($endOfCentralDirectory);
        $this->flush();
    }

    private function write(string $bytes): void
    {
        echo $bytes;
        $this->offset += strlen($bytes);

        $this->guardZip32($this->offset);
    }

    private function normalizeEntryName(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        $name = ltrim($name, '/');

        if ($name === '' || str_contains($name, '../')) {
            throw new RuntimeException('Invalid ZIP entry name.');
        }

        return $name;
    }

    /**
     * Convert Unix time to ZIP/DOS date and time fields.
     *
     * @return array{0:int,1:int}
     */
    private function dosDateTime(int $timestamp): array
    {
        $parts = getdate($timestamp);

        $year = min(2107, max(1980, (int) $parts['year']));
        $month = min(12, max(1, (int) $parts['mon']));
        $day = min(31, max(1, (int) $parts['mday']));
        $hour = min(23, max(0, (int) $parts['hours']));
        $minute = min(59, max(0, (int) $parts['minutes']));
        $second = min(59, max(0, (int) $parts['seconds']));

        $dosTime = ($hour << 11)
            | ($minute << 5)
            | intdiv($second, 2);

        $dosDate = (($year - 1980) << 9)
            | ($month << 5)
            | $day;

        return [$dosTime, $dosDate];
    }

    private function guardZip32(int $value): void
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new RuntimeException(
                'The QR archive exceeded the standard ZIP32 size limit.'
            );
        }
    }

    private function flush(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}
