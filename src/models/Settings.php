<?php

namespace statikbe\translate\models;

use craft\base\Model;

/**
 * Settings, in config/translate.php.
 */
class Settings extends Model
{
    /**
     * @var bool Store the translations editors change in the database (table `translate_overrides`) instead of in the files of
     * the site's `translations` folder. The files then hold the defaults (in git); a change in the control panel only stores
     * the keys whose value differs from the default, on top of them. Off by default: the files are written as before.
     */
    public bool $storeInDatabase = false;

    protected function defineRules(): array
    {
        return [
            [['storeInDatabase'], 'boolean'],
        ];
    }
}
