<?php

namespace mindtwo\LaravelTranslatable\Resolvers;

class LocaleResolver
{
    protected ?array $locales = null;

    public function getLocales(): array
    {
        if ($this->locales !== null) {
            return $this->locales;
        }

        return [
            app()->getLocale(),
            app()->getFallbackLocale(),
        ];
    }

    public function setLocales(array $locales): self
    {
        $this->locales = $locales;

        return $this;
    }
}
