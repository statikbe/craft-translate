<?php

namespace statikbe\translate\i18n;

use craft\i18n\PhpMessageSource;
use statikbe\translate\helpers\OverrideRules;
use statikbe\translate\Translate;

/**
 * Craft's PHP message source with the overrides from the database on top (when `storeInDatabase` is on).
 *
 * Craft's order stays as it is: a plugin's own files, then the site's `translations` folder; the language itself, then its
 * generic language (nl-BE, then nl). The overrides of the generic language and then of the language itself go on top.
 */
class OverridablePhpMessageSource extends PhpMessageSource
{
    /**
     * @inheritdoc
     */
    protected function loadMessages($category, $language): array
    {
        $messages = parent::loadMessages($category, $language);
        $overrides = Translate::getInstance()?->overrides;
        if ($overrides === null) {
            return $messages;
        }

        return OverrideRules::merge($messages, $overrides->exact($category, OverrideRules::fallbackLanguage((string)$language)), $overrides->exact($category, (string)$language));
    }

    /**
     * The messages of the files only, without the overrides: the defaults an editor's change is compared with.
     *
     * @return array<string, string>
     */
    public function fileMessages(string $category, string $language): array
    {
        return parent::loadMessages($category, $language);
    }
}
