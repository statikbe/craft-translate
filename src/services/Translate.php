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
use craft\helpers\ElementHelper;
use craft\helpers\FileHelper;
use craft\helpers\Html;
use Exception;
use statikbe\translate\elements\db\TranslateQuery;
use statikbe\translate\elements\Translate as TranslateElement;
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
            "/'([^']+)'\ *\|\ *(t|translate)/mu",
            // Double quotes
            '/"([^"]+)"\ *\|\ *(t|translate)/mu',
        ),

        // Regex for Craft.t('category', '..')
        'js' => array(
            // Single quotes
            '/Craft\.(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft\.(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
        )
    );


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
    public function set(string $locale, array $translations, string $translationPath = null): bool
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
     * @param ElementQueryInterface $query
     *
     * @param string $category
     *
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    public function get(TranslateQuery $query, string $category = 'site'): array
    {
        if (!is_array($query->source)) {
            $query->source = [$query->source];
        }

        $translations = [];

        // Fetch site once, outside of all loops
        $site = Craft::$app->getSites()->getSiteById($query->siteId);

        foreach ($query->source as $path) {
            if ($query->pluginHandle) {
                $category = $query->pluginHandle;
            }

            $isDir = is_dir($path);

            if ($isDir) {
                $options = [
                    'recursive' => true,
                    'only' => ['*.php', '*.html', '*.twig', '*.js', '*.json', '*.atom', '*.rss'],
                    'except' => ['vendor/', 'node_modules/']
                ];

                $files = FileHelper::findFiles($path, $options);

                foreach ($files as $file) {
                    $elements = $this->_processFile($path, $file, $query, $category, $site, $translations);
                    $translations = array_merge($translations, $elements);
                }
            } elseif (file_exists($path)) {
                $elements = $this->_processFile($path, $path, $query, $category, $site, $translations);
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
     * @param mixed $site
     * @param array $existing Already-collected translations to skip duplicates
     *
     * @return array
     */
    private function _processFile(string $path, string $file, ElementQueryInterface $query, string $category, $site, array $existing = []): array
    {
        $translations = [];
        $contents = file_get_contents($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        foreach ($this->_expressions[$extension] as $regex) {
            $matches = $this->parseString($regex, $contents);
            if (!$matches) {
                continue;
            }

            $pos = ($extension === 'js' || $extension === 'php') ? 3 : 1;

            foreach ($matches[$pos] as $original) {
                // Skip duplicates already collected from previous files
                if (isset($existing[$original]) || isset($translations[$original])) {
                    continue;
                }

                $translation = Craft::t($category, $original, [], $site->language);

                // Apply search filter before building the element
                if ($query->search && !stristr($original, $query->search) && !stristr($translation, $query->search)) {
                    continue;
                }

                // Apply status filter before building the element
                if ($query->status) {
                    $status = ($original !== $translation) ? TranslateElement::TRANSLATED : TranslateElement::PENDING;
                    if ($query->status !== $status) {
                        continue;
                    }
                }

                $slug = ElementHelper::generateSlug($original);

                // Build input HTML directly instead of rendering a Twig template per string
                $field = Html::input('text', 'translation[' . $original . ']', $translation, [
                    'id' => $slug,
                    'class' => 'text fullwidth',
                    'placeholder' => $translation,
                ]);

                $element = new TranslateElement([
                    'id' => $slug,
                    'original' => $original,
                    'translation' => $translation,
                    'source' => $path,
                    'file' => $file,
                    'siteId' => $query->siteId,
                    'field' => $field,
                ]);

                if ($query->id) {
                    foreach ($query->id as $id) {
                        if ($element->id == $id) {
                            $translations[$original] = $element;
                        }
                    }
                } else {
                    $translations[$original] = $element;
                }
            }
        }

        return $translations;
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
    public function getSitePath($locale): string
    {
        $sitePath = Craft::$app->getPath()->getSiteTranslationsPath();
        return $sitePath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'site.php';
    }

}
