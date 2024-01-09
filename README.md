# mindtwo/laravel-translatable

## Overview

The `mindtwo/laravel-translatable` package provides a simple and effective way to manage multilingual models in a Laravel application. It allows you to easily translate Eloquent model attributes into multiple languages without the need for separate tables for each language.

## Features

- Easy integration with Eloquent models.
- Seamless translation of model attributes.
- Simple configuration and usage.

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

use mindtwo\LaravelTranslatable\Models\Translatable;

return [
    'model' => Translatable::class,
];
```


## Setup
After installation, you need to publish and run the migrations:

```bash
php artisan vendor:publish --provider="mindtwo\LaravelTranslatable\TranslatableServiceProvider"
php artisan migrate
```

This will create a `translatable` table in your database.

## Usage

### Creating Translatable Models
1. **Migration**: Use the provided `create_translatable_table.php` migration to set up the `translatable` table.

2. **Model Trait**: Include the `HasTranslations` trait in your model. This trait provides methods to interact with translations.

    ```php
    use mindtwo\LaravelTranslatable\Traits\HasTranslations;

    class YourModel extends Model
    {
        use HasTranslations;

        // Model content
    }
    ```

3. **Translatable Model**: The `Translatable` model is used to store translations. It uses the `AutoCreateUuid` trait for unique identification.

    ```php
    use mindtwo\LaravelTranslatable\Models\Translatable;

    // Usage within your application logic
    ```

### Working with Translations
- **Add a Translation**:
    ```php
    $yourModelInstance->translations()->create([
        'key' => 'your_key',
        'locale' => 'en',
        'text' => 'Your translation text'
    ]);
    ```

- **Check for a Translation**:
    ```php
    $exists = $yourModelInstance->hasTranslation('your_key', 'en');
    ```

- **Get a Translation**:
    ```php
    $translation = $yourModelInstance->getTranslation('your_key', 'en');
    ```

- **Retrieve All Translations**:
    ```php
    $translations = $yourModelInstance->getTranslations();
    ```

## Best Practices
- Always check if a translation exists before attempting to retrieve it.
- Use consistent keys across different models to maintain clarity.
- Regularly back up your translations as they are stored in the database.


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
