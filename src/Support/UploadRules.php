<?php

namespace Modules\Custom\MakerBids\Support;

class UploadRules
{
    public const COLLECTION_IMAGES = 'images';

    public const COLLECTION_ARCHIVES = 'archives';

    public const COLLECTION_LOGOS = 'logos';

    /** Post-award delivery payloads (result files). */
    public const COLLECTION_DELIVERY = 'delivery';

    public const COLLECTIONS = [
        self::COLLECTION_IMAGES,
        self::COLLECTION_ARCHIVES,
        self::COLLECTION_LOGOS,
        self::COLLECTION_DELIVERY,
    ];

    /** Default TTL for delivery files (days). */
    public const DELIVERY_TTL_DAYS = 30;

    public const LOGO_MAX_PX = 512;

    public const LOGO_MAX_FILES = 1;

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const ARCHIVE_EXTENSIONS = ['zip', 'tar', 'gz', 'tgz', '7z', 'rar', 'bz2', 'xz'];

    public const DELIVERY_EXTENSIONS = [
        'zip', 'tar', 'gz', 'tgz', '7z', 'rar', 'bz2', 'xz',
        'stl', '3mf', 'obj', 'pdf', 'png', 'jpg', 'jpeg', 'webp',
        'step', 'stp', 'gcode', 'fbx', 'dwg',
    ];

    public const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp';

    public const ARCHIVE_ACCEPT = '.zip,.tar,.gz,.tgz,.7z,.rar,.bz2,.xz';

    public const DELIVERY_ACCEPT = '.zip,.stl,.3mf,.obj,.pdf,.png,.jpg,.jpeg,.webp,.tar,.gz,.7z,.step,.stp';

    public const IMAGE_MAX_MB = 10;

    public const ARCHIVE_MAX_MB = 50;

    public const DELIVERY_MAX_MB = 50;

    public const PROVIDED_EXTENSIONS = ['STL', 'OBJ', '3MF', 'FBX', 'PDF', 'STEP', 'STP', 'GCODE', 'DWG'];

    public static function isAllowedCollection(string $collection): bool
    {
        return in_array($collection, self::COLLECTIONS, true);
    }

    public static function isDeliveryCollection(string $collection): bool
    {
        return $collection === self::COLLECTION_DELIVERY;
    }

    public static function isProtectedCollection(string $collection): bool
    {
        return $collection === self::COLLECTION_ARCHIVES || $collection === self::COLLECTION_DELIVERY;
    }

    public static function extensionsFor(string $collection): array
    {
        if ($collection === self::COLLECTION_ARCHIVES) {
            return self::ARCHIVE_EXTENSIONS;
        }
        if ($collection === self::COLLECTION_DELIVERY) {
            return self::DELIVERY_EXTENSIONS;
        }

        return self::IMAGE_EXTENSIONS;
    }

    public static function isImageCollection(string $collection): bool
    {
        return $collection === self::COLLECTION_IMAGES || $collection === self::COLLECTION_LOGOS;
    }

    public static function maxKilobytes(string $collection): int
    {
        if ($collection === self::COLLECTION_ARCHIVES || $collection === self::COLLECTION_DELIVERY) {
            return self::ARCHIVE_MAX_MB * 1024;
        }

        return self::IMAGE_MAX_MB * 1024;
    }

    public static function isWithinLogoDimensions(int $width, int $height): bool
    {
        return $width > 0 && $height > 0 && $width <= self::LOGO_MAX_PX && $height <= self::LOGO_MAX_PX;
    }

