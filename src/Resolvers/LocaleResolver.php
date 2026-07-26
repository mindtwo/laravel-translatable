<?php

namespace mindtwo\LaravelTranslatable\Resolvers;

class LocaleResolver
{
    /**
     * The resolved locale fallback chain, if overridden.
     *
     * @var array<int, string>|null
     */
    protected ?array $locales = null;

    /**
     * The default locale, if overridden.
     */
    protected ?string $defaultLocale = null;

    /**
     * Get the locale fallback chain.
     *
     * @return array<int, string>
     */
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

    /**
     * Get the default locale stored on the model itself.
     */
    public function getDefaultLocale(): string
    {
        if ($this->defaultLocale !== null) {
            return $this->defaultLocale;
        }

        return app()->getFallbackLocale();
    }

    /**
     * Set the default locale used for attributes stored on the model itself.
     *
     * @return $this
     */
    public function setDefaultLocale(string $defaultLocale): static
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    /**
     * Set the locale fallback chain.
     *
     * @param  array<int, string>  $locales
     * @return $this
     */
    public function setLocales(array $locales): self
    {
        $this->locales = $locales;

        return $this;
    }

    /**
     * Normalize a locale argument to an array of locales.
     *
     * @param  string|array<int, string>|null  $locales
     * @return array<int, string>
     */
    public function normalizeLocales(string|array|null $locales): array
    {
        if (is_null($locales)) {
            return $this->getLocales();
        }

        return is_array($locales) ? $locales : [$locales];
    }
}
