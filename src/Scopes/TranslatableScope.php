<?php

namespace mindtwo\LaravelTranslatable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

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
        try {
            // Check if the model has the translatedKeys method defined
            $keys = $model::translatedKeys();
        } catch (\Throwable $e) {
            Log::error('Failed to get translated keys for model: '.static::class, [
                'exception' => $e,
            ]);

            // If the keys method is not defined, we assume no translations are needed.
            return;
        }

        // Get the model and its table
        $model = $builder->getModel();
        $table = $model->getTable();
        $locale = app()->getLocale();

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

        // Join only once; we'll filter by key in the ON clause to avoid row duplication.
        // If you add more fields, duplicate the join under a different alias per field.
        foreach ($keys as $key) {
            $alias = 't_i18n_'.$key;

            $builder->leftJoin("{$translatableTable} as {$alias}", function ($join) use ($alias, $table, $model, $locale, $key) {
                $join->on("{$alias}.translatable_id", '=', "{$table}.{$model->getKeyName()}")
                    ->where("{$alias}.translatable_type", '=', $model->getMorphClass())
                    ->where("{$alias}.key", '=', $key)
                    ->where("{$alias}.locale", '=', $locale);
            });

            // Add the COALESCE column and backup column using addSelect
            // The COALESCE column with same name will override the base table column for result access
            $builder->addSelect([
                DB::raw("COALESCE({$alias}.text, {$table}.{$key}) as {$key}"),
                DB::raw("{$table}.{$key} as _base_{$key}"),
            ]);

            // For ordering to work correctly, we need to ensure ORDER BY uses the COALESCE result
            // This is achieved by the COALESCE column appearing later in SELECT and having same alias
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
        $builder->macro('searchByTranslation', function (Builder $builder, string|array $key, string $search, ?string $locale = null) {
            if (is_array($key)) {
                return $builder->whereHas('translations', function ($query) use ($key, $search, $locale) {
                    $query->where(function ($query) use ($key, $search, $locale) {
                        foreach ($key as $k) {
                            $query->orWhere(function ($query) use ($k, $search, $locale) {
                                $query->where('key', $k)
                                    ->where('locale', app(LocaleResolver::class)->resolve($locale))
                                    ->where(fn ($q) => $q->where('text', 'like', "%{$search}%")->orWhere('text', 'like', "%{$search}"));
                            });
                        }
                    });
                });
            }

            return $builder->whereHas('translations', function ($query) use ($key, $search, $locale) {
                $query->where('key', $key)
                    ->where('locale', app(LocaleResolver::class)->resolve($locale))
                    ->where(fn ($q) => $q->where('text', 'like', "%{$search}%")->orWhere('text', 'like', "%{$search}"));
            });
        });
    }
}
