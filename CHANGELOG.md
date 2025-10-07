# Changelog

All notable changes to `laravel-translatable` will be documented in this file.

## [Unreleased] - 2024-XX-XX

### 🎉 Major Features Added

#### Scope-Based Architecture
- **NEW**: Extracted query scopes into dedicated `TranslatableScope` class following Laravel's SoftDeletes pattern
- **NEW**: Added automatic scope application via `HasTranslations` trait
- **NEW**: All scope methods now return query builder for proper method chaining

#### Advanced Query Capabilities
- **NEW**: `withTranslations(?array $locales)` - Eager load translations for specified locales
- **NEW**: `searchByTranslation($key, $search, $locales, $operator)` - Search in translated fields with locale support
- **NEW**: `searchByTranslationExact($key, $search, $locales)` - Exact match search in translations
- **NEW**: `searchByTranslationStartsWith($key, $search, $locales)` - Prefix search in translated fields
- **NEW**: `searchByTranslationEndsWith($key, $search, $locales)` - Suffix search in translated fields
- **NEW**: `whereHasTranslation($key, $locales)` - Filter models that have translations
- **NEW**: `whereTranslation($key, $value, $locales, $operator)` - Filter by translation value
- **NEW**: `orderByTranslation($key, $direction)` - Order results by translated field values using database-agnostic subqueries

#### Locale Resolution System
- **NEW**: `LocaleResolver` class for managing locale chains
- **NEW**: Uses Laravel's `app()->getLocale()` and `app()->getFallbackLocale()` by default
- **NEW**: `setLocales()` method for dynamic locale chain configuration
- **NEW**: `normalizeLocales()` for consistent locale parameter handling

### ⚡ Performance Improvements

#### Query Optimization
- **NEW**: Efficient eager loading via `withTranslations()` to prevent N+1 queries
- **IMPROVED**: `orderByTranslation()` uses Laravel's native query builder for database-agnostic subqueries
- **IMPROVED**: Search queries use `whereIn()` and `whereColumn()` for better index utilization
- **NEW**: Translation map indexing for O(1) attribute access after initial load
- **NEW**: Cached locale resolution within request lifecycle

#### Database Efficiency
- **NEW**: Database-agnostic SQL generation through Laravel's query grammar
- **NEW**: Proper correlated subqueries for ordering by translations
- **IMPROVED**: Eliminated redundant translation lookups with translation map caching

### 🔧 Developer Experience Enhancements

#### IDE Support & Documentation
- **NEW**: Comprehensive PHPDoc annotations for all scope methods in `HasTranslations` trait
- **NEW**: Full autocomplete support with parameter type hints (`string|array|null`)
- **NEW**: Inline documentation for all methods and parameters
- **NEW**: Return type declarations for proper method chaining support

#### Code Quality
- **NEW**: `getUntranslated($key)` - Access original table values bypassing translations
- **NEW**: `setTranslations($translations, $locale)` - Set multiple translations at once
- **NEW**: `getTranslations($key, $locales)` - Get translations for specific locales as array
- **NEW**: `getAllTranslations($key)` - Get all translations for a key across all locales
- **NEW**: Support for both `$translatable` property and `translatedKeys()` method
- **IMPROVED**: Consistent method signatures across all query scopes
- **IMPROVED**: Proper return statements in all macro definitions for method chaining

### 📚 Documentation Overhaul

#### Comprehensive README
- **UPDATED**: Complete rewrite of README.md with accurate API documentation
- **UPDATED**: Quick start guide matching actual implementation
- **UPDATED**: Locale resolution system documentation with examples
- **NEW**: API reference table with correct method signatures
- **NEW**: Performance optimization guidelines
- **UPDATED**: Configuration examples reflecting actual config structure
- **NEW**: Examples for `withTranslations()`, `whereHasTranslation()`, and `whereTranslation()`

#### Configuration Documentation
- **UPDATED**: Accurate config file structure with `auto_translate_attributes`
- **NEW**: Custom `LocaleResolver` implementation examples
- **NEW**: Dynamic locale configuration patterns

### 🔄 Breaking Changes

#### None
- All changes are backwards compatible
- Existing code will continue to work without modifications
- New features are additive and opt-in

### 🛠 Technical Improvements

#### Code Architecture
- **NEW**: `TranslatableScope` class with extension pattern following Laravel conventions
- **NEW**: Proper macro registration for all query scope methods
- **NEW**: Translation map caching for efficient attribute access
- **IMPROVED**: Return query builder from all scope macros for method chaining
- **NEW**: Database-agnostic query generation using Laravel's grammar system

#### Bug Fixes
- **FIXED**: `orderByTranslation()` now returns query builder instead of null
- **FIXED**: All search methods properly return query builder for chaining
- **FIXED**: `orderByTranslation()` uses database-agnostic subqueries to avoid SQL errors
- **FIXED**: Proper correlated subqueries in ordering to match correct translation records

### 📋 Complete API Reference

#### Query Scope Methods
```php
Model::withTranslations(?array $locales = null)
Model::searchByTranslation(string|array $key, string $search, string|array|null $locales = null, string $operator = 'like')
Model::searchByTranslationExact(string|array $key, string $search, string|array|null $locales = null)
Model::searchByTranslationStartsWith(string|array $key, string $search, string|array|null $locales = null)
Model::searchByTranslationEndsWith(string|array $key, string $search, string|array|null $locales = null)
Model::whereHasTranslation(string $key, string|array|null $locales = null)
Model::whereTranslation(string $key, string $value, string|array|null $locales = null, string $operator = 'exact')
Model::orderByTranslation(string $key, string $direction = 'asc')
```

#### Instance Methods
```php
$model->setTranslation(string $key, string $value, ?string $locale = null)
$model->setTranslations(array $translations, ?string $locale = null)
$model->getTranslation(string $key, string|array|null $locales = null)
$model->getTranslations(string $key, string|array|null $locales = null)
$model->getAllTranslations(string $key)
$model->hasTranslation(string $key, ?string $locale = null)
$model->getUntranslated(string $key)
```

### 🎯 Migration Guide

#### For New Users
1. Add `HasTranslations` trait to your models
2. Define `protected $translatable = ['field1', 'field2']`
3. Use `withTranslations()` when querying to eager load translations
4. Access translated fields normally: `$model->field1`

#### For Existing Users
- No code changes required
- All existing functionality preserved
- New query methods available immediately
- Consider using `withTranslations()` for better performance

---

### Credits

- **Bug Fixes**: Fixed return statements in all query scope macros
- **Database Compatibility**: Implemented database-agnostic orderBy using Laravel's query builder
- **Documentation**: Accurate README and CHANGELOG reflecting actual implementation
- **Code Quality**: Consistent method signatures and proper type hints throughout
