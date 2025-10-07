<?php

use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

return [
    'model' => Translatable::class,
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
];
