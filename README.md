# Translate for Craft CMS 4.x

Translate static string from the Craft control panel

![Codeception](https://github.com/statikbe/craft3-translate/workflows/Run%20Codeception%20unit%20tests/badge.svg)

## Requirements

This plugin requires Craft CMS 4.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require statikbe/craft-translate

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Translate.


## Plugin & module translations

From version 1.3.0 onwards, translations for plugins and modules can now be saved in your site as well.

This can be done by adding the following event to your plugin file:

```php
use statikbe\translate\elements\Translate;
use statikbe\translate\events\RegisterPluginTranslationEvent;

 Event::on(
    Translate::class,
    Translate::EVENT_REGISTER_PLUGIN_TRANSLATION,
    function (RegisterPluginTranslationEvent $event) {
        $event->plugins['plugin-handle'] = \Craft::$app->getPlugins()->getPlugin('plugin-handle');
    }
);
```

Translations for the plugin will then be saved in ``site\translations\locale\plugin-handle.php``

## Translations stored in the database

By default, a change in the control panel is written into the translation files of the site's `translations` folder.
That folder then holds both your defaults and the editors' changes, so it can't be kept in git and updated on deploy.

Turn on `storeInDatabase` to keep them apart:

```php
// config/translate.php
return [
    'storeInDatabase' => true,
];
```

- The files in `translations/` hold the defaults and can live in git; a deploy updates them.
- A change in the control panel is stored in the database (`translate_overrides`), only when it differs from the default,
  and wins over the files. Setting a value back to its default, or emptying it, removes it.
- The list shows who changed a translation and when, with **Restore default**, and can show only the changed ones.
- Works for the site's categories (`site`, `website_name`, …) and plugin categories, in Craft's own order: a plugin's own
  files, the site's translations folder, the generic language (`nl`) before the specific one (`nl-BE`), then the overrides.
- All overrides are read from the data cache once per request; a request with a warm cache does no extra query. Template
  caches (`{% cache %}`) keep showing an old translation until they're cleared, as before.

Console commands:

| Command | What it does |
| --- | --- |
| `craft translate/overrides/list [--language=nl-BE] [--category=website_name]` | The overrides: key, default, override, who and when |
| `craft translate/overrides/prune [--dry-run]` | Removes overrides that equal their default, or whose key (of the site's categories) is neither in the files nor used in the templates |
| `craft translate/overrides/promote [--force]` | Writes the overrides into the translation files and removes them from the database (for developers, after changing translations locally; dev mode only unless `--force`) |
| `craft translate/overrides/import-files --from=<path> [--dry-run]` | Switching an existing site: imports its old translations folder (`<language>/<category>.php`); values that differ from the defaults in the code become overrides, keys the code doesn't have are reported. Running it again changes nothing |

---

Brought to you by [Statik](https://www.statik.be), heavily inspired by [boboldehampsink/translate](https://github.com/boboldehampsink/translate).
