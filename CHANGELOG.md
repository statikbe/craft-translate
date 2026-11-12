# Translate Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 5.3.2 - 2026-11-12
### Fixed
- Saving a page of translations lost the changes to some of them: messages starting with a number (like "1. Choose a procedure") are posted in another group than the rest, and only the first group was saved

## 5.3.1 - 2026-10-30
### Fixed
- `translate/overrides/import-files` no longer stores untranslated messages as overrides: a value that is just the message itself (which earlier versions wrote into the file for every untranslated message when a page of translations was saved) is skipped when the translation files have a translation for it

## 5.3.0 - 2026-10-30
### Added
- `storeInDatabase` setting (in `config/translate.php`, off by default): the translations editors change in the control panel are stored in the database (table `translate_overrides`) on top of the translation files, instead of being written into those files. Only values that differ from the default are stored; a value set back to its default (or emptied) removes it. A deploy can then update the files in git without overwriting what editors changed.
- With `storeInDatabase` on: every translation category that reads PHP files (the site's categories like `site` and `website_name`, and plugin categories like `formie`) gets the overrides on top, in Craft's own order (plugin files, the site's translations folder, the generic language before the specific one). All overrides are read once per request from the data cache; a warm cache adds no query.
- The control panel shows who changed a translation and when, a **Restore default** button, and a filter to only show the translations editors changed
- Console commands `translate/overrides/list`, `prune` (removes overrides that equal their default or whose key is no longer used), `promote` (writes the overrides into the translation files, for developers; dev mode or `--force`) and `import-files --from=<path>` (imports an old translations folder once: values that differ from the defaults become overrides; idempotent)

### Fixed
- A JavaScript error on every reload of the translations list (the site banner looked for an element Craft 5 doesn't have)

## 5.2.1 - 2026-10-13
### Changed
- Updated the plugin icon

## 5.2.0 - 2026-10-07
### Added
- Translation categories stored in the site's `translations` folder (e.g. `website_name`, registered in `config/app.php` with `basePath => '@translations'`) are now listed as separate sources in the CP
- Translations in templates are now saved to the file of their category (`translations/<lang>/<category>.php`) instead of always to `site.php`
- CSV downloads for a category source are named after that category

### Changed
- The "Templates" source now only shows strings from the `site` category (`|t` without a category)
- Removed the 2 second delay when loading translations

### Fixed
- Translations with a category (e.g. `|t('website_name')`) were shown with the wrong value and saved to `site.php`
- Twig filters starting with "t", like `|trim` and `|title`, were picked up as translations

## 5.1.0 - 2026-04-08
### Added
- Added Dutch, French, and German translations for the plugin's control panel UI
- Registered the plugin so the labels can also be changed from the CP
### Fixed
- Fixed missing translation category on the site banner text in the index template

## 5.0.1 - 2025-02-21
### Fixed
- Fixed an error in the download dropdown when devMode was enabled ([#17](https://github.com/statikbe/craft-translate/issues/17))

## 5.0.0 - 2024-05-28
### Added
- Craft 5 support

## 5.0.0.beta-01 - 2024-04-18
### Added
- Craft 5 support

## 2.1.4 - 2024-01-24
### Fixed
- fixed regex allowing for spaces in twig syntax

## 2.1.2 - 2023-04-11
### Fixed
- Fixed for single site use


## 2.1.1 - 2022-10-27
### Fixed
- Fixed for single site use

## 2.1.0 - 2022-10-27
### Improvements
- Removed status icons in translation overview
- Added keyboard shortcut to save translations

## 2.0.1 - 2022-10-07
### Added
- Craft 4 compatibility

## 2.0.0-beta.1 - 2022-03-15
### Added
- Craft 4 compatibility

## 1.3.2 - 2022-05-02
### Fixed
- Fixed an issue where saving module translations wasn't working

## 1.3.1 - 2021-11-29
### Fixed
- Fixed a crash when no plugin translations were registered


## 1.3.0 - 2021-11-26
### Added
- Added an event to register plugin translations

### Fixed
- Plugin translations are now save on site level instead of in the vendor directory.

## 1.2.4 - 2021-10-05
### Fixed
- Better fallback for missing siteId value

## 1.2.3 - 2021-06-01
### Fixed
- Site ID should always be an integer.



## 1.2.2 - 2021-05-28
### Fixed
- Fixed an issue where the siteId would not be set when navigating straight to the Translate page after login


## 1.2.1 - 2021-03-22
### Fixed
- Fixed an issue with the regex used to match translations in Twig files


## 1.2.0 - 2021-03-02
### Added
- Basic unit tests to validate certain string parsing scenario's

### Fixed
- Fixed an issue where the wrong site ID was used on initial load of the plugin page

## 1.1.6 - 2020-10-27
### Added
- Fixed PSR-4 autoloading issue.


## 1.1.5 - 2019-03-21
### Added
- Added translatable strings from modules.

## 1.1.4 - 2019-01-25
### Fixed
- Improved loading new translations after safe to account for slower fetch from files. This should fix the "save & see old data" problem.

## 1.1.3 - 2018-12-03
### Fixed
- Sleep for two seconds in the get function

## 1.1.2 - 2018-11-20
### Fixed
- Fixed previous bug that wasn't interely fixed

## 1.1.1 - 2018-11-20
### Fixed
- Fixed bug where new translations didn't immediately show in the translations view

## 1.1.0 - 2018-10-25
### Added
- Added the option to see translations of any status in the same view. So we now have All/Pending/Live.

## 1.0.0 - 2018-08-20
### Added
- Initial release
