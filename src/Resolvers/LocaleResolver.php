<?php

namespace mindtwo\LaravelTranslatable\Resolvers;

class LocaleResolver
{
    public function resolve(?string $locale = null): string
    {
        if (! is_null($locale)) {
            return $locale;
        }

        return app()->getLocale();
    }

    public function resolveFallback(?string $locale = null): string|array
    {
        if (! is_null($locale)) {
            return $locale;
        }

        // Get the Laravel fallback locale
        $fallbackLocale = app()->getFallbackLocale();

        // Check if there's a configured locale chain in the app config
        $localeChain = config('translatable.locale_chain', []);

        if (! empty($localeChain) && is_array($localeChain)) {
            // If a locale chain is configured, use it
            return $localeChain;
        }

        // Default behavior: return just the Laravel fallback locale
        return $fallbackLocale;
    }
}
