<?php

namespace mindtwo\LaravelTranslatable\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use mindtwo\LaravelTranslatable\Models\Translatable;

interface IsTranslatable
{
    /**
     * Get all the translations for the model.
     */
    public function translations(): MorphMany;

    /**
     * Check if the model has a translation for the given locale.
     */
    public function hasTranslation(string $key, ?string $locale = null): bool;

    /**
     * Get the translation for the given locale.
     */
    public function getTranslation(string $key, ?string $locale = null): ?Translatable;

    /**
     * Get all or filtered translations for the model as collection.
     */
    public function getTranslations(?string $key = null, ?string $locale = null): Collection;
}
