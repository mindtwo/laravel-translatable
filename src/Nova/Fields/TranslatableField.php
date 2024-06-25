<?php

namespace mindtwo\LaravelTranslatable\Nova\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\SupportsDependentFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use mindtwo\LaravelTranslatable\Contracts\IsTranslatable;

class TranslatableField extends Field
{
    use SupportsDependentFields;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'translatable-field';

    public $inputType = 'text';

    public ?Resource $repeateableResource = null;

    protected array $localeRules = [];

    public function __construct($name, ?string $key = null)
    {
        parent::__construct($name, $key, function ($value, $resource, $attribute) {

            // If the value is a collection, convert it to an array
            if ($value instanceof Collection) {
                $value = $value->toArray();
            }

            if (empty($value)) {
                return null;
            }

            // Create a value map locale => translation
            $value = collect($value)->mapWithKeys(function ($item) {
                return [$item['locale'] => $item['text']];
            })->toArray();

            return $value;

        });

        $this->key($key);
    }

    /**
     * Set the validation rules for the field.
     *
     * @param  (callable(\Laravel\Nova\Http\Requests\NovaRequest):(array|\Stringable|string|callable))|array|\Stringable|string  ...$rules
     * @return @return array<array-key, array<int, mixed>>
     */
    public function getRules(NovaRequest $request)
    {
        return $this->getLocaleRules($request, true);
    }

