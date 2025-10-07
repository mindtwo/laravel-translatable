<?php

namespace mindtwo\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;
use mindtwo\LaravelTranslatable\Scopes\TranslatableScope;

/**
 * Trait HasTranslations
 *
 * Provides translation functionality for Eloquent models using eager loading and optimized
 * attribute access. Supports locale fallback chains and automatic attribute translation.
 *
 * @property-read Collection<int, Translatable> $translations
 * @property-read int|null $translations_count
 *
 * @method static Builder<static>|static withTranslations(?array $locales = null) Eager load translations for specified locales (or default locale chain)
 * @method static Builder<static>|static searchByTranslation(string|array $key, string $search, string|array|null $locales = null, string $operator = 'like') Search in translated fields with locale fallback support
 * @method static Builder<static>|static searchByTranslationExact(string|array $key, string $search, string|array|null $locales = null) Search for exact matches in translated fields
 * @method static Builder<static>|static searchByTranslationStartsWith(string|array $key, string $search, string|array|null $locales = null) Search for translated fields that start with the given text
 * @method static Builder<static>|static searchByTranslationEndsWith(string|array $key, string $search, string|array|null $locales = null) Search for translated fields that end with the given text
 * @method static Builder<static>|static whereHasTranslation(string $key, string|array|null $locales = null) Filter models that have a translation for the given key and locale(s)
 * @method static Builder<static>|static whereTranslation(string $key, string $value, string|array|null $locales = null, string $operator = 'exact') Filter models where translation matches a specific value
 * @method static Builder<static>|static orderByTranslation(string $key, string $direction = 'asc') Order by translated field values
 *
 * @phpstan-ignore trait.unused
 */
trait HasTranslations
{
    protected ?array $resolvedLocales = null;

    protected ?array $translationsMap = null;

    protected ?array $cachedTranslatableAttributes = null;

    /**
     * Get all the translations for the model.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(config('translatable.model', Translatable::class), 'translatable');
    }

    /**
     * Boot the HasTranslations trait.
     */
    protected static function bootHasTranslations(): void
    {

        // Register the TranslatableScope automatically
        static::addGlobalScope(new TranslatableScope);

        static::retrieved(function ($model) {
            $model->indexTranslations();
        });
    }

