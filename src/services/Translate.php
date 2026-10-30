<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */

namespace statikbe\translate\services;

use Craft;
use craft\base\Component;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\FileHelper;
use craft\helpers\Html;
use Exception;
use statikbe\translate\elements\db\TranslateQuery;
use statikbe\translate\elements\Translate as TranslateElement;
use statikbe\translate\Translate as TranslatePlugin;
use Throwable;

class Translate extends Component
{
    /**
     * Translate REGEX.
     * @credits to boboldehampsink
     * @var []
     */
    public array $_expressions = array(
        // Regex for Craft::t('category', '..')
        'php' => array(
            // Single quotes
            '/Craft::(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft::(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
        ),

        // Regex for |t('category')
        'twig' => array(
            // Single quotes
            "/'([^']+)'\s*\|\s*(?:t|translate)\b(?:\(\s*['\"]([^'\"]*)['\"])?/mu",
            // Double quotes
            '/"([^"]+)"\s*\|\s*(?:t|translate)\b(?:\(\s*[\'"]([^\'"]*)[\'"])?/mu',
        ),

        // Regex for Craft.t('category', '..')
        'js' => array(
            // Single quotes
            '/Craft\.(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft\.(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
        ),
    );


    /** @var array<int, string|null> */
    private array $_userNames = [];

    /**
     * Initialize service.
     *
     * @codeCoverageIgnore
     */
    public function init(): void
    {
        parent::init();

        $this->_expressions['html'] = $this->_expressions['twig'];
        $this->_expressions['json'] = $this->_expressions['twig'];
        $this->_expressions['atom'] = $this->_expressions['twig'];
        $this->_expressions['rss'] = $this->_expressions['twig'];
    }

    /**
     * Set translations.
     *
     * @param string $locale
     * @param array $translations
     * @param string|null $translationPath
     *
     * @return bool
     * @throws \Exception if unable to write to file
     */
    public function set(string $locale, array $translations, ?string $translationPath = null): bool
    {
        // Determine locale's translation destination file
        $file = $translationPath ?? $this->getSitePath($locale);

        // Get current translation
        if ($current = @include($file)) {
            $translations = array_merge($current, $translations);
        }

        // Prepare php file
        $php = "<?php\r\n\r\nreturn ";

        // Get translations as php
        $php .= var_export($translations, true);

        // End php file
        $php .= ';';

        // Convert double space to tab (as in Craft's own translation files)
        $php = str_replace("  '", "\t'", $php);

        // Save code to file
        try {
            FileHelper::writeToFile($file, $php);
        } catch (Throwable $e) {
            throw new Exception(Craft::t('translate', 'Something went wrong while saving your translations: ' . $e->getMessage()));
        }

        return true;
    }

