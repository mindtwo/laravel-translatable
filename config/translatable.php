<?php

use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Translatable Model
    |--------------------------------------------------------------------------
    |
    | The model class to use for storing translations.
    |
    */
    'model' => Translatable::class,

    /*
    |--------------------------------------------------------------------------
    | Locale Resolver
    |--------------------------------------------------------------------------
    |
    | The resolver class to use for determining locale fallback chains.
    |
    */
    'resolver' => LocaleResolver::class,

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

    /*
    |--------------------------------------------------------------------------
    | Default Locale on Model
    |--------------------------------------------------------------------------
    |
    | When enabled, the default locale returned by the locale resolver is considered
    | to be stored on the model itself, i.e. not available in the translatable table
    | but stored directly in fields in the model table.
    |
    */
    'default_locale_on_model' => false,
];
