<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */

namespace statikbe\translate\assetbundles\translate;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

class TranslateAsset extends AssetBundle
{
    public function init(): void
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@statikbe/translate/assetbundles/translate';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/statikTranslate.js',
        ];

        parent::init();
    }

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('translate', [
                'Default restored',
                'Downloading file',
                'Translations saved',
            ]);
        }
    }
}
