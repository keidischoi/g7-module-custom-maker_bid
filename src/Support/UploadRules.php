<?php

namespace Modules\Custom\MakerBid\Support;

class UploadRules
{
    public const COLLECTION_IMAGES = 'images';

    public const COLLECTION_ARCHIVES = 'archives';

    public const COLLECTIONS = [self::COLLECTION_IMAGES, self::COLLECTION_ARCHIVES];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const ARCHIVE_EXTENSIONS = ['zip', 'tar', 'gz', 'tgz', '7z', 'rar', 'bz2', 'xz'];

    public const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp';

    public const ARCHIVE_ACCEPT = '.zip,.tar,.gz,.tgz,.7z,.rar,.bz2,.xz';

    public const IMAGE_MAX_MB = 10;

    public const ARCHIVE_MAX_MB = 50;

    public const PROVIDED_EXTENSIONS = ['STL', '3MF', 'OBJ', 'STEP', 'STP', 'GCODE', 'FBX'];

    public static function isAllowedCollection(string $collection): bool
    {
        return in_array($collection, self::COLLECTIONS, true);
    }

    public static function extensionsFor(string $collection): array
    {
        return $collection === self::COLLECTION_ARCHIVES
            ? self::ARCHIVE_EXTENSIONS
            : self::IMAGE_EXTENSIONS;
    }

    public static function maxKilobytes(string $collection): int
    {
        $mb = $collection === self::COLLECTION_ARCHIVES ? self::ARCHIVE_MAX_MB : self::IMAGE_MAX_MB;

        return $mb * 1024;
    }

    public static function isAllowedExtension(string $collection, string $filename): bool
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        if ($collection === self::COLLECTION_ARCHIVES && str_ends_with(strtolower($filename), '.tar.gz')) {
            return true;
        }

        return in_array($ext, self::extensionsFor($collection), true);
    }

    public static function isAllowedProvidedExtension(string $ext): bool
    {
        return in_array(strtoupper($ext), self::PROVIDED_EXTENSIONS, true);
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    public static function normalizeProvidedExtensions(mixed $raw): array
    {
        $items = [];
        if (is_string($raw) && $raw !== '') {
            $raw = preg_split('/[,\s]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }
        foreach ($raw as $item) {
            if (! is_string($item) && ! is_int($item)) {
                continue;
            }
            $value = strtoupper(trim((string) $item));
            if ($value !== '' && self::isAllowedProvidedExtension($value) && ! in_array($value, $items, true)) {
                $items[] = $value;
            }
        }

        return $items;
    }
}
