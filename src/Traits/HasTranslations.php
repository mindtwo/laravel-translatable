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
 * @method static \Illuminate\Database\Eloquent\Builder searchByTranslation(string|array $key, string $search, ?string $locale = null)
 * @method static \Illuminate\Database\Eloquent\Builder withOutTranslations()
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
        // Get the locale
        $locale = $locale ?? $this->getFallbackTranslationLocale($locale);
        $fallbackLocale = $this->getFallbackTranslationLocale();

        // Merge locale and fallback
        $locale = collect([$locale])
            ->when($fallbackLocale !== $locale, function ($collection) use ($fallbackLocale) {
                return $collection->push($fallbackLocale);
            });

        // Get the translatable records by priority
        $subQuery = $this->buildLocalePriorityQuery($locale->all())
            ->where('key', $key);

        /** @var ?Translatable $translatable */
        $translatable = $this->translations()
            ->whereIn('id', function ($query) use ($subQuery) {
                $query->fromSub($subQuery, 'ranked')
                    ->where('rn', 1)
                    ->select('id');
            })
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
            ->when(! is_null($locale), function ($query) use ($locale) {
                $query->where('locale', app(LocaleResolver::class)->resolve($locale));
            })
            ->when(! is_null($key), function ($query) use ($key) {
                $query->where('key', $key);
            })->get();

        return $translatableRecords;
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
    public function getFallbackTranslationLocale(?string $locale = null): string
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

    /**
     * Build a query to rank certificates based on locale priority.
     * This method constructs a subquery that ranks certificates based on the user's locale preferences.
     * It uses a CASE WHEN expression to assign ranks based on the locale.
     *
     * @param  string[]  $localePriority
     */
    protected function buildLocalePriorityQuery(array $localePriority): \Illuminate\Database\Query\Builder
    {
        // Build CASE WHEN expression
        $caseParts = [];
        $params = [];

        foreach ($localePriority as $index => $locale) {
            if (is_null($locale)) {
                $caseParts[] = 'WHEN locale IS NULL THEN '.($index + 1);
            } else {
                $caseParts[] = 'WHEN locale = ? THEN '.($index + 1);
                $params[] = $locale;
            }
        }

        $caseSql = 'CASE '.implode(' ', $caseParts).' ELSE '.(count($localePriority) + 1).' END';

        // Create Sub query
        return DB::table('translatable')
            ->select('id')
            ->selectRaw("ROW_NUMBER() OVER (
                PARTITION BY translatable_id, translatable_type, key
                ORDER BY {$caseSql}, id DESC
            ) as rn", $params)
            ->whereIn('locale', $localePriority);
    }
}
