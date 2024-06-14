<?php

namespace mindtwo\LaravelTranslatable\Nova\Fields;

use Laravel\Nova\Fields\Field;

class TranslatableField extends Field
{
    public $component = 'translatable-field';

    public function __construct($name, ?string $key = null)
    {
        parent::__construct($name, 'translations', function ($value, $resource, $attribute) {
            // Create a value map locale => translation
            $value = collect($value)->mapWithKeys(function ($item) {
                return [$item['locale'] => $item['text']];
            });

            return $value;
        });

        $this->key($key);
    }

    /**
     * Fill the model's attribute with data.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Laravel\Nova\Support\Fluent  $model
     * @return void
     */
    public function fillModelWithData(mixed $model, mixed $value, string $attribute)
    {
        if (! $model instanceof \mindtwo\LaravelTranslatable\Contracts\IsTranslatable) {
            throw new \InvalidArgumentException('The model must implement the IsTranslatable interface.');
        }

        // Create or update translations from the given value
        foreach ($value as $locale => $translation) {
            $model->translations()->updateOrCreate(
                [
                    'locale' => $locale,
                    'key' => $this->meta['key'],
                ],
                ['text' => $translation]
            );
        }
    }

    /**
     * Resolve the given attribute from the given resource.
     *
     * @param  mixed  $resource
     * @param  string  $attribute
     * @return mixed
     */
    protected function resolveAttribute($resource, $attribute)
    {
        // Get translations for the given key
        return $resource->getTranslations($this->meta['key']);
    }

    /**
     * Set the locales for the field.
     *
     * @return $this
     */
    public function locales(array $locales)
    {
        return $this->withMeta(['locales' => $locales]);
    }

    /**
     * Set the key for the field.
     *
     * @param  string  $key
     * @return $this
     */
    public function key($key)
    {
        return $this->withMeta(['key' => $key]);
    }
}
