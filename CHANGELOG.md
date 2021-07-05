# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.2] - 2021-07-05
- BUG FIX: Removing the containers didn't work (Thomas Sjolshagen)
- BUG FIX: Didn't stop the test stack when testing is done (Thomas Sjolshagen)
- BUG FIX: Wrong variable name used for dependency building/loading (Thomas Sjolshagen)
- BUG FIX: docker-composer up failed due to missing volume definition (Thomas Sjolshagen)
- BUG FIX: Port collisions when this plugin is a dependency for another plugin build (Thomas Sjolshagen)
- BUG FIX: Added a few bug fixes to Makefile (Thomas Sjolshagen)
- BUG FIX: Add exception handling to NewLicenseSettings() constructor (Thomas Sjolshagen)
- BUG FIX: WPCS update (nit) (Thomas Sjolshagen)
- BUG FIX: Didn't mock all uses of get_option() BUG FIX: Refactored LicensingTest.php BUG FIX: markTestSkipped() is static (Thomas Sjolshagen)
- BUG FIX: Wrong path for source file (Thomas Sjolshagen)
- BUG FIX: Initial commit - extracts version info for plugin (Thomas Sjolshagen)
- Custom .gitignore for the _data test directory (Thomas Sjolshagen)
- BUG FIX: Missing settings for docker-compose test environment (Thomas Sjolshagen)
- BUG FIX: Wrong name for .env file (Thomas Sjolshagen)
- BUG FIX: Exclude docker key file (if it exists) (Thomas Sjolshagen)
- BUG FIX: Wrong path to docker key file (if it exists) (Thomas Sjolshagen)
- BUG FIX: Wrong name for .env file (Thomas Sjolshagen)
- BUG FIX: Didn't use git archive to build new plugin archive (Thomas Sjolshagen)
- BUG FIX: Clean up PHPCS errors in class-licensing.php (Thomas Sjolshagen)
- BUG FIX: Clean up PHPCS errors in class-licenseserver.php (Thomas Sjolshagen)
- BUG FIX: Initial commit of .gitattributes (Thomas Sjolshagen)
- BUG FIX: Refactored Makefile to support plugin_config.mk (Thomas Sjolshagen)
- BUG FIX: Initial commit of plugin_config.mk (Thomas Sjolshagen)
- Bug fix/from 109 (#101) (Thomas Sjølshagen)
- BUG FIX: Stop using CircleCI for now (Thomas Sjolshagen)
- V2.0.1 (#99) (Thomas Sjølshagen)

## [2.0.2] - 2021-06-28
- BUG FIX: Use the correct branch (main) (Thomas Sjolshagen)
- BUG FIX: Didn't include inc and build_readmes (Thomas Sjolshagen)
- BUG FIX: Re-added the post-install/post-update commands for phpcs (Thomas Sjolshagen)
- BUG FIX: Updated config for phpunit (Thomas Sjolshagen)
- BUG FIX: Stop using CircleCI for now (Thomas Sjolshagen)

## [2.0.1] - 2021-06-28
- BUG FIX: Didn't exclude MacOS specific files (Thomas Sjolshagen)
- BUG FIX: Refactored the utilities module presence filter (Thomas Sjolshagen)
- BUG FIX: Updates to fix build environment (Thomas Sjolshagen)

## v2.0 - 2021-06-27
- BUG FIX: Forgot to rename plugin source for build script (Thomas Sjolshagen)
- BUG FIX: Use the expected repository (Thomas Sjolshagen)
- BUG FIX: Minor nits (Thomas Sjolshagen)
- BUG FIX: More tweaking for wp unit tests (Thomas Sjolshagen)
- BUG FIX: Still using error_log() for now (Thomas Sjolshagen)
- BUG FIX: Refactored - Plugin uses own autoloader (Thomas Sjolshagen)
- BUG FIX: Refactored - Moving away from Singleton pattern (Thomas Sjolshagen)
- BUG FIX: Refactored to integrate more settings (Thomas Sjolshagen)
- BUG FIX: Load settings when SKU is found (Thomas Sjolshagen)
- BUG FIX: Refactoring custom exceptions (Thomas Sjolshagen)
- BUG FIX: Didn't (re)load settings for the license key when setting it + updated exceptions (Thomas Sjolshagen)
- BUG FIX: Attempted to use static update call (Thomas Sjolshagen)
- BUG FIX: Remove redundant LicenseSettings->load_settings() call (Thomas Sjolshagen)
- BUG FIX: Initial commit of refactored AJAX handling (Thomas Sjolshagen)
- BUG FIX: Refactored the LicenseSettings class (Thomas Sjolshagen)
- BUG FIX: Refactored the E20R Licenses page for wp-admin (Thomas Sjolshagen)
- BUG FIX: Clean up WPCS issues and test for Set Expiration Date add-on (Thomas Sjolshagen)
- BUG FIX: Wrong upload dir info index used (Thomas Sjolshagen)
- BUG FIX: Various updates for license module (Thomas Sjolshagen)
- BUG FIX: Adding custom exceptions (Thomas Sjolshagen)
- BUG FIX: Refactor class handling for Licensing (supporting old and new licensing classes) (Thomas Sjolshagen)
- BUG FIX: Didn't escape \ in namespace for Utilities module (Thomas Sjolshagen)
- BUG FIX: Various updates for WP Unit Testing (incomplete) (Thomas Sjolshagen)
- BUG FIX: Ignore all content in the DB backup directory (Thomas Sjolshagen)
- BUG FIX: Fix I18N slug name BUG FIX: Be a little smarter about debug logging (own file & dir) (Thomas Sjolshagen)
- BUG FIX: Fix I18N slug name (Thomas Sjolshagen)

## v1.0.8 - 2021-03-01

* BUG FIX: Problems updating update module call-out in other plugins embedding 00-e20r-utilities

## v1.0.7 -

* BUG FIX: Exception handling in autoloader

## v1.0.6 -

* BUG FIX: Need the path to the plugin for plugin-update-checker to work
* BUG FIX: Shellcheck updates
* BUG FIX: Typo in changelog script
* ENH: Adding changelog generation and updating metadata

## v1.0.4 -

* BUG FIX: Wrong path when loading the plugin update checker
* ENH: Bumping version number and change log management logic

## 1.0.3 -
* BUG FIX: Attempting to fix plugin updater

## 1.0.2 -
* BUG FIX: Path to the GDPR policy template HTML file was incorrect after the refactor

## 1.0.1
* BUG FIX: Make sure this loads as one of the very first plugin(s)

## 1.0 -
* Initial release of the E20R Utilities module (plugin)
