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

class TranslateAsset extends AssetBundle
{
    public function init()
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
}
