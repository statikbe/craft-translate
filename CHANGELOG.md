# Translate Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.1.5 - 2025-02-21
### Fixed
- Fixed an error in the download dropdown when devMode was enabled ([#17](https://github.com/statikbe/craft-translate/issues/17))

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
