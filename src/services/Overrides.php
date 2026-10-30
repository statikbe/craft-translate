<?php

namespace statikbe\translate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\i18n\PhpMessageSource;
use statikbe\translate\helpers\OverrideRules;
use statikbe\translate\helpers\TranslationFile;
use statikbe\translate\i18n\OverridablePhpMessageSource;
use Throwable;
use yii\caching\TagDependency;

/**
 * The translations editors changed, stored in the database on top of the translation files (when `storeInDatabase` is
 * on). Only values that differ from the default are stored; setting a value back to its default removes it.
 *
 * Every override is read once per request from the data cache (one entry for all of them, cleared on every change), so
 * a request with a warm cache does no query.
 */
class Overrides extends Component
{
    public const TABLE = '{{%translate_overrides}}';

    private const CACHE_KEY = 'translate.overrides.v1';
    private const CACHE_TAG = 'translate-overrides';

    /** @var array<string, array<string, array<string, array{translation: string, userId: int|null, dateUpdated: string}>>>|null */
    private ?array $all = null;

    /** @var array<string, array<string, string>> */
    private array $fileMessages = [];

    /**
     * Every override: [category][language][message] => row.
     *
     * @return array<string, array<string, array<string, array{translation: string, userId: int|null, dateUpdated: string}>>>
     */
    public function all(): array
    {
        if ($this->all !== null) {
            return $this->all;
        }

        try {
            $this->all = Craft::$app->getCache()->getOrSet(self::CACHE_KEY, function() {
                $all = [];
                $rows = (new Query())
                    ->select(['language', 'category', 'message', 'translation', 'userId', 'dateUpdated'])
                    ->from(self::TABLE)
                    ->all();
                foreach ($rows as $row) {
                    $all[$row['category']][$row['language']][(string)$row['message']] = [
                        'translation' => (string)$row['translation'],
                        'userId' => $row['userId'] !== null ? (int)$row['userId'] : null,
                        'dateUpdated' => (string)$row['dateUpdated'],
                    ];
                }
                return $all;
            }, null, new TagDependency(['tags' => [self::CACHE_TAG]]));
        } catch (Throwable $e) {
            // The table doesn't exist yet (the migration hasn't run): no overrides, and nothing cached
            Craft::warning('Translation overrides could not be read: ' . $e->getMessage(), __METHOD__);
            $this->all = [];
        }

        return $this->all;
    }

    /**
     * The overrides of one language (not of its generic language): [message => translation].
     *
     * @return array<string, string>
     */
    public function exact(string $category, string $language): array
    {
        if ($language === '') {
            return [];
        }

        return array_map(fn(array $row) => $row['translation'], $this->all()[$category][$language] ?? []);
    }

    /**
     * One override, or null.
     *
     * @return array{translation: string, userId: int|null, dateUpdated: string}|null
     */
    public function get(string $category, string $language, string $message): ?array
    {
        return $this->all()[$category][$language][$message] ?? null;
    }

    /**
     * The messages of the translation files only (a plugin's own files, then the site's translations folder).
     *
     * @return array<string, string>
     */
    public function fileMessages(string $category, string $language): array
    {
        $key = $category . '|' . $language;
        if (!isset($this->fileMessages[$key])) {
            $source = $this->messageSource($category);
            $this->fileMessages[$key] = $source ? array_map('strval', $source->fileMessages($category, $language)) : [];
        }

        return $this->fileMessages[$key];
    }

    /**
     * What a message shows without an override of this language: the files, plus the overrides of its generic language.
     *
     * @return array<string, string>
     */
    public function defaults(string $category, string $language): array
    {
        return OverrideRules::merge(
            $this->fileMessages($category, $language),
            $this->exact($category, OverrideRules::fallbackLanguage($language)),
            [],
        );
    }

    /**
     * Saves the values of the control panel: only those that differ from their default are stored.
     *
     * @param array<string|int, string> $values [message => value]
     * @return array{saved: int, removed: int}
     */
    public function save(string $category, string $language, array $values, ?int $userId): array
    {
        $defaults = $this->defaults($category, $language);
        $counts = ['saved' => 0, 'removed' => 0];

        foreach ($values as $message => $value) {
            $message = (string)$message;
            $existing = $this->get($category, $language, $message)['translation'] ?? null;
            switch (OverrideRules::action($message, (string)$value, $defaults, $existing)) {
                case OverrideRules::UPSERT:
                    $this->write($category, $language, $message, (string)$value, $userId);
                    $counts['saved']++;
                    break;
                case OverrideRules::DELETE:
                    $this->remove($category, $language, $message);
                    $counts['removed']++;
                    break;
            }
        }

        if ($counts['saved'] || $counts['removed']) {
            $this->invalidate();
        }

        return $counts;
    }

