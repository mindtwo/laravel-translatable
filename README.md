# Laravel Translatable Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-translatable.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-translatable)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-translatable/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-translatable/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-translatable/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mindtwo/laravel-translatable/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-translatable.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-translatable)


## Overview

The `mindtwo/laravel-translatable` package provides a simple and effective way to manage multilingual models in a Laravel application. It allows you to easily translate Eloquent model attributes into multiple languages without the need for separate tables for each language.

## Features

- ✅ **Automatic Translation Override**: Translated fields automatically override base model attributes
- ✅ **Fallback Locale Support**: Uses Laravel's app locale and fallback locale
- ✅ **Query Scope Methods**: Search, filter, and order by translated content
- ✅ **Performance Optimized**: Efficient database queries with proper indexing
- ✅ **IDE Autocomplete**: Full PHPDoc support for all methods
- ✅ **Flexible Configuration**: Customizable locale resolution and auto-translation
- ✅ **Laravel Integration**: Works seamlessly with Eloquent relationships and collections

## Installation

To install the package, run the following command in your Laravel project:

```bash
composer require mindtwo/laravel-translatable
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="translatable-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="translatable-config"
```

This is the contents of the published config file:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Translatable Model
    |--------------------------------------------------------------------------
    |
    | The model class to use for storing translations.
    |
    */
    'model' => \mindtwo\LaravelTranslatable\Models\Translatable::class,

    /*
    |--------------------------------------------------------------------------
    | Locale Resolver
    |--------------------------------------------------------------------------
    |
    | The resolver class to use for determining locale fallback chains.
    |
    */
    'resolver' => \mindtwo\LaravelTranslatable\Resolvers\LocaleResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Auto Translate Attributes
    |--------------------------------------------------------------------------
    |
    | When enabled, translatable attributes will be automatically translated
    | when accessed via magic attribute accessors (e.g., $model->title).
    | This can be overridden per model by implementing the autoTranslateAttributes() method.
    |
    */
    'auto_translate_attributes' => true,
];
```


## Quick Start

### 1. Create a Translatable Model

```php
<?php

use Illuminate\Database\Eloquent\Model;
use mindtwo\LaravelTranslatable\Traits\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    protected $fillable = ['title', 'description', 'price'];

    /**
     * Define which fields are translatable
     */
    protected $translatable = ['title', 'description'];
}
```

### 2. Add Translations

```php
$product = Product::create([
    'title' => 'Default Title',
    'description' => 'Default Description',
    'price' => 99.99
]);

// Add individual translations
$product->setTranslation('title', 'English Product', 'en');
$product->setTranslation('title', 'Deutsches Produkt', 'de');
$product->setTranslation('title', 'Produit Français', 'fr');

// Or add multiple translations at once for a locale
$product->setTranslations([
    'title' => 'English Product',
    'description' => 'English Description',
], 'en');
```

### 3. Automatic Translation Override

```php
// Set application locale
app()->setLocale('de');

// Load model with translations
$product = Product::withTranslations()->first();
echo $product->title; // "Deutsches Produkt" (automatically translated!)
echo $product->description; // "Deutsche Beschreibung"
echo $product->price; // 99.99 (non-translated field remains unchanged)

// Get original value if needed
echo $product->getUntranslated('title'); // "Default Title"

// Get specific translation
echo $product->getTranslation('title', 'en'); // "English Product"

// Get all translations for a key
$titles = $product->getAllTranslations('title');
// ['en' => 'English Product', 'de' => 'Deutsches Produkt', 'fr' => 'Produit Français']
```

## Advanced Usage

### Query Scope Methods

All scope methods support IDE autocomplete and have full parameter type hints:

```php
// Eager load translations for current locale
$products = Product::withTranslations()->get();

// Eager load translations for specific locales
$products = Product::withTranslations(['en', 'de'])->get();

// Search in translated fields (with fallback locale support)
$products = Product::searchByTranslation('title', 'Product')->get();

// Search in specific locales
$products = Product::searchByTranslation('title', 'Product', ['en', 'de'])->get();

// Exact match search
$exact = Product::searchByTranslationExact('title', 'Deutsches Produkt')->get();

// Prefix/suffix search
$startsWith = Product::searchByTranslationStartsWith('title', 'German')->get();
$endsWith = Product::searchByTranslationEndsWith('description', 'warranty')->get();

// Multiple field search
$multiField = Product::searchByTranslation(['title', 'description'], 'search term')->get();

// Filter models that have a translation
$hasTitle = Product::whereHasTranslation('title')->get();
$hasGermanTitle = Product::whereHasTranslation('title', 'de')->get();

// Filter by exact translation value
$products = Product::whereTranslation('title', 'Exact Title')->get();

// Order by translated field
$sorted = Product::orderByTranslation('title', 'asc')->get();

// Method chaining works perfectly
$results = Product::searchByTranslationStartsWith('title', 'Premium')
    ->whereHasTranslation('description')
    ->orderByTranslation('title')
    ->limit(10)
    ->get();
```

### Locale Resolution

By default, the package uses Laravel's built-in locale configuration:

```php
// The LocaleResolver provides locales in this order:
// 1. Current application locale: app()->getLocale()
// 2. Fallback locale: app()->getFallbackLocale()
```

#### Custom Locale Resolution

You can customize locale resolution by extending the `LocaleResolver`:

```php
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

