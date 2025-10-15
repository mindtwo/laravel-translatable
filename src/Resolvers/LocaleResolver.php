<?php

namespace mindtwo\LaravelTranslatable\Resolvers;

class LocaleResolver
{
    protected ?array $locales = null;

    protected ?string $defaultLocale = null;

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

    public function getDefaultLocale(): string
    {
        if ($this->defaultLocale !== null) {
            return $this->defaultLocale;
        }

        return app()->getFallbackLocale();
    }

    public function setDefaultLocale(string $defaultLocale): static
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    public function setLocales(array $locales): self
    {
        $this->locales = $locales;

        return $this;
    }

    /**
     * Normalize locale parameter to array format.
     */
    public function normalizeLocales(string|array|null $locales): array
    {
        if (is_null($locales)) {
            return $this->getLocales();
        }

        return is_array($locales) ? $locales : [$locales];
    }
}
