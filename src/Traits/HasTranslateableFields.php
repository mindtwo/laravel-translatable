<?php

namespace mindtwo\LaravelTranslatable\Traits;

trait HasTranslateableFields
{
    /**
     * Get translated attribute.
     *
     * @param  mixed  $key
     * @return string|null
     */
    public function getTranslatedAttribute($key)
    {
        if (! $this->hasTranslation($key)) {
            return null;
        }

        return $this->getTranslation($key)->text;
    }

    /**
     * Get translation.
     *
     * @param  string  $key
     * @param  string|null  $locale
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function setTranslation($key, $value)
    {
        $locale = app()->getLocale();

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
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        // If the attribute is marked as translatable, set the translated value
        if (property_exists($this, 'translatable') && in_array($key, $this->translatable)) {
            return $this->setTranslation($key, $value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get attribute.
     *
     * @param  mixed  $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        // If the attribute is marked as translatable, return the translated value
        if (property_exists($this, 'translatable') && in_array($name, $this->translatable)) {
            return $this->getTranslatedAttribute($name);
        }

        return parent::getAttribute($name);
    }
}
