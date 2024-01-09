<?php

namespace mindtwo\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use mindtwo\LaravelTranslatable\Models\Translatable;

trait HasTranslations
{
    /**
     * Get all the translations for the model.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(config('translatable.model', Translatable::class), 'translatable');
    }

    /**
     * Check if the model has a translation for the given locale.
     */
    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        if (is_null($locale)) {
            $locale = app()->getLocale();
        }

        return $this->translations()
            ->where('key', $key)
            ->where('locale', $locale)
            ->exists();
    }

    /**
     * Get the translation for the given locale.
     */
    public function getTranslation(string $key, ?string $locale = null): Translatable
    {
        if (is_null($locale)) {
            $locale = app()->getLocale();
        }

        /** @var Translatable $translatable */
        $translatable = $this->translations()
            ->where('key', $key)
            ->where('locale', $locale)
            ->first();

        return $translatable;
    }

    /**
     * Get all or filtered translations for the model as collection.
     */
    public function getTranslations(?string $key = null, ?string $locale = null): Collection
    {
        /** @var Collection<Translatable> $translatableRecords */
        $translatableRecords = $this->translations()
            ->when(! is_null($key), function ($query) use ($key) {
                $query->where('key', $key);
            })->when(! is_null($locale), function ($query) use ($locale) {
                $query->where('locale', $locale);
            })->get();

        return $translatableRecords;
    }
}
