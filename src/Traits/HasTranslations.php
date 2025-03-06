<?php

namespace mindtwo\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\JoinClause;
use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

/**
 * Trait HasTranslations.
 *
 * @method static \Illuminate\Database\Eloquent\Builder orderByTranslation(string $key, ?string $locale = null, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder searchByTranslation(string|array $key, string $search, ?string $locale = null)
 */
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
        return $this->translations()
            ->where('key', $key)
            ->where('locale', app(LocaleResolver::class)->resolve($locale))
            ->exists();
    }

    /**
     * Set the translation for the given locale.
     */
    public function setTranslation(string $key, string $value, ?string $locale = null): self
    {
        $locale = app(LocaleResolver::class)->resolve($locale);

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
        // If translations are already loaded, we can use the collection to find the translation.
        if ($this->relationLoaded('translations') && $this->translations->isNotEmpty()) {
            /** @var ?Translatable $translatable */
            $translatable = $this->translations
                ->where('key', $key)
                ->where('locale', app(LocaleResolver::class)->resolve($locale))
                ->first();

            return $translatable;
        }

        /** @var ?Translatable $translatable */
        $translatable = $this->translations()
            ->where('key', $key)
            ->where('locale', app(LocaleResolver::class)->resolve($locale))
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
                $query->where('locale', app(LocaleResolver::class)->resolve($locale));
            })->get();

        return $translatableRecords;
    }

    /**
     * Add scope to order translations by locale value.
     */
    public function scopeOrderByTranslation($query, string $key, ?string $locale = null, $direction = 'asc'): void
    {
        $keyName = $this->getKeyName();

        $query
            // ->select($this->getTable().'.*')
            ->join('translatable', function (JoinClause $join) use ($keyName, $key, $locale) {
                $join->on($this->getTable().'.'.$keyName, '=', 'translatable.translatable_id')
                    ->where('translatable.translatable_type', self::class)
                    ->where('translatable.key', $key)
                    ->where('translatable.locale', app(LocaleResolver::class)->resolve($locale));
            })
            ->orderBy('translatable.text', $direction);
    }

    /**
     * Add scope to search for translations.
     */
    public function scopeSearchByTranslation($query, string|array $key, string $search, ?string $locale = null): void
    {
        // multiple keys
        if (is_array($key)) {
            $query->whereHas('translations', function ($query) use ($key, $search, $locale) {
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

            return;
        }

        $query->whereHas('translations', function ($query) use ($key, $search, $locale) {
            $query->where('key', $key)
                ->where('locale', app(LocaleResolver::class)->resolve($locale))
                ->where(fn ($q) => $q->where('text', 'like', "%{$search}%")->orWhere('text', 'like', "%{$search}"));
        });
    }
}
