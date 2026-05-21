<?php

namespace mindtwo\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;
use mindtwo\LaravelTranslatable\Scopes\TranslatableScope;

/**
 * Add translation support to an Eloquent model.
 *
 * @property-read Collection<int, Translatable> $translations
 * @property-read int|null $translations_count
 *
 * @method static Builder<static>|static withTranslations(?array $locales = null)
 * @method static Builder<static>|static searchByTranslation(string|array $key, string $search, string|array|null $locales = null, string $operator = 'like', string $boolean = 'and')
 * @method static Builder<static>|static searchByTranslationExact(string|array $key, string $search, string|array|null $locales = null, string $boolean = 'and')
 * @method static Builder<static>|static searchByTranslationStartsWith(string|array $key, string $search, string|array|null $locales = null, string $boolean = 'and')
 * @method static Builder<static>|static searchByTranslationEndsWith(string|array $key, string $search, string|array|null $locales = null, string $boolean = 'and')
 * @method static Builder<static>|static whereHasTranslation(string $key, string|array|null $locales = null, string $boolean = 'and')
 * @method static Builder<static>|static whereTranslation(string $key, string $value, string|array|null $locales = null, string $operator = 'exact')
 * @method static Builder<static>|static orderByTranslation(string $key, string $direction = 'asc')
 *
 * @phpstan-ignore trait.unused
 */
trait HasTranslations
{
    /**
     * The resolved locale fallback chain for this instance.
     *
     * @var array<int, string>|null
     */
    protected ?array $resolvedLocales = null;

    /**
     * The indexed translations keyed by locale and key.
     *
     * @var array<string, array<string, string>>|null
     */
    protected ?array $translationsMap = null;

    /**
     * The locales that have been queried for this instance.
     *
     * @var array<string, true>
     */
    protected array $loadedLocales = [];

    /**
     * The cached list of translatable attribute keys.
     *
     * @var array<int, string>|null
     */
    protected ?array $cachedTranslatableAttributes = null;

    /**
     * Boot the has-translations trait for a model.
     */
    protected static function bootHasTranslations(): void
    {
        static::addGlobalScope(new TranslatableScope);
    }

    /**
     * Get all of the translations for the model.
     *
     * @return MorphMany<Translatable, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(config('translatable.model', Translatable::class), 'translatable');
    }

    /**
     * Get the translated value for the given key using the locale fallback chain.
     *
     * @param  string|array<int, string>|null  $locales
     */
    public function getTranslation(string $key, string|array|null $locales = null): mixed
    {
        $locales = $this->getResolvedLocales($locales);

        if (count($locales) === 0) {
            return parent::getAttribute($key);
        }

        if ($this->translationsMap === null) {
            $this->indexTranslations();
        }

        foreach ($locales as $locale) {
            if ($locale === $this->defaultLocaleOnModel()) {
                $value = parent::getAttribute($key);

                if ($value !== null) {
                    return $value;
                }

                continue;
            }

            $this->ensureLocaleLoaded($locale);

            if (isset($this->translationsMap[$locale][$key])) {
                return $this->translationsMap[$locale][$key];
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Get the untranslated value for the given key from the model's own table.
     */
    public function getUntranslated(string $key): mixed
    {
        return parent::getAttribute($key);
    }

    /**
     * Get every translation for the given key, keyed by locale.
     *
     * @return array<string, string>
     */
    public function getAllTranslations(string $key): array
    {
        return $this->translations()
            ->where('key', $key)
            ->pluck('text', 'locale')
            ->toArray();
    }

    /**
     * Get the translations for the given key, filtered by the locale chain.
     *
     * @param  string|array<int, string>|null  $locales
     * @return array<string, string>
     */
    public function getTranslations(string $key, string|array|null $locales = null): array
    {
        $locales = $this->getResolvedLocales($locales);

        if (count($locales) === 0) {
            return [];
        }

        if ($this->translationsMap === null) {
            $this->indexTranslations();
        }

        $translations = [];

        foreach ($locales as $locale) {
            if ($locale === $this->defaultLocaleOnModel()) {
                $translations[$locale] = parent::getAttribute($key);

                continue;
            }

            $this->ensureLocaleLoaded($locale);

            if (isset($this->translationsMap[$locale][$key])) {
                $translations[$locale] = $this->translationsMap[$locale][$key];
            }
        }

        return $translations;
    }

    /**
     * Determine if the model has a translation for the given key and locale.
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
     *
     * @return $this
     */
    public function setTranslation(string $key, string $value, ?string $locale = null): self
    {
        $locale = $locale ?? $this->getLocaleChain()[0] ?? app()->getLocale();

        $this->translations()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            ['text' => $value]
        );

        if ($this->translationsMap !== null) {
            $this->translationsMap[$locale][$key] = $value;
        }

        return $this;
    }

    /**
     * Set or update multiple translations at once for the given locale.
     *
     * @param  array<string, string>  $translations
     * @return $this
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

        if ($this->translationsMap !== null) {
            foreach ($translations as $key => $value) {
                $this->translationsMap[$locale][$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Get an attribute from the model, automatically translating it when applicable.
     *
     * @param  string  $key
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
     * Build an indexed map of translations for O(1) attribute access.
     */
    public function indexTranslations(): void
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
     * Get the default locale that is stored on the model itself, if any.
     */
    public function defaultLocaleOnModel(): ?string
    {
        if (config('translatable.default_locale_on_model')) {
            return resolve(LocaleResolver::class)->getDefaultLocale();
        }

        return null;
    }

    /**
     * Mark the given locales as already loaded for this instance.
     *
     * @param  array<int, string>  $locales
     */
    public function addLoadedLocales(array $locales): void
    {
        foreach ($locales as $locale) {
            $this->loadedLocales[$locale] = true;
        }
    }

    /**
     * Get the locale fallback chain for this model.
     *
     * @return array<int, string>
     */
    protected function getLocaleChain(): array
    {
        return resolve(LocaleResolver::class)->getLocales();
    }

    /**
     * Resolve and cache the locale chain for translation lookups.
     *
     * @param  string|array<int, string>|null  $locales
     * @return array<int, string>
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
     * Determine if automatic attribute translation is enabled for this model.
     */
    protected function autoTranslateAttributes(): bool
    {
        return config('translatable.auto_translate_attributes', true);
    }

    /**
     * Get the translatable attribute keys defined on the model.
     *
     * @return array<int, string>
     */
    protected function getTranslatableAttributes(): array
    {
        if ($this->cachedTranslatableAttributes !== null) {
            return $this->cachedTranslatableAttributes;
        }

        if (method_exists($this, 'translatedKeys')) {
            return $this->cachedTranslatableAttributes = $this->translatedKeys();
        }

        if (property_exists($this, 'translatable') && is_array($this->translatable)) {
            return $this->cachedTranslatableAttributes = $this->translatable;
        }

        return $this->cachedTranslatableAttributes = [];
    }

    /**
     * Ensure the translations for the given locale are loaded and indexed.
     */
    protected function ensureLocaleLoaded(string $locale): void
    {
        if ($this->translationsMap !== null && isset($this->translationsMap[$locale])) {
            return;
        }

        if (isset($this->loadedLocales[$locale])) {
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

        $this->loadedLocales[$locale] = true;

        if ($this->relationLoaded('translations')) {
            $this->setRelation('translations', $this->translations->merge($translations));
        }
    }
}