    /**
     * Get translations by Element Query.
     *
     * @param TranslateQuery $query
     *
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function get(TranslateQuery $query): array
    {
        if (!is_array($query->source)) {
            $query->source = [$query->source];
        }

        $translations = [];

        // Plugin sources use the plugin handle as category, otherwise the source's category (default 'site')
        $category = $query->pluginHandle ?: ($query->category ?: 'site');

        // Loop through paths
        foreach ($query->source as $path) {
            // Check if this is a folder or a file
            $isDir = is_dir($path);

            if ($isDir) {
                $options = [
                    'recursive' => true,
                    'only' => ['*.php', '*.html', '*.twig', '*.js', '*.json', '*.atom', '*.rss'],
                    'except' => ['vendor/', 'node_modules/'],
                ];

                $files = FileHelper::findFiles($path, $options);

                // Loop through files and find translate occurences
                foreach ($files as $file) {

                    // Parse file
                    $elements = $this->_processFile($path, $file, $query, $category);

                    // Collect in array
                    $translations = array_merge($translations, $elements);
                }
            } elseif (file_exists($path)) {

                // Parse file
                $elements = $this->_processFile($path, $path, $query, $category);

                // Collect in array
                $translations = array_merge($translations, $elements);
            }
        }
        return $translations;
    }

    /**
     * Apply regex search into file
     *
     * @param string $path
     * @param string $file
     * @param ElementQueryInterface $query
     * @param string $category
     *
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    private function _processFile(string $path, string $file, ElementQueryInterface $query, string $category): array
    {
        $translations = array();
        $contents = file_get_contents($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // Process the file
        foreach ($this->_expressions[$extension] as $regex) {
            // Do it!
            $matches = $this->parseString($regex, $contents);
            if ($matches) {
                $pos = 1;
                // Js and php files goes to 3
                if ($extension === 'js' || $extension === 'php') {
                    $pos = 3;
                }
                foreach ($matches[$pos] as $i => $original) {
                    // Skip strings from other categories, a translation without category means 'site'
                    $matchCategory = ($matches[2][$i] ?? '') ?: 'site';
                    if (!$query->pluginHandle && $matchCategory !== $category) {
                        continue;
                    }

                    // Apply the Craft Translate
                    $site = Craft::$app->getSites()->getSiteById($query->siteId);
                    //changed $site->language to site handle
                    $translation = Craft::t($category, $original, [], $site->language);

                    // Stored in the database: whether an editor changed it
                    $override = $this->_override($category, $site->language, $original);
                    if (filter_var($query->changedOnly, FILTER_VALIDATE_BOOLEAN) && $override === null) {
                        continue;
                    }

                    $view = Craft::$app->getView();
                    $slug = ElementHelper::generateSlug($original);

                    $field = $view->renderTemplate('_includes/forms/text', [
                        'id' => $slug,
                        'name' => 'translation[' . $original . ']',
                        'value' => $translation,
                        'placeholder' => $translation,
                    ]);
                    if ($override !== null) {
                        $field .= $this->_overrideHtml($original, $override);
                    }

                    // Let's create our translate element with all the info
                    $element = new TranslateElement([
                        'id' => $slug,
                        'original' => $original,
                        'translation' => $translation,
                        'source' => $path,
                        'file' => $file,
                        'siteId' => $query->siteId,
                        'field' => $field,
                        'overridden' => $override !== null,
                    ]);


                    // Continue when Searching
                    if ($query->search && !stristr($element->original, $query->search) && !stristr($element->translation, $query->search)) {
                        continue;
                    }
                    // Continue when filter by status
                    if ($query->status && $query->status != $element->getStatus()) {
                        continue;
                    }
                    // add actions occurrences
                    if ($query->id) {
                        foreach ($query->id as $id) {
                            if ($element->id == $id) {
                                $translations[$element->original] = $element;
                            }
                        }
                    } else {
                        $translations[$element->original] = $element;
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * The override an editor stored for a message (with `storeInDatabase` on), or null.
     *
     * @return array{translation: string, userId: int|null, dateUpdated: string}|null
     */
    private function _override(string $category, string $language, string $message): ?array
    {
        $plugin = TranslatePlugin::getInstance();
        if (!$plugin || !$plugin->getSettings()->storeInDatabase) {
            return null;
        }

        return $plugin->overrides->get($category, $language, $message);
    }

    /**
     * The line under a changed translation: who changed it and when, and a button to restore the default.
     *
     * @param array{translation: string, userId: int|null, dateUpdated: string} $override
     */
    private function _overrideHtml(string $message, array $override): string
    {
        if ($override['userId'] !== null && !array_key_exists($override['userId'], $this->_userNames)) {
            $this->_userNames += TranslatePlugin::getInstance()->overrides->userNames([$override['userId']]);
            $this->_userNames[$override['userId']] ??= null;
        }
        $name = $override['userId'] !== null ? ($this->_userNames[$override['userId']] ?? null) : null;
        $date = Craft::$app->getFormatter()->asDatetime(DateTimeHelper::toDateTime($override['dateUpdated'], false, false) ?: null, 'short');

        $changed = $name !== null
            ? Craft::t('translate', 'Changed by {name} on {date}', ['name' => $name, 'date' => $date])
            : Craft::t('translate', 'Changed on {date}', ['date' => $date]);

        return Html::tag('div',
            Html::encode($changed) . ' · ' .
            Html::button(Craft::t('translate', 'Restore default'), [
                'type' => 'button',
                'class' => 'translate-restore btn small',
                'data-message' => $message,
            ]),
            ['class' => 'translate-override smalltext light', 'style' => 'margin-top: 4px;']
        );
    }

