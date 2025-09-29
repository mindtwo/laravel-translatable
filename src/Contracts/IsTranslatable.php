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
     * If locale is null, it should check for the default locale.
     */
    public function hasTranslation(string $key, ?string $locale = null): bool;

    /**
     * Set the translation for the given locale.
     * If locale is null, it should check for the default locale.
     */
    public function setTranslation(string $key, string $value, ?string $locale = null): self;

    /**
     * Get the translation for the given locale.
     * If locale is null, it should check for the default locale.
     */
    public function getTranslation(string $key, ?string $locale = null): ?Translatable;

    /**
     * Get all or filtered translations for the model as collection.
     */
    public function getTranslations(?string $key = null, ?string $locale = null): Collection;

    /**
     * Get the fallback translation locale.
     * If an array is returned it should be used to check for multiple locales.
     * If a string is returned, it should be used as the default locale.
     *
     * @return string|array<string>
     */
    public function getFallbackTranslationLocale(?string $locale = null): string|array;
}
