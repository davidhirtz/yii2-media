<?php

declare(strict_types=1);

namespace Hirtz\Media\Images;

use yii\base\InvalidArgumentException;

/**
 * Adds an ICC profile, EXIF and XMP to a WebP an encoder wrote without them (GD), as the extended format's chunks.
 */
final class WebpContainer
{
    private const int FLAG_ICC = 0x20;
    private const int FLAG_ALPHA = 0x10;
    private const int FLAG_EXIF = 0x08;
    private const int FLAG_XMP = 0x04;

    /**
     * @param array<string, string> $profiles Imagick's profile names (`icc`, `exif`, `xmp`) to their data
     */
    public static function addProfiles(string $webp, array $profiles, int $width, int $height): string
    {
        $icc = $profiles['icc'] ?? '';
        $exif = $profiles['exif'] ?? '';
        $xmp = $profiles['xmp'] ?? '';

        if ($icc === '' && $exif === '' && $xmp === '') {
            return $webp;
        }

        if (strlen($webp) < 12 || !str_starts_with($webp, 'RIFF') || substr($webp, 8, 4) !== 'WEBP') {
            throw new InvalidArgumentException('The data is not a WebP image.');
        }

        $flags = 0;
        $image = '';
        $offset = 12;

        while ($offset + 8 <= strlen($webp)) {
            $fourCc = substr($webp, $offset, 4);
            $length = unpack('V', substr($webp, $offset + 4, 4))[1] ?? 0;
            $data = substr($webp, $offset + 8, $length);
            $offset += 8 + $length + ($length % 2);

            $flags |= match ($fourCc) {
                'VP8X' => ord($data[0] ?? "\0") & self::FLAG_ALPHA,
                'ALPH' => self::FLAG_ALPHA,
                // A lossless bitstream says in bit 28 of its header whether it uses its alpha
                'VP8L' => (unpack('V', substr($data, 1, 4))[1] ?? 0) & 0x10000000 ? self::FLAG_ALPHA : 0,
                default => 0,
            };

            if (in_array($fourCc, ['ALPH', 'VP8 ', 'VP8L'], true)) {
                $image .= self::chunk($fourCc, $data);
            }
        }

        $flags |= ($icc !== '' ? self::FLAG_ICC : 0)
            | ($exif !== '' ? self::FLAG_EXIF : 0)
            | ($xmp !== '' ? self::FLAG_XMP : 0);

        $canvas = substr(pack('V', $width - 1), 0, 3) . substr(pack('V', $height - 1), 0, 3);

        $chunks = self::chunk('VP8X', pack('C', $flags) . "\0\0\0" . $canvas)
            . ($icc !== '' ? self::chunk('ICCP', $icc) : '')
            . $image
            . ($exif !== '' ? self::chunk('EXIF', $exif) : '')
            . ($xmp !== '' ? self::chunk('XMP ', $xmp) : '');

        return 'RIFF' . pack('V', strlen($chunks) + 4) . 'WEBP' . $chunks;
    }

    private static function chunk(string $fourCc, string $data): string
    {
        return $fourCc . pack('V', strlen($data)) . $data . (strlen($data) % 2 ? "\0" : '');
    }
}
