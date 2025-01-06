<?php

use mindtwo\LaravelTranslatable\Models\Translatable;

return [
    'model' => Translatable::class,
    'resolver' => \mindtwo\LaravelTranslatable\Resolvers\LocaleResolver::class,
];
