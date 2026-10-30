<?php

namespace statikbe\translate\helpers;

/**
 * Writes messages into a translation file (`return [ 'key' => 'value', … ];`) the way a developer would: an existing
 * entry gets its new value on its own line, a new one is added in alphabetical order when the file is sorted (else at
 * the end), and the rest of the file (comments, array syntax, indentation) stays as it is.
 */
class TranslationFile
{
    private const STRING = '(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")';

    /**
     * Returns the file contents with these messages set.
     *
     * @param string|null $contents The current file, null when there is none
     * @param array<string, string> $messages
     */
    public static function apply(?string $contents, array $messages): string
    {
        if ($contents === null || trim($contents) === '') {
            $lines = [];
            foreach ($messages as $message => $translation) {
                $lines[] = '    ' . self::literal((string)$message) . ' => ' . self::literal($translation) . ',';
            }
            return "<?php\n\nreturn [\n" . implode("\n", $lines) . ($lines ? "\n" : '') . "];\n";
        }

        foreach ($messages as $message => $translation) {
            $contents = self::set($contents, (string)$message, $translation);
        }

        return $contents;
    }

    /**
     * A PHP string literal, as var_export() writes it.
     */
    public static function literal(string $value): string
    {
        return var_export($value, true);
    }

    private static function set(string $contents, string $message, string $translation): string
    {
        // An existing entry: replace its value
        foreach (["'", '"'] as $quote) {
            $key = $quote === "'" ? self::literal($message) : '"' . addcslashes($message, "\"\\\$") . '"';
            $pattern = '/(' . preg_quote($key, '/') . '\s*=>\s*)' . self::STRING . '/s';
            if (preg_match($pattern, $contents)) {
                return (string)preg_replace_callback($pattern, fn(array $m) => $m[1] . self::literal($translation), $contents, 1);
            }
        }

        $line = self::literal($message) . ' => ' . self::literal($translation) . ',';

        // The entries that are on a line of their own: [line number => key]
        $lines = explode("\n", $contents);
        $entries = [];
        $indent = '    ';
        foreach ($lines as $i => $text) {
            if (preg_match('/^(\s*)(' . self::STRING . ')\s*=>\s*' . self::STRING . '\s*,?\s*$/', $text, $m)) {
                $entries[$i] = self::unquote($m[2]);
                $indent = $m[1];
            }
        }

        // Sorted file: insert before the first key that comes after it
        $keys = array_values($entries);
        $sorted = $keys;
        sort($sorted, SORT_STRING);
        if ($keys && $keys === $sorted) {
            foreach ($entries as $i => $key) {
                if (strcmp($key, $message) > 0) {
                    array_splice($lines, $i, 0, [$indent . $line]);
                    return implode("\n", $lines);
                }
            }
        }

        // Otherwise (or the last one): before the closing bracket of the returned array
        if (!preg_match('/(\]|\))\s*;\s*$/', $contents, $m, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException('Not a translation file: no closing bracket of the returned array.');
        }
        $closing = $m[0][1];
        $before = rtrim(substr($contents, 0, $closing));
        $last = substr($before, -1);
        if ($last !== ',' && $last !== '[' && $last !== '(') {
            $before .= ',';
        }

        return $before . "\n" . $indent . $line . "\n" . substr($contents, $closing);
    }

    private static function unquote(string $literal): string
    {
        $inner = substr($literal, 1, -1);
        if ($literal[0] === '"') {
            return stripcslashes($inner);
        }

        return strtr($inner, ['\\\\' => '\\', "\\'" => "'"]);
    }
}
