<?php

namespace mindtwo\LaravelTranslatable\Traits;

use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;

trait HasTranslateableFields
{
    /**
     * Disable translations.
     *
     * @var bool
     */
    protected static $disabledTranslations = false;

    /**
     * Get translated attribute.
     *
     * @param  mixed  $key
     * @return string|null
     */
    public function getTranslatedAttribute($key)
    {
        // Try to get the translation for the current locale
        if ($this->hasTranslation($key)) {
            return $this->getTranslation($key)->text;
        }

        if ($this->hasTranslation($key, $this->getFallbackTranslationLocale())) {
            return $this->getTranslation($key, $this->getFallbackTranslationLocale())->text;
        }

        return null;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        if (static::$disabledTranslations === true || ! $this instanceof \mindtwo\LaravelTranslatable\Contracts\IsTranslatable) {
            return parent::setAttribute($key, $value);
        }

        // If the attribute is marked as translatable, set the translated value
        if (property_exists($this, 'translatable') && in_array($key, $this->translatable)) {
            return $this->setTranslation($key, $value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return self|false - returns false if the attribute is not updated
     */
    public function setFallbackAttribute($key, $value, bool $force = false): self|false
    {
        if (! $this instanceof \mindtwo\LaravelTranslatable\Contracts\IsTranslatable) {
            return false;
        }

        // If the attribute is marked as translatable, set the translated value
        $locale = $this->getFallbackTranslationLocale();

        // If the translation already exists and we don't want to force the update, return false
        if ($this->hasTranslation($key, $locale) && ! $force) {
            return false;
        }

        // Set the translation if the attribute is marked as translatable
        if (property_exists($this, 'translatable') && in_array($key, $this->translatable)) {
            return $this->setTranslation($key, $value, $locale);
        }

        return false;
    }

    /**
     * Get attribute.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        // if we disable the translation, return the original value
        if (static::$disabledTranslations === true) {
            return parent::getAttribute($name);
        }

        // If the attribute is marked as translatable, return the translated value
        if (property_exists($this, 'translatable') && in_array($name, $this->translatable)) {
            return $this->getTranslatedAttribute($name);
        }

        return parent::getAttribute($name);
    }

    /**
     * Get the fallback translation locale.
     *
     * @param  string  $key
     * @param  string|null  $locale
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getFallbackTranslationLocale()
    {
        // Get the fallback locale from the model via method
        if (method_exists($this, 'getTranslatableFallback')) {
            return $this->getTranslatableFallback();
        }

        // Get the fallback locale from the model via method
        if (property_exists($this, 'translatableFallback')) {
            return $this->translatableFallback;
        }

        return app(LocaleResolver::class)->resolveFallback();
    }

    /**
     * Disable translations.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public static function withoutTranslations($callback)
    {
        static::$disabledTranslations = true;

        $result = $callback();

        static::$disabledTranslations = false;

        return $result;
    }
}