    /**
     * Removes an override, so the message shows its default again.
     */
    public function restore(string $category, string $language, string $message): bool
    {
        $removed = $this->remove($category, $language, $message);
        if ($removed) {
            $this->invalidate();
        }

        return $removed;
    }

    /**
     * Imports the files of an old translations folder (`<language>/<category>.php`): values that differ from the defaults
     * become overrides. Running it again changes nothing.
     *
     * @return array<int, array{language: string, category: string, message: string, result: string}>
     */
    public function importFiles(string $path, bool $dryRun = false): array
    {
        $report = [];
        $files = FileHelper::findFiles($path, ['only' => ['*.php'], 'recursive' => true]);
        sort($files);

        foreach ($files as $file) {
            $relative = substr($file, strlen(rtrim($path, '/')) + 1);
            $parts = explode('/', $relative);
            if (count($parts) !== 2 || !preg_match('/^[a-z0-9_-]+$/i', $parts[0])) {
                continue;
            }
            [$language, $name] = $parts;
            $category = substr($name, 0, -4);
            $messages = self::readFile($file);
            $defaults = $this->defaults($category, $language);

            foreach ($messages as $message => $value) {
                $message = (string)$message;
                if (!is_string($value)) {
                    continue;
                }
                $existing = $this->get($category, $language, $message)['translation'] ?? null;
                $result = OverrideRules::classifyImport($message, $value, $defaults, $existing);
                if (in_array($result, [OverrideRules::IMPORT_OVERRIDE, OverrideRules::IMPORT_UNKNOWN], true) && !$dryRun) {
                    $this->write($category, $language, $message, $value, null);
                }
                $report[] = ['language' => $language, 'category' => $category, 'message' => $message, 'result' => $result];
            }
        }

        if (!$dryRun) {
            $this->invalidate();
        }

        return $report;
    }

    /**
     * The overrides that are no longer needed: the same as their default, or (for the site's own categories) a key that
     * is neither in the translation files nor used in the templates any more.
     *
     * @param array<string, string[]> $usedKeys [category => keys used in the templates], for the site's own categories
     * @return array<int, array{language: string, category: string, message: string, reason: string}>
     */
    public function prune(array $usedKeys, bool $dryRun = false): array
    {
        $removed = [];
        foreach ($this->all() as $category => $languages) {
            foreach ($languages as $language => $rows) {
                $defaults = $this->defaults((string)$category, (string)$language);
                $files = $this->fileMessages((string)$category, (string)$language);
                foreach ($rows as $message => $row) {
                    $message = (string)$message;
                    $reason = null;
                    if ($row['translation'] === '' || $row['translation'] === OverrideRules::baseline($message, $defaults)) {
                        $reason = 'default';
                    } elseif (isset($usedKeys[$category]) && !array_key_exists($message, $files) && !in_array($message, $usedKeys[$category], true)) {
                        $reason = 'unused';
                    }
                    if ($reason === null) {
                        continue;
                    }
                    if (!$dryRun) {
                        $this->remove((string)$category, (string)$language, $message);
                    }
                    $removed[] = ['language' => (string)$language, 'category' => (string)$category, 'message' => $message, 'reason' => $reason];
                }
            }
        }

        if ($removed && !$dryRun) {
            $this->invalidate();
        }

        return $removed;
    }

    /**
     * Writes the overrides into the translation files of the site (`translations/<language>/<category>.php`) and removes
     * them from the database: for a developer who changed translations locally through the control panel.
     *
     * @return array<string, int> [file => number of messages written]
     */
    public function promote(): array
    {
        $written = [];
        $basePath = Craft::$app->getPath()->getSiteTranslationsPath();

        foreach ($this->all() as $category => $languages) {
            foreach ($languages as $language => $rows) {
                $messages = array_map(fn(array $row) => $row['translation'], $rows);
                $file = $basePath . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . $category . '.php';
                $contents = is_file($file) ? (string)file_get_contents($file) : null;
                $new = TranslationFile::apply($contents, $messages);

                // Check the result before replacing the file
                $check = self::evaluate($new);
                foreach ($messages as $message => $translation) {
                    if (($check[$message] ?? null) !== $translation) {
                        throw new \RuntimeException("Could not write \"$message\" into $file; nothing was changed in that file.");
                    }
                }

                FileHelper::writeToFile($file, $new);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($file, true);
                }
                foreach (array_keys($messages) as $message) {
                    $this->remove((string)$category, (string)$language, (string)$message);
                }
                $written[$file] = count($messages);
            }
        }

