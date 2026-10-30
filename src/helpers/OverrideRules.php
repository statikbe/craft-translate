<?php

namespace statikbe\translate\helpers;

/**
 * The rules for overrides, without Craft: what the default of a message is, what a save or an import does with a value.
 *
 * "Defaults" are the messages of the translation files (and, for a language like nl-BE, the overrides of its generic
 * language nl): everything below the layer an editor changes.
 */
class OverrideRules
{
    public const NONE = 'none';
    public const UPSERT = 'upsert';
    public const DELETE = 'delete';

    public const IMPORT_EMPTY = 'empty';
    public const IMPORT_DEFAULT = 'default';
    public const IMPORT_UNCHANGED = 'unchanged';
    public const IMPORT_OVERRIDE = 'override';
    public const IMPORT_UNKNOWN = 'unknown';

    /**
     * The value a message shows without an override: its default, or the message itself (Craft's forceTranslation).
     *
     * @param array<string, string> $defaults
     */
    public static function baseline(string $message, array $defaults): string
    {
        $default = $defaults[$message] ?? null;
        return is_string($default) && $default !== '' ? $default : $message;
    }

    /**
     * What saving a value from the control panel does.
     *
     * - empty, or the same as the default: no override (an existing one is deleted, so it shows the default again);
     * - the same as the existing override: nothing;
     * - otherwise: store the override.
     *
     * @param array<string, string> $defaults
     */
    public static function action(string $message, string $value, array $defaults, ?string $existing): string
    {
        if ($value === '' || $value === self::baseline($message, $defaults)) {
            return $existing !== null ? self::DELETE : self::NONE;
        }

        return $value === $existing ? self::NONE : self::UPSERT;
    }

    /**
     * What the one-time import of an old translations folder does with a value.
     *
     * @param array<string, string> $defaults
     */
    public static function classifyImport(string $message, string $value, array $defaults, ?string $existing): string
    {
        if ($value === '') {
            return self::IMPORT_EMPTY;
        }
        if ($value === self::baseline($message, $defaults)) {
            return self::IMPORT_DEFAULT;
        }
        if ($value === $existing) {
            return self::IMPORT_UNCHANGED;
        }

        return array_key_exists($message, $defaults) ? self::IMPORT_OVERRIDE : self::IMPORT_UNKNOWN;
    }

    /**
     * The generic language of a language (nl for nl-BE), or '' when it is generic itself.
     */
    public static function fallbackLanguage(string $language): string
    {
        $fallback = substr($language, 0, 2);
        return $fallback !== $language ? $fallback : '';
    }

    /**
     * The messages a language shows: the files, then the overrides of the generic language (nl for nl-BE), then the
     * overrides of the language itself. Empty overrides are ignored.
     *
     * @param array<string, string> $files
     * @param array<string, string> $fallbackOverrides
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function merge(array $files, array $fallbackOverrides, array $overrides): array
    {
        foreach ([$fallbackOverrides, $overrides] as $layer) {
            foreach ($layer as $message => $translation) {
                if ($translation !== '') {
                    $files[$message] = $translation;
                }
            }
        }

        return $files;
    }
}
