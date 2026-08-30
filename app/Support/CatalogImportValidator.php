<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Validates catalog bulk-import files by extension (and xlsx zip signature),
 * not strict MIME — offline Windows/LAN often sends application/octet-stream.
 */
final class CatalogImportValidator
{
    private const ALLOWED_EXTENSIONS = ['xlsx', 'csv', 'txt'];

    public static function extension(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== '') {
            return $ext;
        }

        return strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
    }

    public static function isAllowed(UploadedFile $file): bool
    {
        $ext = self::extension($file);
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        if ($ext === 'xlsx') {
            return self::hasXlsxZipSignature($file);
        }

        return true;
    }

    private static function hasXlsxZipSignature(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return false;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === 'PK'."\x03\x04";
    }
}