class CustomLocaleResolver extends LocaleResolver
{
    public function getLocales(): array
    {
        // Return custom locale chain
        return ['de', 'en', 'fr'];
    }
}
```

Then register it in your `config/translatable.php`:

```php
'resolver' => \App\Services\CustomLocaleResolver::class,
```

Or set locales dynamically:

```php
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

$resolver = app(LocaleResolver::class);
$resolver->setLocales(['de', 'en', 'fr']);

// Now queries will use this locale chain
$products = Product::withTranslations()->get();
```

### Working with Translation Data

```php
// Check if translation exists
if ($product->hasTranslation('title', 'de')) {
    echo "German translation available";
}

// Get specific translation (returns translated text)
$translation = $product->getTranslation('title', 'en');
echo $translation; // "English Product"

// Get translations for specific locales
$translations = $product->getTranslations('title', ['en', 'de']);
// ['en' => 'English Product', 'de' => 'Deutsches Produkt']

// Get all translations for a key
$allTitles = $product->getAllTranslations('title');
// ['en' => 'English Product', 'de' => 'Deutsches Produkt', 'fr' => 'Produit Français']

// Access the translations relationship
$translationModels = $product->translations;
foreach ($translationModels as $translation) {
    echo "{$translation->locale}: {$translation->key} = {$translation->text}";
}
```

### Performance Features

- **Efficient Eager Loading**: Use `withTranslations()` to eager load translations and avoid N+1 queries
- **Optimized Subqueries**: Order by translation uses database-agnostic subqueries
- **Index-Friendly Queries**: Uses `whereIn()` and `whereColumn()` for better database performance
- **Cached Locale Resolution**: Locale chain resolved once per request
- **Translation Map Indexing**: O(1) attribute access after initial load

## Configuration Examples

### E-commerce Setup
```php
// Product.php
class Product extends Model
{
    use HasTranslations;

    protected $translatable = ['title', 'description', 'features'];
}

// Category.php
class Category extends Model
{
    use HasTranslations;

    protected $translatable = ['name', 'description'];
}
```

### Multi-Region Content
```php
// Article.php
class Article extends Model
{
    use HasTranslations;

    protected $translatable = ['title', 'content', 'excerpt', 'meta_description'];

    // Disable auto-translation for this model if needed
    protected function autoTranslateAttributes(): bool
    {
        return false;
    }
}
```

## API Reference

### Instance Methods

| Method | Description | Parameters |
|--------|-------------|------------|
| `setTranslation($key, $value, $locale)` | Set translation for a field | `string $key`, `string $value`, `?string $locale` |
| `setTranslations($translations, $locale)` | Set multiple translations at once | `array $translations`, `?string $locale` |
| `getTranslation($key, $locales)` | Get translated text with fallback | `string $key`, `string\|array\|null $locales` |
| `getTranslations($key, $locales)` | Get translations as array | `string $key`, `string\|array\|null $locales` |
| `getAllTranslations($key)` | Get all translations for a key | `string $key` |
| `hasTranslation($key, $locale)` | Check if translation exists | `string $key`, `?string $locale` |
| `getUntranslated($key)` | Get original table value | `string $key` |
| `translations()` | Get translations relationship | - |

### Query Scope Methods (Static)

| Method | Description | Parameters |
|--------|-------------|------------|
| `withTranslations($locales)` | Eager load translations | `?array $locales` |
| `searchByTranslation($key, $search, $locales, $operator)` | Search in translations | `string\|array $key`, `string $search`, `string\|array\|null $locales`, `string $operator` |
| `searchByTranslationExact($key, $search, $locales)` | Exact match search | `string\|array $key`, `string $search`, `string\|array\|null $locales` |
| `searchByTranslationStartsWith($key, $search, $locales)` | Prefix search | `string\|array $key`, `string $search`, `string\|array\|null $locales` |
| `searchByTranslationEndsWith($key, $search, $locales)` | Suffix search | `string\|array $key`, `string $search`, `string\|array\|null $locales` |
| `whereHasTranslation($key, $locales)` | Filter models with translation | `string $key`, `string\|array\|null $locales` |
| `whereTranslation($key, $value, $locales, $operator)` | Filter by translation value | `string $key`, `string $value`, `string\|array\|null $locales`, `string $operator` |
| `orderByTranslation($key, $direction)` | Order by translated field | `string $key`, `string $direction` |

### Required Configuration

Models using `HasTranslations` must define translatable fields:

```php
// Property approach (recommended)
protected $translatable = ['title', 'description'];

// Or method approach (for backwards compatibility)
public function translatedKeys(): array
{
    return ['title', 'description'];
}
```

### Optional Configuration

```php
// Disable auto-translation for specific model
protected function autoTranslateAttributes(): bool
{
    return false;
}
```

## IDE Support

The package includes comprehensive PHPDoc annotations for full IDE support:

- ✅ **Autocomplete** for all scope methods
- ✅ **Parameter hints** with proper types (`string|array`, `?string`)
- ✅ **Return type information** for method chaining
- ✅ **Inline documentation** on method hover
- ✅ **Static analysis support** (PHPStan compatible)


## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [mindtwo GmbH](https://github.com/mindtwo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