        $this->invalidate();

        return $written;
    }

    /**
     * Clears the cached overrides (after every change).
     */
    public function invalidate(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);
        $this->all = null;
    }

    /**
     * Uses the overridable message source for every translation category that reads PHP files, except Craft's own
     * (`app`, `yii`): the site's categories (`site`, `website_name`, …) and the plugins' (`formie`, …).
     */
    public function swapMessageSources(): void
    {
        $i18n = Craft::$app->getI18n();
        foreach ($i18n->translations as $category => $source) {
            if (in_array($category, ['app', 'yii'], true) || str_contains((string)$category, '*')) {
                continue;
            }
            if (is_array($source) && in_array(ltrim((string)($source['class'] ?? ''), '\\'), [PhpMessageSource::class, \yii\i18n\PhpMessageSource::class], true)) {
                $i18n->translations[$category]['class'] = OverridablePhpMessageSource::class;
            } elseif (is_object($source) && in_array(get_class($source), [PhpMessageSource::class, \yii\i18n\PhpMessageSource::class], true)) {
                $i18n->translations[$category] = new OverridablePhpMessageSource(self::sourceConfig($source));
            }
        }
    }

    /**
     * The names of these users, for the "changed by" line.
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    public function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return [];
        }
        $names = [];
        foreach (User::find()->id($ids)->status(null)->all() as $user) {
            $names[$user->id] = $user->getName();
        }

        return $names;
    }

    private function write(string $category, string $language, string $message, string $translation, ?int $userId): void
    {
        Db::upsert(self::TABLE, [
            'language' => $language,
            'category' => $category,
            'messageHash' => sha1($message),
            'message' => $message,
            'translation' => $translation,
            'userId' => $userId,
        ], [
            'translation' => $translation,
            'userId' => $userId,
        ]);

        if ($this->all !== null) {
            $this->all[$category][$language][$message] = ['translation' => $translation, 'userId' => $userId, 'dateUpdated' => (string)Db::prepareDateForDb(new \DateTime())];
        }
    }

    private function remove(string $category, string $language, string $message): bool
    {
        $deleted = Db::delete(self::TABLE, [
            'language' => $language,
            'category' => $category,
            'messageHash' => sha1($message),
        ]);
        if ($this->all !== null) {
            unset($this->all[$category][$language][$message]);
        }

        return $deleted > 0;
    }

    /**
     * A message source that reads the files of a category, built from its configuration (never cached messages).
     */
    private function messageSource(string $category): ?OverridablePhpMessageSource
    {
        $i18n = Craft::$app->getI18n();
        $source = $i18n->translations[$category] ?? null;
        if ($source === null) {
            try {
                $source = $i18n->getMessageSource($category);
            } catch (Throwable) {
                return null;
            }
        }

        if (is_array($source)) {
            $config = $source;
            unset($config['class']);
        } elseif ($source instanceof \yii\i18n\PhpMessageSource) {
            $config = self::sourceConfig($source);
        } else {
            return null;
        }

        return new OverridablePhpMessageSource($config);
    }

    /**
     * @return array<string, mixed>
     */
    private static function sourceConfig(\yii\i18n\PhpMessageSource $source): array
    {
        $config = [
            'basePath' => $source->basePath,
            'sourceLanguage' => $source->sourceLanguage,
            'forceTranslation' => $source->forceTranslation,
            'fileMap' => $source->fileMap,
        ];
        if ($source instanceof PhpMessageSource) {
            $config['allowOverrides'] = $source->allowOverrides;
        }

        return $config;
    }

    /**
     * @return array<string|int, mixed>
     */
    private static function readFile(string $file): array
    {
        $messages = include $file;
        return is_array($messages) ? $messages : [];
    }

    /**
     * @return array<string|int, mixed>
     */
    private static function evaluate(string $php): array
    {
        $temp = tempnam(Craft::$app->getPath()->getTempPath(), 'translate');
        file_put_contents($temp, $php);
        try {
            return self::readFile($temp);
        } finally {
            @unlink($temp);
        }
    }
}