    /**
     * Get the translated value for a given key using the locale fallback chain.
     */
    public function getTranslation(string $key, string|array|null $locales = null): mixed
    {
        $locales = $this->getResolvedLocales($locales);

        if (count($locales) === 0) {
            // No locales specified, return default attribute value
            return parent::getAttribute($key);
        }

        if ($this->translationsMap === null) {
            $this->indexTranslations();
        }

        foreach ($locales as $locale) {
            $this->ensureLocaleLoaded($locale);

            if (isset($this->translationsMap[$locale][$key])) {
                return $this->translationsMap[$locale][$key];
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Get the untranslated value for a given key.
     * This returns the base attribute value if no translation exists.
     *
     * @param  string  $key  The translation key to retrieve
     * @return mixed The untranslated value
     */
    public function getUntranslated(string $key): mixed
    {
        // Return the base attribute value if no translation exists
        return parent::getAttribute($key);
    }

    /**
     * Get all translations for a specific key across all locales.
     * Returns an associative array with locale as key and translation as value.
     *
     * @param  string  $key  The translation key to retrieve
     * @return array<string, string> Associative array of translations by locale
     */
    public function getAllTranslations(string $key): array
    {
        return $this->translations()
            ->where('key', $key)
            ->pluck('text', 'locale')
            ->toArray();
    }

    /**
     * Get translations for a specific key across all locales.
     * Returns an associative array with locale as key and translation as value.
     *
     * @param  string  $key  The translation key to retrieve
     * @param  string|array<string>|null  $locales  Optional specific locales to filter by
     * @return array<string, string> Associative array of translations by locale
     */
    public function getTranslations(string $key, string|array|null $locales = null): array
    {

        $locales = $this->getResolvedLocales($locales);

        if (count($locales) === 0) {
            // No locales specified, return empty array
            return [];
        }

        if ($this->translationsMap === null) {
            $this->indexTranslations();
        }

        $translations = [];

        foreach ($locales as $locale) {
            $this->ensureLocaleLoaded($locale);

            if (isset($this->translationsMap[$locale][$key])) {
                $translations[$locale] = $this->translationsMap[$locale][$key];
            }
        }

        return $translations;
    }

    /**
     * Check if the model has a translation for the given key and locale.
     */
    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->getLocaleChain()[0] ?? app()->getLocale();

        return $this->translations()
            ->where('locale', '=', $locale)
            ->where('key', '=', $key)
            ->exists();
    }

    /**
     * Set or update the translation for the given key and locale.
     */
    public function setTranslation(string $key, string $value, ?string $locale = null): self
    {
        $locale = $locale ?? $this->getLocaleChain()[0] ?? app()->getLocale();

        $this->translations()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            ['text' => $value]
        );

        // Update cache directly without forcing full reindex
        if ($this->translationsMap !== null) {
            $this->translationsMap[$locale][$key] = $value;
        }

        return $this;
    }

    /**
     * Set multiple translations at once for a given locale.
     */
    public function setTranslations(array $translations, ?string $locale = null): self
    {
        $locale = $locale ?? $this->getLocaleChain()[0] ?? app()->getLocale();

        foreach ($translations as $key => $value) {
            $this->translations()->updateOrCreate(
                ['key' => $key, 'locale' => $locale],
                ['text' => $value]
            );
        }

        // Update cache directly without forcing full reindex
        if ($this->translationsMap !== null) {
            foreach ($translations as $key => $value) {
                $this->translationsMap[$locale][$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Override getAttribute to automatically translate attributes.
     */
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);

        if (
            $this->autoTranslateAttributes() &&
            in_array($key, $this->getTranslatableAttributes(), true)
        ) {
            $translation = $this->getTranslation($key);

            return $translation ?? $value;
        }

        return $value;
    }

    /**
     * Build an optimized index of translations for O(1) attribute access.
     */
    protected function indexTranslations(): void
    {
        if ($this->relationLoaded('translations')) {
            $this->translationsMap = [];

            foreach ($this->translations as $translation) {
                $this->translationsMap[$translation->locale][$translation->key] = $translation->text;
            }

            return;
        }

        $this->translationsMap = [];
    }

    /**
     * Get the locale fallback chain for this model.
     */
    protected function getLocaleChain(): array
    {
        return resolve(LocaleResolver::class)->getLocales();
    }

    /**
     * Resolve and cache the locale chain for translation lookups.
     */
    protected function getResolvedLocales(string|array|null $locales = null): array
    {
        if ($locales !== null) {
            return is_array($locales) ? $locales : [$locales];
        }

        if ($this->resolvedLocales !== null) {
            return $this->resolvedLocales;
        }

        $this->resolvedLocales = $this->getLocaleChain();

        return $this->resolvedLocales;
    }

    /**
     * Check if automatic attribute translation is enabled for this model.
     */
    protected function autoTranslateAttributes(): bool
    {
        return config('translatable.auto_translate_attributes', true);
    }

    /**
     * Get the list of translatable attribute keys.
     */
    protected function getTranslatableAttributes(): array
    {
        if ($this->cachedTranslatableAttributes !== null) {
            return $this->cachedTranslatableAttributes;
        }

        // Backwards compatibility: check for translatedKeys method first
        if (method_exists($this, 'translatedKeys')) {
            return $this->cachedTranslatableAttributes = $this->translatedKeys();
        }

        // Check for $translatable property (similar to spatie/laravel-translatable)
        if (property_exists($this, 'translatable') && is_array($this->translatable)) {
            return $this->cachedTranslatableAttributes = $this->translatable;
        }

        return $this->cachedTranslatableAttributes = [];
    }

    /**
     * Ensure translations for a specific locale are loaded and indexed.
     */
    protected function ensureLocaleLoaded(string $locale): void
    {
        if ($this->translationsMap !== null && isset($this->translationsMap[$locale])) {
            return;
        }

        $translations = $this->translations()
            ->where('locale', $locale)
            ->get();

        if ($this->translationsMap === null) {
            $this->translationsMap = [];
        }

        foreach ($translations as $translation) {
            $this->translationsMap[$locale][$translation->key] = $translation->text;
        }

        if ($this->relationLoaded('translations')) {
            $this->setRelation('translations', $this->translations->merge($translations));
        }
    }
}