    protected function getLocaleRules(NovaRequest $request, bool $withLocales = false): array
    {
        if (! $withLocales) {
            $rules = is_callable($this->rules) ? call_user_func($this->rules, $request) : $this->rules;

            return [
                $this->attribute => $rules,
            ];
        }

        // Validate the field for each locale
        return [
            $this->attribute => function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                $value = json_decode($value, true);
                if (empty($value) || ! is_array($value)) {
                    $fail("The {$attribute} is invalid.");

                    return;
                }

                // Create a rule map for each locale
                $localeRuleMap = array_reduce($this->meta['locales'], function ($carry, $locale) use ($request) {
                    $rules = $this->getRulesForLocale($locale, $request);

                    $carry[$locale] = $rules;

                    return $carry;
                }, []);

                // Use the rule map to validate the value
                $validator = Validator::make($value, $localeRuleMap);

                if ($validator->fails()) {
                    $fail(
                        collect($validator->messages())
                            ->map(function ($message, $locale) {
                                $localeKey = str_replace('_', ' ', Str::snake($locale));

                                $attributeName = "{$this->name} ({$locale})";

                                return Str::replace($localeKey, $attributeName, $message[0]);
                            })
                    );
                }
            },
        ];
    }

    /**
     * Fill the model's attribute with data.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Laravel\Nova\Support\Fluent  $model
     * @return void
     */
    public function fillModelWithData(mixed $model, mixed $value, string $attribute)
    {
        // skip if the model is a Fluent instance
        if ($model instanceof \Laravel\Nova\Support\Fluent) {
            $attributes = $model->getAttributes();

            $findModel = $this->model::find($attributes['id']);

            if (! $findModel) {
                // create a uuid to identify the model later
                $uuid = $model->uuid ?? Str::uuid()->toString();

                $model->uuid = $uuid;
                // wait for a model to be created
                $this->model::created(function ($created) use ($uuid, $value, $attribute) {
                    // skip if the model is not the one we are looking for
                    if (! $created->uuid || $created->uuid !== $uuid) {
                        return;
                    }

                    $this->upsertModelTranslation($created, $value, $attribute);
                });

                return;
            }

            $this->upsertModelTranslation($findModel, $value, $attribute);

            return;
        }

        // If the model does not implement the IsTranslatable interface, throw an exception
        if (! $model instanceof \mindtwo\LaravelTranslatable\Contracts\IsTranslatable) {
            throw new \InvalidArgumentException('The model must implement the IsTranslatable interface.');
        }

        $this->upsertModelTranslation($model, $value, $attribute);
    }

    /**
     * Create or update translations for the given model.
     *
     * @param  \mindtwo\LaravelTranslatable\Contracts\IsTranslatable & Model  $model
     */
    protected function upsertModelTranslation(IsTranslatable $model, mixed $value, string $attribute): void
    {
        // If the value is a string, try to decode it
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        // Skip if the value is not an array
        if (! is_array($value)) {
            return;
        }

        if (! $model->exists) {
            // create a uuid to identify the model later
            $uuid = $model->uuid ?? Str::uuid()->toString();

            $model->uuid = $uuid;
            // wait for a model to be created
            $model::created(function ($model) use ($value, $attribute, $uuid) {
                // skip if the model is not the one we are looking for
                if (! $model->uuid || $model->uuid !== $uuid) {
                    return;
                }

                $this->upsertModelTranslation($model, $value, $attribute);
            });

            return;
        }

        // Create or update translations from the given value
        foreach ($value as $locale => $translation) {
            if (empty($translation)) {
                $model->translations()->where([
                    'locale' => $locale,
                    'key' => $attribute,
                ])->delete();

                continue;
            }

            $model->translations()->updateOrCreate(
                [
                    'locale' => $locale,
                    'key' => $attribute,
                ],
                ['text' => $translation]
            );
        }
    }

    /**
     * Determine if the field is required.
     *
     * @return bool
     */
    public function isRequired(NovaRequest $request)
    {
        // Get the base rules
        $rules = is_callable($this->rules) ? call_user_func($this->rules, $request) : $this->rules;

        if (! empty($this->attribute)) {
            if ($request->isResourceIndexRequest() || $request->isLensRequest() || $request->isActionRequest()) {
                return in_array('required', $this->getLocaleRules($request)[$this->attribute]);
            }

            if ($request->isCreateOrAttachRequest()) {
                return in_array('required', $this->getLocaleRules($request)[$this->attribute]);
            }

            if ($request->isUpdateOrUpdateAttachedRequest()) {
                return in_array('required', $this->getLocaleRules($request)[$this->attribute]);
            }
        }

        // If the field is not required, return false
        return in_array('required', $rules);
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
        if (is_array($resource)) {
            return $resource;
        }

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
     * Set the model for the field.
     *
     * @return $this
     */
    public function model(string $model)
    {
        $this->model = $model;

        return $this;
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

    /**
     * Set the input type for the field to markdown.
     *
     * @return $this
     */
    public function markdown()
    {
        return $this->inputType('markdown');
    }

    /**
     * Set the input type for the field to textarea.
     *
     * @return $this
     */
    public function textarea()
    {
        return $this->inputType('textarea');
    }

    /**
     * Set the input component for the field.
     *
     * @param  string  $component
     * @return $this
     */
    public function inputType($component)
    {
        $this->inputType = $component;

        return $this;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'inputType' => $this->inputType,
        ]);
    }

    /**
     * Resolve the element's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        $attribute = $attribute ?? $this->attribute;

        return with(app(NovaRequest::class), function ($request) use ($resource, $attribute) {
            if (! $request->isFormRequest() || ! in_array($request->method(), ['PUT', 'PATCH'])) {
                return parent::resolve($resource, $attribute);
            }

            $value = $request->input($this->attribute);

            if (! is_string($value)) {
                return $value;
            }

            return json_decode($value, true);
        });
    }

    /**
     * Set the rules for the field for a specific language.
     *
     * @return $this
     */
    public function rulesFor(string $locale, array|\Closure $rules)
    {
        $this->localeRules[$locale] = $rules;

        return $this;
    }

    /**
     * Get the rules for the field for a specific language.
     */
    protected function getRulesForLocale(string $locale, NovaRequest $request): array
    {
        $rules = $this->localeRules[$locale] ?? $this->rules ?? [];

        if (is_callable($rules)) {
            return call_user_func($rules, $request);
        }

        return $rules;
    }
}
