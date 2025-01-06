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
}

