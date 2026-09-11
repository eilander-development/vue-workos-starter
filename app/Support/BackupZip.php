<?php

namespace App\Support;

use RuntimeException;

class BackupZip
{
    /** @var array<string, string> */
    private array $files = [];

    public function add(string $name, string $contents): void
    {
        $this->files[$name] = $contents;
    }

    public function get(string $name): ?string
    {
        return $this->files[$name] ?? null;
    }

    public function save(string $path): void
    {
        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && ! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $this->build());
        @chmod($path, 0666);
    }

    public static function open(string $path): self
    {
        $binary = @file_get_contents($path);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
        }

        $zip = new self;
        $zip->files = self::parse($binary);

        return $zip;
    }

    private function build(): string
    {
        $local = '';
        $central = '';
        $count = 0;

        foreach ($this->files as $name => $contents) {
            $nameBytes = $name;
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLen = strlen($nameBytes);
            $offset = strlen($local);

            $local .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0x0800, 0, 0, 0, $crc, $size, $size, $nameLen, 0);
            $local .= $nameBytes.$contents;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0x0800,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                $nameLen,
                0,
                0,
                0,
                0,
                0,
                $offset
            );
            $central .= $nameBytes;
            $count++;
        }

        $eocd = pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($central), strlen($local), 0);

        return $local.$central.$eocd;
    }

    /**
     * @return array<string, string>
     */
    private static function parse(string $binary): array
    {
        $eocd = strrpos($binary, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
        }

        $info = unpack('vdisk/vcdDisk/ventries/vtotal/VcdSize/VcdOffset', substr($binary, $eocd + 4, 16));
        if (! is_array($info)) {
            throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
        }

        $offset = (int) $info['cdOffset'];
        $total = (int) $info['total'];
        $files = [];

        for ($i = 0; $i < $total; $i++) {
            if (substr($binary, $offset, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
            }

            $header = unpack(
                'vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen/vcommentLen/vdisk/viattr/Veattr/Vlocal',
                substr($binary, $offset + 4, 42)
            );
            if (! is_array($header)) {
                throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
            }

            $nameLen = (int) $header['nameLen'];
            $extraLen = (int) $header['extraLen'];
            $commentLen = (int) $header['commentLen'];
            $name = substr($binary, $offset + 46, $nameLen);
            $local = (int) $header['local'];
            $method = (int) $header['method'];
            $compSize = (int) $header['comp'];

            if (substr($binary, $local, 4) !== "PK\x03\x04") {
                throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
            }

            $localHeader = unpack(
                'vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen',
                substr($binary, $local + 4, 26)
            );
            if (! is_array($localHeader)) {
                throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
            }

            $dataStart = $local + 30 + (int) $localHeader['nameLen'] + (int) $localHeader['extraLen'];
            if (($header['flags'] & 0x08) === 0x08 && $compSize === 0) {
                $compSize = (int) $localHeader['comp'];
            }
            $compressed = substr($binary, $dataStart, $compSize);
            $files[$name] = self::decompress($compressed, $method);
            $offset += 46 + $nameLen + $extraLen + $commentLen;
        }

        return $files;
    }

    private static function decompress(string $data, int $method): string
    {
        if ($method === 0) {
            return $data;
        }

        if ($method === 8) {
            $inflated = @gzinflate($data);
            if (! is_string($inflated)) {
                throw new RuntimeException('Dit bestand is geen geldige zip-backup.');
            }

            return $inflated;
        }

        throw new RuntimeException('Dit zip-bestand gebruikt een niet-ondersteunde compressie.');
    }
}
