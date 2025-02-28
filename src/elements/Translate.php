<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */

namespace statikbe\translate\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\web\ErrorHandler;
use statikbe\translate\elements\db\TranslateQuery;
use statikbe\translate\events\RegisterPluginTranslationEvent;
use statikbe\translate\Translate as TranslatePlugin;
use yii\base\Event;

class Translate extends Element
{
    /**
     * Status constants.
     */
    public const ALL = 'all';
    public const TRANSLATED = 'live';
    public const PENDING = 'disabled';

    public const EVENT_REGISTER_PLUGIN_TRANSLATION = "event_register_plugin_translation";

    public $original;
    public $translation;
    public $source;
    public $file;
    public $locale = 'en_us';
    public $field;
//    public $translateStatus;

    /**
     * Return element type name.
     *
     * @return string
     */
    public function getName(): string
    {
        return Craft::t('translate', 'Translations');
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function __toString(): string
    {
        try {
            return $this->original;
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
        }
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * Define statuses.
     *
     * @return array
     */
    public static function statuses(): array
    {
//        return [
//            self::TRANSLATED => Craft::t('translate', 'Translated'),
//            self::PENDING => Craft::t('translate', 'Pending'),
//        ];
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        if ($this->original != $this->translation) {
            return static::TRANSLATED;
        }

        return static::PENDING;
    }

    public static function find(): ElementQueryInterface
    {
        return new TranslateQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes['field'] = ['label' => Craft::t('app', 'Translation')];
        return $attributes;
    }

    /**
     * Returns the default table attributes.
     *
     * @param string $source
     *
     * @return array
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['field'];
    }

    /**
     * Don't encode the attribute html.
     *
     * @param string $attribute
     *
     * @return string
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        return $this->$attribute;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'original',
            'translation',
            'source',
            'file',
            'locale',
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [];

        $sources[] = ['heading' => Craft::t('translate', 'Default')];

        $sources[] = [
            'label' => Craft::t('translate', 'Templates'),
            'key' => 'all-templates:',
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath(),
                ],
            ],
//            'nested' => $templateSources
        ];

        $event = new RegisterPluginTranslationEvent([
            'plugins' => [],
        ]);

        Event::trigger(__CLASS__, self::EVENT_REGISTER_PLUGIN_TRANSLATION, $event);
        $registerdPlugins = array_filter($event->plugins);

        foreach ($registerdPlugins as $path => $module) {

            //was vroeger $modulesources (om plugin mapje te maken met eronder de plugins, nu enkel balkje per plugin)
//            $sources['plugins:' . $path] = [
            $modulesSources['plugins:' . $path] = [
                'label' => $module->id,
                'key' => 'plugins:' . $module->id,
                'criteria' => [
                    'pluginHandle' => $module->getHandle(),
                    'source' => [
                        $module->getBasePath(),
                    ],
                ],
            ];
        }

        //luste vroeger de plugins uit die in de plugins map hierboven terecht kwamen
        if (isset($modulesSources)) {
            $sources[] = [
                'label' => Craft::t('translate', 'Modules'),
                'key' => 'modules',
                'criteria' => [
                    'source' => [
                    ],
                ],
                'nested' => $modulesSources,
            ];
        }

        return $sources;
    }

    public function canSave(User $user): bool
    {
        return true;
    }

    public function getInlineAttributeInputHtml(string $attribute): string
    {
        return $this->field;
    }


    /**
     * @inheritdoc
     */
    public static function indexHtml(
        ElementQueryInterface $elementQuery,
        array                 $disabledElementIds = null,
        array                 $viewState,
        string                $sourceKey = null,
        string                $context = null,
        bool                  $includeContainer,
        bool                  $showCheckboxes,
        bool                  $sortable,
    ): string {
        // just 1 locale enabled
        if (empty($elementQuery->siteId)) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $elementQuery->siteId = $primarySite->id;
        }


        $elementQuery->status = null;
        $elements = TranslatePlugin::getInstance()->translate->get($elementQuery);

        $variables = [
            'viewMode' => $viewState['mode'],
            'context' => $context,
            'inputNameSpace' => 'translation',
            'nestedInputNamespace' => 'translation',
            'disabledElementIds' => [],
            'attributes' => Craft::$app->getElementSources()->getTableAttributes(static::class, $sourceKey),
            'elements' => $elements,
            'sourceKey' => $sourceKey,
            'includeContainer' => false,
            'showCheckboxes' => false,
            'selectable' => false,
            'sortable' => false,
            'showHeaderColumn' => true,
            'inlineEditing' => true,
        ];

        // Better UI
        Craft::$app->view->registerJs("$('table.fullwidth thead th').css('width', '50%');");
        Craft::$app->view->registerJs("$('.buttons.hidden').removeClass('hidden');");
        Craft::$app->view->registerJs("$('.filter-btn').addClass('hidden');");
        Craft::$app->view->registerJs("$('.btn.statusmenubtn').addClass('hidden');");

        return Craft::$app->view->renderTemplate("_elements/tableview/container", $variables);
    }

    /**
     * @return null|string
     */
    public function getLocale(): ?string
    {
        $site = Craft::$app->getSites()->getSiteById($this->siteId);

        return $site->language;
    }
}
