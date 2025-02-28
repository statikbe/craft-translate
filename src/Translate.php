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

use craft\base\Plugin;
use statikbe\translate\services\Translate as TranslateService;

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

        $this->setComponents([
            'translate' => TranslateService::class,
        ]);
    }
}
