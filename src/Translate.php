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
use craft\base\Plugin;
use statikbe\translate\services\Translate as TranslateService;
use statikbe\translate\elements\Translate as TranslateElement;
use statikbe\translate\events\RegisterPluginTranslationEvent;

/**
 * Class Translate
 * @package statikbe\translate
 * @property TranslateService translate
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
            function (RegisterPluginTranslationEvent $event) {
                $event->plugins['translate'] = \Craft::$app->getPlugins()->getPlugin('translate');
            }
        );

        $this->setComponents([
            'translate' => TranslateService::class,
        ]);
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
