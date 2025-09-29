# Laravel Translatable Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mindtwo/laravel-translatable.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-translatable)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-translatable/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mindtwo/laravel-translatable/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mindtwo/laravel-translatable/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mindtwo/laravel-translatable/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mindtwo/laravel-translatable.svg?style=flat-square)](https://packagist.org/packages/mindtwo/laravel-translatable)


## Overview

The `mindtwo/laravel-translatable` package provides a simple and effective way to manage multilingual models in a Laravel application. It allows you to easily translate Eloquent model attributes into multiple languages without the need for separate tables for each language.

## Features

- ✅ **Automatic Translation Override**: Translated fields automatically override base model attributes
- ✅ **Fallback Locale Chains**: Sophisticated fallback system (e.g., `fr` → `de` → `en`)
- ✅ **Query Scope Methods**: Search, filter, and order by translated content
- ✅ **Performance Optimized**: Efficient database queries with proper indexing
- ✅ **IDE Autocomplete**: Full PHPDoc support for all methods
- ✅ **Flexible Configuration**: Global and per-model locale chain configuration
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
    | Default Locale Chain
    |--------------------------------------------------------------------------
    |
    | Define a default fallback locale chain for all translatable models.
    | When a translation is not found in the current locale, the system will
    | try each locale in this chain until a translation is found.
    |
    | Example: ['de', 'en', 'fr'] means:
    | Current Locale → German → English → French → Base Table Value
    |
    */
    'locale_chain' => [
        // 'de', // German as primary fallback
        // 'en', // English as secondary fallback
        // 'fr', // French as tertiary fallback
    ],
];
```


## Quick Start

### 1. Create a Translatable Model

```php
<?php

use Illuminate\Database\Eloquent\Model;
use mindtwo\LaravelTranslatable\Traits\HasTranslations;
use mindtwo\LaravelTranslatable\Contracts\IsTranslatable;

class Product extends Model implements IsTranslatable
{
    use HasTranslations;

    protected $fillable = ['title', 'description', 'price'];

    /**
     * Define which fields are translatable
     */
    public static function translatedKeys(): array
    {
        return ['title', 'description'];
    }

    /**
     * Optional: Custom fallback locale chain for this model
     */
    public function getTranslatableFallback(): array
    {
        return ['de', 'en', 'fr']; // German → English → French
    }
}
```

### 2. Add Translations

```php
$product = Product::create([
    'title' => 'Default Title',
    'description' => 'Default Description',
    'price' => 99.99
]);

// Add translations
$product->setTranslation('title', 'English Product', 'en');
$product->setTranslation('title', 'Deutsches Produkt', 'de');
$product->setTranslation('title', 'Produit Français', 'fr');

$product->setTranslation('description', 'English Description', 'en');
$product->setTranslation('description', 'Deutsche Beschreibung', 'de');
```

### 3. Automatic Translation Override

```php
// Set application locale
app()->setLocale('de');

$product = Product::first();
echo $product->title; // "Deutsches Produkt" (automatically translated!)
echo $product->description; // "Deutsche Beschreibung"
echo $product->price; // 99.99 (non-translated field remains unchanged)

// Get original value if needed
echo $product->getUntranslated('title'); // "Default Title"
```

## Advanced Usage

### Query Scope Methods

All scope methods support IDE autocomplete and have full parameter type hints:

```php
// Search in translated fields (with fallback locale support)
$products = Product::searchByTranslation('title', 'Product')->get();

// Exact match search
$exact = Product::searchByTranslationExact('title', 'Deutsches Produkt')->get();

// Prefix/suffix search
$startsWith = Product::searchByTranslationStartsWith('title', 'German')->get();
$endsWith = Product::searchByTranslationEndsWith('description', 'warranty')->get();

// Multiple field search
$multiField = Product::searchByTranslation(['title', 'description'], 'search term')->get();

// Order by translated field
$sorted = Product::orderByTranslation('title', 'en', 'asc')->get();

// Get base table values (without translations)
$baseProducts = Product::withoutTranslations()->get();

// Method chaining works perfectly
$results = Product::searchByTranslationStartsWith('title', 'Premium')
    ->orderByTranslation('title')
    ->limit(10)
    ->get();
```

### Fallback Locale Chains

#### Global Configuration

Configure a default fallback chain in your `config/translatable.php`:

```php
'locale_chain' => ['de', 'en', 'fr']
```

This creates the fallback order: **Current Locale** → **German** → **English** → **French** → **Base Table Value**

#### Model-Specific Configuration

Override the global chain for specific models:

```php
class Product extends Model implements IsTranslatable
{
    use HasTranslations;

    // Method approach
    public function getTranslatableFallback(): array
    {
        return ['es', 'en']; // Spanish → English for this model
    }

    // Or property approach
    protected $translatableFallback = ['es', 'en'];
}
```

### Working with Translation Data

```php
// Check if translation exists
if ($product->hasTranslation('title', 'de')) {
    echo "German translation available";
}

// Get specific translation object
$translation = $product->getTranslation('title', 'de');
echo $translation->text; // "Deutsches Produkt"

// Get all translations for a key
$titleTranslations = $product->getTranslations('title');

// Get all translations for a locale
$germanTranslations = $product->getTranslations(null, 'de');

// Get all translations
$allTranslations = $product->getTranslations();
```

### Performance Features

- **Automatic Query Optimization**: Efficient subqueries with proper locale priority
- **Index-Friendly Queries**: Uses `whereIn()` for better database performance
- **Fallback Chain Caching**: Locale priorities calculated once per query
- **Smart Column Selection**: Only translated fields are overridden

## Configuration Examples

### E-commerce Setup
```php
// config/translatable.php
'locale_chain' => ['de', 'en', 'fr', 'es']

// Product.php
public function getTranslatableFallback(): array
{
    return ['en', 'de']; // Products: English → German
}

// Category.php
public function getTranslatableFallback(): array
{
    return ['de', 'en', 'fr']; // Categories: German → English → French
}
```

### Multi-Region Content
```php
// config/translatable.php
'locale_chain' => ['en', 'fr', 'de']

// Article.php
public static function translatedKeys(): array
{
    return ['title', 'content', 'excerpt', 'meta_description'];
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