    public static function isAllowedExtension(string $collection, string $filename): bool
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }

        if (in_array($collection, [self::COLLECTION_ARCHIVES, self::COLLECTION_DELIVERY], true)
            && str_ends_with(strtolower($filename), '.tar.gz')) {
            return true;
        }

        return in_array($ext, self::extensionsFor($collection), true);
    }

    /**
     * @param  list<string>|null  $allowed  null = built-in PROVIDED_EXTENSIONS
     */
    public static function isAllowedProvidedExtension(string $ext, ?array $allowed = null): bool
    {
        $allowed = $allowed ?? self::PROVIDED_EXTENSIONS;

        return in_array(strtoupper($ext), $allowed, true);
    }

    /** @var list<string> */
    private const EXT_TOKEN_JUNK = [
        'TRUE', 'FALSE', 'YES', 'NO', 'ON', 'OFF', 'NULL', 'UNDEFINED',
        'KRW', 'USD', 'EUR', 'JPY', 'CNY', 'GBP',
        'KR', 'US', 'EN', 'JP', 'CN', 'KO',
    ];

    /**
     * Normalize a single extension token; returns null when rejected.
     */
    public static function normalizeExtToken(mixed $raw): ?string
    {
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['label'] ?? $raw['ext'] ?? $raw['slug'] ?? null;
            if (is_array($raw) || is_object($raw)) {
                return null;
            }
        } elseif (is_object($raw)) {
            $arr = (array) $raw;
            $raw = $arr['value'] ?? $arr['label'] ?? $arr['ext'] ?? $arr['slug'] ?? null;
            if (is_array($raw) || is_object($raw)) {
                return null;
            }
        }
        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }
        $value = strtoupper(ltrim(trim((string) $raw), '.'));
        if ($value === '' || str_starts_with($value, '[OBJECT') || $value === 'OBJECT]') {
            return null;
        }
        if (strlen($value) < 2 || ctype_digit($value) || in_array($value, self::EXT_TOKEN_JUNK, true)) {
            return null;
        }
        if (! preg_match('/^[A-Z0-9]{2,16}$/', $value)) {
            return null;
        }

        return $value;
    }

    private static function isBoolishExtValue(mixed $v): bool
    {
        if (is_bool($v) || $v === 0 || $v === 1 || $v === '0' || $v === '1') {
            return true;
        }
        if (is_string($v)) {
            $u = strtolower(trim($v));

            return in_array($u, ['on', 'off', 'true', 'false', 'yes', 'no'], true);
        }

        return false;
    }

    private static function isTruthyExtValue(mixed $v): bool
    {
        if ($v === true || $v === 1 || $v === '1') {
            return true;
        }
        if (is_string($v)) {
            $u = strtolower(trim($v));

            return in_array($u, ['on', 'true', 'yes'], true);
        }

        return false;
    }

    /**
     * Associative map whose keys look like extensions and values are boolean-ish.
     *
     * @param  array<mixed, mixed>  $raw
     */
    private static function isExtensionMap(array $raw): bool
    {
        if ($raw === [] || array_is_list($raw)) {
            return false;
        }
        foreach ($raw as $key => $value) {
            $tok = self::normalizeExtToken(is_string($key) || is_int($key) ? (string) $key : null);
            if ($tok === null || ! self::isBoolishExtValue($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse a settings textarea / CSV / array into uppercase extension tokens.
     *
     * Never treats arbitrary associative object values (e.g. settings.general) as extensions.
     * Extension maps use KEYS (STL => true), not values.
     *
     * @param  mixed  $raw
     * @return list<string>
     */
    public static function parseExtensionList(mixed $raw): array
    {
        $items = [];
        if (is_string($raw) && trim($raw) !== '') {
            $raw = preg_split('/[,\s;|]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        if (self::isExtensionMap($raw)) {
            foreach ($raw as $key => $value) {
                if (! self::isTruthyExtValue($value)) {
                    continue;
                }
                $tok = self::normalizeExtToken((string) $key);
                if ($tok !== null && ! in_array($tok, $items, true)) {
                    $items[] = $tok;
                }
            }

            return $items;
        }

        // Single option-shaped assoc: {value|label|ext|slug}
        if (! array_is_list($raw)) {
            if (array_key_exists('value', $raw) || array_key_exists('label', $raw)
                || array_key_exists('ext', $raw) || array_key_exists('slug', $raw)) {
                $tok = self::normalizeExtToken($raw);
                return $tok !== null ? [$tok] : [];
            }

            // Arbitrary associative map (settings blob, form state) — do not iterate values.
            return [];
        }

        foreach ($raw as $item) {
            if (is_array($item) && ! array_is_list($item) && self::isExtensionMap($item)) {
                foreach (self::parseExtensionList($item) as $tok) {
                    if (! in_array($tok, $items, true)) {
                        $items[] = $tok;
                    }
                }
                continue;
            }
            $tok = self::normalizeExtToken($item);
            if ($tok !== null && ! in_array($tok, $items, true)) {
                $items[] = $tok;
            }
        }

        return $items;
    }

    /**
     * @param  mixed  $raw
     * @param  list<string>|null  $allowed
     * @return list<string>
     */
    public static function normalizeProvidedExtensions(mixed $raw, ?array $allowed = null): array
    {
        $allowed = $allowed ?? self::PROVIDED_EXTENSIONS;
        $items = [];
        foreach (self::parseExtensionList($raw) as $value) {
            if (self::isAllowedProvidedExtension($value, $allowed) && ! in_array($value, $items, true)) {
                $items[] = $value;
            }
        }

        return $items;
    }

    public static function extensionFieldKey(string $ext): string
    {
        return 'ext_'.strtolower($ext);
    }

    public static function deliveryExpiresAt(?\DateTimeInterface $from = null): \DateTimeInterface
    {
        $base = $from
            ? \DateTimeImmutable::createFromInterface($from)
            : new \DateTimeImmutable('now');

        return $base->modify('+'.self::DELIVERY_TTL_DAYS.' days');
    }
}
