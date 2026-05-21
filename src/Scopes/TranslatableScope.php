<?php

namespace mindtwo\LaravelTranslatable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;
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
class TranslatableScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['WithTranslations', 'SearchByTranslation', 'WhereHasTranslation', 'WhereTranslation', 'OrderByTranslation'];

    /**
     * {@inheritDoc}
     */
    public function apply(Builder $builder, $model): void {}

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     * @return void
     */
    public function extend($builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Add the with-translations extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    public function addWithTranslations(Builder $builder): void
    {
        $builder->macro('withTranslations', function (Builder $query, ?array $locales = null) {
            $locales = $locales ?? resolve(LocaleResolver::class)->getLocales();

            if (count($locales) === 0) {
                return $query;
            }

            return $query->with([
                'translations' => fn ($q) => $q->whereIn('locale', $locales),
            ])->afterQuery(fn ($results) => $results->each(fn ($result) => $result->addLoadedLocales($locales)));
        });
    }

    /**
     * Add the search-by-translation extensions to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    public function addSearchByTranslation(Builder $builder): void
    {
        $builder->macro('searchByTranslation', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null,
            string $operator = 'like',
            string $boolean = 'and',
        ) {
            return TranslatableScope::applySearchByTranslation($query, $key, $search, $locales, $operator, $boolean);
        });

        $builder->macro('searchByTranslationExact', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null,
            string $boolean = 'and',
        ) {
            return TranslatableScope::applySearchByTranslation($query, $key, $search, $locales, 'exact', $boolean);
        });

        $builder->macro('searchByTranslationStartsWith', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null,
            string $boolean = 'and',
        ) {
            return TranslatableScope::applySearchByTranslation($query, $key, $search, $locales, 'starts_with', $boolean);
        });

        $builder->macro('searchByTranslationEndsWith', function (
            Builder $query,
            string|array $key,
            string $search,
            string|array|null $locales = null,
            string $boolean = 'and',
        ) {
            return TranslatableScope::applySearchByTranslation($query, $key, $search, $locales, 'ends_with', $boolean);
        });
    }

    /**
     * Add the where-has-translation extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    public function addWhereHasTranslation(Builder $builder): void
    {
        $builder->macro('whereHasTranslation', function (
            Builder $query,
            string $key,
            string|array|null $locales = null,
            string $boolean = 'and'
        ) {
            $localePriority = resolve(LocaleResolver::class)->normalizeLocales($locales);

            return $query->has(
                relation: 'translations',
                boolean: $boolean,
                callback: function ($q) use ($key, $localePriority) {
                    $q->where('key', $key)
                        ->whereIn('locale', $localePriority);
                }
            );
        });
    }

    /**
     * Add the where-translation extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    public function addWhereTranslation(Builder $builder): void
    {
        $builder->macro('whereTranslation', function (
            Builder $query,
            string $key,
            string $value,
            string|array|null $locales = null,
            string $operator = 'exact'
        ) {
            return TranslatableScope::applySearchByTranslation($query, $key, $value, $locales, $operator, 'and');
        });
    }

    /**
     * Add the order-by-translation extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $builder
     */
    public function addOrderByTranslation(Builder $builder): void
    {
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

    /**
     * Apply a translation search constraint to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  string|array<int, string>  $key
     * @param  string|array<int, string>|null  $locales
     */
    public static function applySearchByTranslation(
        Builder $query,
        string|array $key,
        string $search,
        string|array|null $locales,
        string $operator,
        string $boolean,
    ): Builder {
        $localePriority = resolve(LocaleResolver::class)->normalizeLocales($locales);

        $searchValue = match ($operator) {
            'like' => "%{$search}%",
            'starts_with' => "{$search}%",
            'ends_with' => "%{$search}",
            'exact' => $search,
            default => "%{$search}%",
        };

        if (! in_array(strtolower($boolean), ['and', 'or'])) {
            $boolean = 'and';
        }

        $boolean = strtolower($boolean);
        $comparison = $operator === 'exact' ? '=' : 'like';

        return $query->has(
            relation: 'translations',
            boolean: $boolean,
            callback: function ($q) use ($key, $searchValue, $localePriority, $comparison) {
                $q->whereIn('locale', $localePriority)
                    ->when(
                        is_array($key),
                        fn ($q) => $q->whereIn('key', $key),
                        fn ($q) => $q->where('key', $key)
                    )
                    ->where('text', $comparison, $searchValue);
            }
        );
    }
}
