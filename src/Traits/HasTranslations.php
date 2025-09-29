<?php

namespace mindtwo\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;
use mindtwo\LaravelTranslatable\Scopes\TranslatableScope;

/**
 * Trait HasTranslations.
 *
 * This trait provides translation functionality for Eloquent models. It automatically
 * overrides specified model attributes with their translated values based on the current
 * locale and configured fallback chain.
 *
 * Query Scope Methods (added by TranslatableScope):
 *
 * @method static \Illuminate\Database\Eloquent\Builder withoutTranslations() Remove translation overrides and get base table values
 * @method static \Illuminate\Database\Eloquent\Builder orderByTranslation(string $key, ?string $locale = null, string $direction = 'asc') Order results by translated field value
 * @method static \Illuminate\Database\Eloquent\Builder searchByTranslation(string|array $key, string $search, ?string $locale = null, string $operator = 'like') Search in translated fields with locale fallback support
 * @method static \Illuminate\Database\Eloquent\Builder searchByTranslationExact(string|array $key, string $search, ?string $locale = null) Search for exact matches in translated fields
 * @method static \Illuminate\Database\Eloquent\Builder searchByTranslationStartsWith(string|array $key, string $search, ?string $locale = null) Search for translated fields that start with the given text
 * @method static \Illuminate\Database\Eloquent\Builder searchByTranslationEndsWith(string|array $key, string $search, ?string $locale = null) Search for translated fields that end with the given text
 *
 * Required Implementation:
 * Models using this trait must implement the translatedKeys() method that returns an array
 * of column names that should be translatable.
 *
 * Example:
 * ```php
 * public static function translatedKeys(): array
 * {
 *     return ['title', 'description'];
 * }
 * ```
 *
 * Optional Fallback Configuration:
 * Models can customize their fallback locale chain by implementing getTranslatableFallback()
 * method or defining a $translatableFallback property.
 *
 * @phpstan-ignore-next-line
 */
trait HasTranslations
{
    /**
     * Boot the trait.
     */
    protected static function bootHasTranslations(): void
    {
        static::addGlobalScope(new TranslatableScope);
    }

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
        $locale = $locale ?? $this->getFallbackTranslationLocale($locale);

        return $this->translations()
            ->where([
                'locale' => $locale,
                'key' => $key,
            ])
            ->when(
                is_array($locale),
                fn ($query) => $query->whereIn('locale', $locale),
                fn ($query) => $query->where('locale', $locale)
            )
            ->exists();
    }

    /**
     * Set the translation for the given locale.
     */
    public function setTranslation(string $key, string $value, ?string $locale = null): self
    {
        $locale = $locale ?? $this->getFallbackTranslationLocale($locale);

        if ($this->hasTranslation($key, $locale)) {
            $this->getTranslation($key, $locale)->update(['text' => $value]);
        } else {
            $this->translations()->create([
                'key' => $key,
                'locale' => $locale,
                'text' => $value,
            ]);
        }

        return $this;
    }

    /**
     * Get the translation for the given locale.
     */
    public function getTranslation(string $key, ?string $locale = null): ?Translatable
    {
        // Build locale priority array once
        $currentLocale = $locale ?? app()->getLocale();
        $fallbackLocale = $this->getFallbackTranslationLocale();

        // Build priority array efficiently
        $localePriority = [$currentLocale];
        if (is_array($fallbackLocale)) {
            $localePriority = array_merge($localePriority, $fallbackLocale);
        } elseif ($fallbackLocale !== $currentLocale) {
            $localePriority[] = $fallbackLocale;
        }

        // Remove duplicates while preserving order
        $localePriority = array_values(array_unique($localePriority));

        // Use a simpler query with ORDER BY instead of window functions
        /** @var ?Translatable $translatable */
        $translatable = $this->translations()
            ->where('key', $key)
            ->whereIn('locale', $localePriority)
            ->orderByRaw($this->buildLocaleOrderExpression($localePriority))
            ->orderBy('id', 'desc') // Tie-breaker for same locale
            ->first();

        return $translatable;
    }

    /**
     * Build ORDER BY expression for locale priority without using window functions.
     */
    private function buildLocaleOrderExpression(array $localePriority): string
    {
        $cases = [];
        foreach ($localePriority as $index => $locale) {
            if (is_null($locale)) {
                $cases[] = 'WHEN locale IS NULL THEN '.($index + 1);
            } else {
                $cases[] = 'WHEN locale = '.DB::connection()->getPdo()->quote($locale).' THEN '.($index + 1);
            }
        }

        return 'CASE '.implode(' ', $cases).' ELSE '.(count($localePriority) + 1).' END';
    }

    /**
     * Get all or filtered translations for the model as collection.
     * When locale is specified, includes fallback locales for better coverage.
     */
    public function getTranslations(?string $key = null, ?string $locale = null): Collection
    {
        $query = $this->translations();

        // Apply key filter first (most selective)
        if (! is_null($key)) {
            $query->where('key', $key);
        }

        // Apply locale filter with fallback support
        if (! is_null($locale)) {
            $resolvedLocale = app(LocaleResolver::class)->resolve($locale);
            $fallbackLocale = $this->getFallbackTranslationLocale();

            // Build locale list including fallbacks
            $locales = [$resolvedLocale];
            if (is_array($fallbackLocale)) {
                $locales = array_merge($locales, $fallbackLocale);
            } elseif ($fallbackLocale !== $resolvedLocale) {
                $locales[] = $fallbackLocale;
            }

            // Use whereIn for better performance with multiple locales
            $query->whereIn('locale', array_unique($locales));
        }

        /** @var Collection<Translatable> $translatableRecords */
        return $query->get();
    }

    /**
     * Get the untranslated value for a specific key.
     * This is useful for accessing the base value when no translation exists.
     */
    public function getUntranslated(string $key): ?string
    {
        return $this->attributes["_base_{$key}"] ?? null;
    }

    /**
     * Get the fallback translation locale.
     */
    public function getFallbackTranslationLocale(?string $locale = null): string|array
    {
        // Get the fallback locale from the model via method
        if (method_exists($this, 'getTranslatableFallback')) {
            return $this->getTranslatableFallback();
        }

        // Get the fallback locale from the model via method
        if (property_exists($this, 'translatableFallback')) {
            return $this->translatableFallback;
        }

        return app(LocaleResolver::class)->resolveFallback($locale);
    }
}