    /**
     * The messages used in the site's templates, per category: [category => [message, …]].
     *
     * @return array<string, string[]>
     * @throws \yii\base\Exception
     */
    public function usedKeys(): array
    {
        $keys = [];
        $files = FileHelper::findFiles(Craft::$app->getPath()->getSiteTemplatesPath(), [
            'recursive' => true,
            'only' => ['*.php', '*.html', '*.twig', '*.js', '*.json', '*.atom', '*.rss'],
            'except' => ['vendor/', 'node_modules/'],
        ]);
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $contents = (string)file_get_contents($file);
            foreach ($this->_expressions[$extension] ?? [] as $regex) {
                $matches = $this->parseString($regex, $contents);
                $pos = in_array($extension, ['js', 'php'], true) ? 3 : 1;
                foreach ($matches[$pos] ?? [] as $i => $message) {
                    $category = ($matches[2][$i] ?? '') ?: 'site';
                    $keys[$category][$message] = true;
                }
            }
        }

        return array_map(fn(array $messages) => array_map('strval', array_keys($messages)), $keys);
    }

    public function parseString($expression, $string)
    {
        $string = preg_replace("/\r?\n|\r|\n/", " ", $string);
        $string = preg_replace('!\s+!', ' ', $string);
        preg_match_all($expression, $string, $matches);
        return $matches;
    }

    /**
     * @param $locale
     *
     * @return string
     * @throws \yii\base\Exception
     */
    public function getSitePath($locale, string $category = 'site'): string
    {
        $sitePath = Craft::$app->getPath()->getSiteTranslationsPath();
        return $sitePath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $category . '.php';
    }

    /**
     * The translation category of a source in the control panel: `category:website_name` → website_name, a plugin source
     * → the plugin's handle, anything else → site.
     *
     * @throws \yii\web\BadRequestHttpException for a category that isn't stored in the site's translations folder
     */
    public function categoryForSourceKey(string $sourceKey): string
    {
        if (str_starts_with($sourceKey, 'category:')) {
            $category = substr($sourceKey, strlen('category:'));
            if (!in_array($category, $this->getSiteCategories(), true)) {
                throw new \yii\web\BadRequestHttpException('Invalid translation category');
            }
            return $category;
        }

        if (str_contains($sourceKey, 'plugins:')) {
            $handle = substr($sourceKey, strrpos($sourceKey, 'plugins:') + strlen('plugins:'));
            $plugin = Craft::$app->getPlugins()->getPlugin($handle);
            if (!$plugin) {
                throw new \yii\web\BadRequestHttpException('Invalid plugin');
            }
            return $plugin->getHandle();
        }

        return 'site';
    }

    /**
     * @throws \yii\base\Exception
     */
    public function getSiteCategories(): array
    {
        $sitePath = Craft::$app->getPath()->getSiteTranslationsPath();
        $categories = [];

        foreach (Craft::$app->getI18n()->translations as $category => $source) {
            if (str_contains($category, '*')) {
                continue;
            }
            $basePath = is_array($source) ? ($source['basePath'] ?? null) : ($source->basePath ?? null);
            if ($basePath && rtrim(Craft::getAlias($basePath), '/') === rtrim($sitePath, '/')) {
                $categories[] = $category;
            }
        }

        return $categories;
    }
}
