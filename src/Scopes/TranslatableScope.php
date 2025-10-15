<?php

namespace mindtwo\LaravelTranslatable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

/**
 * TranslatableScope provides automatic translation functionality for Eloquent models.
 *
 * This scope automatically:
 * - Joins translation tables for models with translatedKeys()
 * - Overrides specified columns with their translated values using COALESCE
 * - Supports locale fallback chains for missing translations
 * - Adds query builder macros for translation-specific operations
 *
 * Applied automatically when using the HasTranslations trait.
 *
 * Added Query Builder Methods:
 * - withoutTranslations(): Remove translation overrides
 * - orderByTranslation(): Order by translated field values
 * - searchByTranslation(): Search in translated fields with fallback support
 * - searchByTranslationExact(): Exact match search in translations
 * - searchByTranslationStartsWith(): Prefix search in translations
 * - searchByTranslationEndsWith(): Suffix search in translations
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class TranslatableScope implements \Illuminate\Database\Eloquent\Scope
{
    /**
     * The extensions to be added to the builder.
     */
    protected $extensions = ['WithTranslations', 'SearchByTranslation', 'WhereHasTranslation', 'WhereTranslation', 'OrderByTranslation'];

    /**
     * {@inheritDoc}
     */
    public function apply(Builder $builder, $model): void {}

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  Builder<*>  $builder
     * @return void
     */
    public function extend($builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Eager load translations for specified locales.
     */
    public function addWithTranslations(Builder $builder): void
    {

        $builder->macro('withTranslations', function (Builder $query, ?array $locales = null) {

            $locales = $locales ?? resolve(LocaleResolver::class)->getLocales();

            if (count($locales) === 0) {
                // No locales specified, skip eager loading
                return $query;
            }

            return $query->with([
                'translations' => fn (MorphMany $q) => $q->whereIn('locale', $locales),
            ]);
        });

    }

    /**
     * Search for models by translated field value with locale fallback support.
     */
    public function addSearchByTranslation(
        Builder $builder,
    ): void {

        $builder->macro('searchByTranslation', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null,
            string $operator = 'like'
        ) {
            $localePriority = resolve(LocaleResolver::class)->normalizeLocales($locales);

            $searchValue = match ($operator) {
                'like' => "%{$search}%",
                'starts_with' => "{$search}%",
                'ends_with' => "%{$search}",
                'exact' => $search,
                default => "%{$search}%"
            };

            $comparison = $operator === 'exact' ? '=' : 'like';

            if (is_array($key)) {
                $query->whereHas('translations', function ($q) use ($key, $searchValue, $localePriority, $comparison) {
                    $q->whereIn('locale', $localePriority)
                        ->whereIn('key', $key)
                        ->where('text', $comparison, $searchValue);
                });
            } else {
                $query->whereHas('translations', function ($q) use ($key, $searchValue, $localePriority, $comparison) {
                    $q->where('key', $key)
                        ->whereIn('locale', $localePriority)
                        ->where('text', $comparison, $searchValue);
                });
            }

            return $query;
        });

        // Add additional macros for exact, starts_with, and ends_with searches
        $builder->macro('searchByTranslationExact', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null
        ) {
            return $query->searchByTranslation($key, $search, $locales, 'exact');
        });

        $builder->macro('searchByTranslationStartsWith', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null
        ) {
            return $query->searchByTranslation($key, $search, $locales, 'starts_with');
        });

        $builder->macro('searchByTranslationEndsWith', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null
        ) {
            return $query->searchByTranslation($key, $search, $locales, 'ends_with');
        });
    }

    /**
     * Filter models that have a translation for the given key and locale(s).
     */
    public function addWhereHasTranslation(
        Builder $builder
    ): void {

        $builder->macro('whereHasTranslation', function (
            Builder $query,
            string $key,
            string|array|null $locales = null
        ) {

            $localePriority = resolve(LocaleResolver::class)->normalizeLocales($locales);

            $query->whereHas('translations', function ($q) use ($key, $localePriority) {
                $q->where('key', $key)
                    ->whereIn('locale', $localePriority);
            });

            return $query;
        });

    }

    /**
     * Filter models where translation matches a specific value.
     */
    public function addWhereTranslation(
        Builder $builder
    ): void {
        $builder->macro('whereTranslation', function (
            Builder $query,
            string $key,
            string $value,
            string|array|null $locales = null,
            string $operator = 'exact'
        ) {
            $query->searchByTranslation($key, $value, $locales, $operator);
        });
    }

    /**
     * Order by translated field values.
     */
    public function addOrderByTranslation(
        Builder $builder
    ): void {
        $builder->macro('orderByTranslation', function (
            Builder $query,
            string $key,
            string $direction = 'asc'
        ) {
            $locale = resolve(LocaleResolver::class)->getLocales()[0] ?? app()->getLocale();
            $model = $query->getModel();
            $translationsModel = config('translatable.model');

            $subQuery = $translationsModel::query()
                ->select('text')
                ->where('key', $key)
                ->where('locale', $locale)
                ->where('translatable_type', $model->getMorphClass())
                ->whereColumn('translatable_id', $model->getTable().'.'.$model->getKeyName())
                ->limit(1);

            return $query->orderBy($subQuery, $direction);
        });
    }
}
