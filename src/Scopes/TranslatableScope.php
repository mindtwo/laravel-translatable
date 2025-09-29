<?php

namespace mindtwo\LaravelTranslatable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use mindtwo\LaravelTranslatable\Contracts\IsTranslatable;
use mindtwo\LaravelTranslatable\Models\Translatable;
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
    protected $extensions = ['WithoutTranslations', 'SearchByTranslation'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<TModel>  $builder
     * @param  TModel  $model
     * @return void
     */
    public function apply($builder, $model)
    {
        if (! $model instanceof IsTranslatable) {
            // If the model does not implement IsTranslatable, we skip the scope
            return;
        }

        $keys = $model->translatedKeys();
        if (empty($keys)) {
            // If there are no translatable keys, we skip the scope
            return;
        }

        // Get the model and its table
        $model = $builder->getModel();
        $table = $model->getTable();

        // Get the current locale
        $currentLocale = app()->getLocale();

        // Get fallback locale chain from the model
        $fallbackLocale = $model->getFallbackTranslationLocale();

        // Build locale priority array: current locale first, then fallbacks
        $localePriority = collect([$currentLocale]);
        if (is_array($fallbackLocale)) {
            $localePriority = $localePriority->merge($fallbackLocale);
        } elseif ($fallbackLocale !== $currentLocale) {
            $localePriority->push($fallbackLocale);
        }
        $localePriority = $localePriority->unique()->values()->toArray();

        $translatableModel = config('translatable.model', Translatable::class);
        $translatableTable = (new $translatableModel)->getTable();

        // Ensure base table columns are selected if no explicit select was made
        if (empty($builder->getQuery()->columns)) {
            // Instead of {table}.*, manually select non-translated columns to avoid conflicts
            $tableColumns = DB::getSchemaBuilder()->getColumnListing($table);
            $baseColumns = [];

            foreach ($tableColumns as $column) {
                if (! in_array($column, $keys)) {
                    $baseColumns[] = "{$table}.{$column}";
                }
            }

            $builder->select($baseColumns);
        }

        // Join only once per key, using a subquery to get the best translation based on locale priority
        foreach ($keys as $key) {
            // Build the locale priority subquery similar to buildLocalePriorityQuery
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

            // Create a subquery that gets the best translation for this key based on locale priority
            $subQuery = DB::table($translatableTable)
                ->select('text')
                ->whereColumn('translatable_id', "{$table}.{$model->getKeyName()}")
                ->where('translatable_type', $model->getMorphClass())
                ->where('key', $key)
                ->whereIn('locale', $localePriority)
                ->orderByRaw($caseSql, $params)
                ->orderBy('id', 'desc')
                ->limit(1);

            // Add the subquery result and backup column using addSelect
            // We need to merge the bindings manually since DB::raw doesn't support mergeBindings
            $builder->addSelect([
                DB::raw("COALESCE(({$subQuery->toSql()}), {$table}.{$key}) as {$key}"),
                DB::raw("{$table}.{$key} as _base_{$key}"),
            ]);

            // Add the subquery bindings to the main query
            $builder->addBinding($subQuery->getBindings(), 'select');
        }
    }

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
     * Add the withOutTranslations extension to the builder.
     */
    protected function addWithoutTranslations(Builder $builder)
    {
        $builder->macro('withoutTranslations', function (Builder $builder) {
            return $builder->withoutGlobalScope(TranslatableScope::class);
        });
    }

    /**
     * Add the searchByTranslation extension to the builder.
     */
    protected function addSearchByTranslation(Builder $builder)
    {
        $builder->macro('searchByTranslation', function (
            Builder $builder,
            string|array $key,
            string $search,
            ?string $locale = null,
            string $operator = 'like'
        ) {
            // Get the model to access fallback locale configuration
            $model = $builder->getModel();

            // Build locale priority list including fallbacks
            $currentLocale = $locale ? app(LocaleResolver::class)->resolve($locale) : app()->getLocale();
            $fallbackLocale = $model->getFallbackTranslationLocale();

            $localePriority = [$currentLocale];
            if (is_array($fallbackLocale)) {
                $localePriority = array_merge($localePriority, $fallbackLocale);
            } elseif ($fallbackLocale !== $currentLocale) {
                $localePriority[] = $fallbackLocale;
            }
            $localePriority = array_unique($localePriority);

            // Prepare search value based on operator
            $searchValue = match($operator) {
                'like' => "%{$search}%",
                'starts_with' => "{$search}%",
                'ends_with' => "%{$search}",
                'exact' => $search,
                default => "%{$search}%"
            };

            // Use standard LIKE operator (case sensitivity depends on database collation)
            $comparison = 'like';

            if (is_array($key)) {
                // Multiple keys search with locale fallback
                return $builder->whereHas('translations', function ($query) use ($key, $searchValue, $localePriority, $comparison) {
                    $query->whereIn('locale', $localePriority)
                        ->whereIn('key', $key)
                        ->where('text', $comparison, $searchValue);
                });
            }

            // Single key search with locale fallback
            return $builder->whereHas('translations', function ($query) use ($key, $searchValue, $localePriority, $comparison) {
                $query->where('key', $key)
                    ->whereIn('locale', $localePriority)
                    ->where('text', $comparison, $searchValue);
            });
        });

        // Add convenience macros for common search patterns
        $builder->macro('searchByTranslationExact', function (Builder $builder, string|array $key, string $search, ?string $locale = null) {
            return $builder->searchByTranslation($key, $search, $locale, 'exact');
        });

        $builder->macro('searchByTranslationStartsWith', function (Builder $builder, string|array $key, string $search, ?string $locale = null) {
            return $builder->searchByTranslation($key, $search, $locale, 'starts_with');
        });

        $builder->macro('searchByTranslationEndsWith', function (Builder $builder, string|array $key, string $search, ?string $locale = null) {
            return $builder->searchByTranslation($key, $search, $locale, 'ends_with');
        });
    }
}
