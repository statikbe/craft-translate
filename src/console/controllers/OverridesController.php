<?php

namespace statikbe\translate\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use statikbe\translate\helpers\OverrideRules;
use statikbe\translate\Translate;
use yii\console\ExitCode;

/**
 * The translations editors changed, stored in the database (`storeInDatabase`).
 */
class OverridesController extends Controller
{
    /** @var string|null Only this language (e.g. nl-BE) */
    public ?string $language = null;

    /** @var string|null Only this category (e.g. website_name) */
    public ?string $category = null;

    /** @var bool Show what would change, change nothing */
    public bool $dryRun = false;

    /** @var bool Also outside dev mode */
    public bool $force = false;

    /** @var string|null The old translations folder to import (`<language>/<category>.php`) */
    public ?string $from = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        return match ($actionID) {
            'list' => array_merge($options, ['language', 'category']),
            'prune' => array_merge($options, ['dryRun']),
            'promote' => array_merge($options, ['force']),
            'import-files' => array_merge($options, ['from', 'dryRun']),
            default => $options,
        };
    }

    /**
     * Lists the translations editors changed: the key, its default and the override.
     */
    public function actionList(): int
    {
        $overrides = Translate::getInstance()->overrides;
        $rows = [];
        $userIds = [];
        foreach ($overrides->all() as $category => $languages) {
            if ($this->category !== null && $category !== $this->category) {
                continue;
            }
            foreach ($languages as $language => $messages) {
                if ($this->language !== null && $language !== $this->language) {
                    continue;
                }
                $defaults = $overrides->defaults((string)$category, (string)$language);
                foreach ($messages as $message => $row) {
                    $rows[] = [(string)$language, (string)$category, (string)$message, OverrideRules::baseline((string)$message, $defaults), $row['translation'], $row['userId'], $row['dateUpdated']];
                    $userIds[] = $row['userId'];
                }
            }
        }

        if (!$rows) {
            $this->stdout('No translations changed by editors.' . PHP_EOL);
            return ExitCode::OK;
        }

        $names = $overrides->userNames(array_filter($userIds));
        $this->table(
            ['Language', 'Category', 'Key', 'Default', 'Override', 'By', 'Changed (UTC)'],
            array_map(fn(array $r) => [$r[0], $r[1], self::short($r[2]), self::short($r[3]), self::short($r[4]), $r[5] !== null ? ($names[$r[5]] ?? '#' . $r[5]) : '', $r[6]], $rows),
        );
        $this->stdout(count($rows) . ' override(s).' . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Removes the overrides that are no longer needed: the same as their default, or a key of the site's own categories
     * that is neither in the translation files nor used in the templates any more.
     */
    public function actionPrune(): int
    {
        $plugin = Translate::getInstance();
        $usedKeys = [];
        $used = $plugin->translate->usedKeys();
        foreach ($plugin->translate->getSiteCategories() as $category) {
            $usedKeys[$category] = $used[$category] ?? [];
        }

        $removed = $plugin->overrides->prune($usedKeys, $this->dryRun);
        foreach ($removed as $row) {
            $this->stdout(sprintf("%s %s/%s: %s (%s)\n", $this->dryRun ? 'Would remove' : 'Removed', $row['language'], $row['category'], self::short($row['message']), $row['reason'] === 'default' ? 'same as the default' : 'not used any more'));
        }
        $this->stdout(count($removed) . ' override(s) ' . ($this->dryRun ? 'would be removed.' : 'removed.') . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Writes the overrides into the translation files (`translations/<language>/<category>.php`) and removes them from the
     * database. For developers, after changing translations locally in the control panel; only in dev mode (or --force).
     */
    public function actionPromote(): int
    {
        if (!Craft::$app->getConfig()->getGeneral()->devMode && !$this->force) {
            $this->stderr('This writes into the translation files: run it locally in dev mode, or add --force.' . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $written = Translate::getInstance()->overrides->promote();
        foreach ($written as $file => $count) {
            $this->stdout("$file: $count message(s)" . PHP_EOL);
        }
        $this->stdout($written ? 'Commit the translation files.' . PHP_EOL : 'No translations changed by editors.' . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Imports an old translations folder once (e.g. the shared `translations` folder of a server): every value that differs
     * from the default in the code becomes an override. Running it again changes nothing.
     */
    public function actionImportFiles(): int
    {
        if ($this->from === null || !is_dir($this->from)) {
            $this->stderr('Pass the folder to import with --from=<path> (it holds <language>/<category>.php).' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $report = Translate::getInstance()->overrides->importFiles($this->from, $this->dryRun);
        $counts = array_count_values(array_column($report, 'result'));
        foreach ($report as $row) {
            if ($row['result'] === OverrideRules::IMPORT_UNKNOWN) {
                $this->stdout(sprintf("Not in the code: %s/%s: %s\n", $row['language'], $row['category'], self::short($row['message'])), Console::FG_YELLOW);
            }
        }

        $this->stdout(sprintf(
            "%s %d override(s), %d of them for a key that isn't in the code. Skipped: %d the same as the default, %d already imported, %d empty.\n",
            $this->dryRun ? 'Would store' : 'Stored',
            ($counts[OverrideRules::IMPORT_OVERRIDE] ?? 0) + ($counts[OverrideRules::IMPORT_UNKNOWN] ?? 0),
            $counts[OverrideRules::IMPORT_UNKNOWN] ?? 0,
            $counts[OverrideRules::IMPORT_DEFAULT] ?? 0,
            $counts[OverrideRules::IMPORT_UNCHANGED] ?? 0,
            $counts[OverrideRules::IMPORT_EMPTY] ?? 0,
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }

    private static function short(string $text, int $length = 60): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
