# Changelog

All notable changes to `laravel-translatable` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2026-05-21

### Added

- Laravel 13 support — `illuminate/contracts` and `illuminate/database` now allow `^13.0`.
- PHP 8.5 support.

### Changed

- Dev tooling ranges widened to Pest 3/4, Testbench 9/10/11, Larastan 2/3 and PHPStan 1/2.
- CI matrix expanded to test Laravel 11, 12 and 13 against PHP 8.2–8.5, excluding incompatible combinations. Laravel 10 is no longer exercised in CI because `pestphp/pest-plugin-laravel` v3 requires Laravel 11+ and v4 requires PHP 8.3+; runtime support for L10 stays via `^10.18` in `composer.json` (consumers do not install the package's dev dependencies).
- `composer test` now passes `--no-coverage` so Pest 4 + PHPUnit 12 do not trip `failOnWarning` without a coverage driver.
- `TranslatableScope` no longer relies on macro-from-macro calls — the search logic moved into a static `applySearchByTranslation()` method, and the `Builder::with()` closure no longer narrows `Relation` to `MorphMany`.
- Source docblocks aligned with the Laravel framework conventions.

### Removed

- The historic `ignoreErrors` block from `phpstan.neon.dist`; the underlying type issues were fixed instead of suppressed.

### Notes

- No public API changed. Existing users on Laravel 10/11/12 are unaffected; Composer resolves the appropriate dependency versions per Laravel version.

## [2.1.0] - 2025-10-15

### Added

- Optional `default_locale_on_model` mode: the default locale is read from the model's own columns instead of the translatable table.
- `$boolean` parameter on the search query macros so they can be combined with `or` clauses.

### Fixed

- Locales that resolve to no translations are no longer queried more than once per instance.

## [2.0.0] - 2025-10-07

Major rewrite. Translations are now accessed through Eloquent global scopes and macros instead of model-level overrides.

### Added

- `TranslatableScope` global scope, applied automatically by the `HasTranslations` trait.
- Query macros: `withTranslations()`, `searchByTranslation()`, `searchByTranslationExact()`, `searchByTranslationStartsWith()`, `searchByTranslationEndsWith()`, `whereHasTranslation()`, `whereTranslation()`, `orderByTranslation()`.
- `LocaleResolver` class for managing the locale fallback chain (defaults to `app()->getLocale()` and `app()->getFallbackLocale()`).
- Instance methods: `setTranslation()`, `setTranslations()`, `getTranslation()`, `getTranslations()`, `getAllTranslations()`, `hasTranslation()`, `getUntranslated()`.
- Support for both a `protected $translatable = [...]` property and a `translatedKeys()` method to declare translatable attributes.

### Changed

- `orderByTranslation()` uses correlated subqueries instead of joins, making ordering database-agnostic.
- Search queries use `whereIn()` / `whereColumn()` for better index utilization.
- Translation lookups use an indexed map for O(1) attribute access after the initial load.
- README rewritten to match the new API.

## [1.5.0] - 2025-07-23

### Added

- Laravel 12 support.

## [1.4.1] - 2025-03-17

### Removed

- Remaining Nova integration code.

## [1.4.0] - 2025-03-06

### Changed

- Improved translation loading.

### Removed

- Nova field/integration support.

## [1.3.1] - 2025-01-06

### Added

- Dedicated resolver class powering the locale fallback chain.

## [1.3.0] - 2024-11-11

### Added

- Better support for dependent translatable fields.

## [1.2.2] - 2024-07-05

### Added

- Method to set the fallback locale at runtime.

## [1.2.1] - 2024-06-25

### Added

- Query scopes for searching translations and ordering by translated columns.
- Locale-specific rules for Nova fields.

## [1.2.0] - 2024-06-24

### Added

- `withoutTranslations()` helper to skip the translation layer on a query.

## [1.1.2] - 2024-06-21

### Added

- Fallback handling for the dynamic translation accessor.

## [1.1.1] - 2024-06-20

### Changed

- Additional support for the translatable Nova field.

## [1.1.0] - 2024-06-18

### Added

- Dynamic accessor that reads translatable attributes off the model.

## [1.0.3] - 2024-02-01

### Fixed

- Allow `null` returns from translation lookups.

## [1.0.2] - 2024-01-24

### Added

- Documented model property annotations.

## [1.0.1] - 2024-01-09

### Added

- Allow a custom translatable model class.

## [1.0] - 2024-01-09

### Added

- Initial release.

[2.2.0]: https://github.com/mindtwo/laravel-translatable/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/mindtwo/laravel-translatable/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/mindtwo/laravel-translatable/compare/1.5.0...2.0.0
[1.5.0]: https://github.com/mindtwo/laravel-translatable/compare/1.4.1...1.5.0
[1.4.1]: https://github.com/mindtwo/laravel-translatable/compare/1.4.0...1.4.1
[1.4.0]: https://github.com/mindtwo/laravel-translatable/compare/1.3.1...1.4.0
[1.3.1]: https://github.com/mindtwo/laravel-translatable/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/mindtwo/laravel-translatable/compare/1.2.2...1.3.0
[1.2.2]: https://github.com/mindtwo/laravel-translatable/compare/1.2.1...1.2.2
[1.2.1]: https://github.com/mindtwo/laravel-translatable/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/mindtwo/laravel-translatable/compare/1.1.2...1.2.0
[1.1.2]: https://github.com/mindtwo/laravel-translatable/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/mindtwo/laravel-translatable/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/mindtwo/laravel-translatable/compare/1.0.3...1.1.0
[1.0.3]: https://github.com/mindtwo/laravel-translatable/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/mindtwo/laravel-translatable/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/mindtwo/laravel-translatable/compare/1.0...1.0.1
[1.0]: https://github.com/mindtwo/laravel-translatable/releases/tag/1.0
