<?php
/**
 * Translate plugin for Craft CMS 4.x
 *
 * Translate your website templates and plugins into multiple languages. Bulk translation with Google Translate or Yandex.
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */

namespace statikbe\translate;

use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\services\Plugins;
use statikbe\translate\elements\Translate as TranslateElement;
use statikbe\translate\events\RegisterPluginTranslationEvent;
use statikbe\translate\models\Settings;
use statikbe\translate\services\Overrides;
use statikbe\translate\services\Translate as TranslateService;

/**
 * Class Translate
 * @package statikbe\translate
 * @property-read TranslateService $translate
 * @property-read Overrides $overrides
 * @method Settings getSettings()
 */
class Translate extends Plugin
{
    public bool $hasCpSection = true;

    public bool $hasCpSettings = false;

    public function init(): void
    {
        parent::init();

        Event::on(
            TranslateElement::class,
            TranslateElement::EVENT_REGISTER_PLUGIN_TRANSLATION,
            function(RegisterPluginTranslationEvent $event) {
                $event->plugins['translate'] = \Craft::$app->getPlugins()->getPlugin('translate');
            }
        );

        $this->setComponents([
            'translate' => TranslateService::class,
            'overrides' => Overrides::class,
        ]);

        // Translations stored in the database: put them on top of the files, for every category that reads PHP files
        if ($this->getSettings()->storeInDatabase) {
            if (\Craft::$app->getPlugins()->arePluginsLoaded()) {
                $this->overrides->swapMessageSources();
            } else {
                Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, fn() => $this->overrides->swapMessageSources());
            }
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    public function getCpNavItem(): array
    {
        $label = \Craft::t('translate','Translate');

        $ret = [
            'label' => $label,
            'url' => $this->id,
        ];
        if (($iconPath = $this->cpNavIconPath()) !== null) {
            $ret['icon'] = $iconPath;
        }
        return $ret;
    }
}
