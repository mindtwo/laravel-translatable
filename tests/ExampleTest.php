<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use mindtwo\LaravelTranslatable\Models\Translatable;
use mindtwo\LaravelTranslatable\Traits\HasTranslateableFields;
use mindtwo\LaravelTranslatable\Traits\HasTranslations;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run the translatable table migration
    foreach ([
        include dirname(__DIR__).'/database/migrations/create_translatable_table.php',
        include __DIR__.'/migrations/create_test_models_table.php',
    ] as $migration) {
        $migration->up();
    }

    // Define a test model using the HasTranslations trait
    if (! class_exists('TestModel')) {
        class TestModel extends Model implements \mindtwo\LaravelTranslatable\Contracts\IsTranslatable
        {
            use HasTranslations;

            protected $guarded = [];
        }
    }

    if (! class_exists('TestModelWithTranslations')) {
        class TestModelWithTranslations extends Model implements \mindtwo\LaravelTranslatable\Contracts\IsTranslatable
        {
            use HasTranslateableFields;
            use HasTranslations;

            protected $guarded = [];

            protected $translatable = ['title'];

            protected function getTitleAttribute()
            {
                return 'not translated';
            }
        }
    }
});

test('models can have translations', function () {
    $model = TestModel::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    expect($model->translations)->toHaveCount(1);
    expect($model->translations->first()->text)->toBe('Test Title');
});

test('hasTranslation method works correctly', function () {
    $model = TestModel::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    expect($model->hasTranslation('title', 'en'))->toBeTrue();
    expect($model->hasTranslation('title', 'fr'))->toBeFalse();
});

test('getTranslation method retrieves the correct translation', function () {
    $model = TestModel::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    $translation = $model->getTranslation('title', 'en');

    expect($translation instanceof Translatable)->toBeTrue();
    expect($translation->text)->toBe('Test Title');
});

test('getTranslations returns the correct collection', function () {
    $model = TestModel::create();

    $model->translations()->createMany([
        ['key' => 'title', 'locale' => 'en', 'text' => 'Test Title English'],
        ['key' => 'title', 'locale' => 'de', 'text' => 'Test Title German'],
    ]);

    $translations = $model->getTranslations('title');

    expect($translations)->toHaveCount(2);
    expect($translations->pluck('text'))->toContain('Test Title English', 'Test Title German');
});

test('models can autoload translations', function () {
    Model::preventLazyLoading(true);

    $model = TestModel::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    $model = TestModel::first();

    expect($model->translations)->toHaveCount(1);
    expect($model->translations->first()->text)->toBe('Test Title');
});

test('dynamic accessor for model translations works correctly', function () {
    $model = TestModelWithTranslations::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    TestModelWithTranslations::first();

    expect($model->title)->toBe('Test Title');
});

test('dynamic mutator for model translations works correctly', function () {
    $model = TestModelWithTranslations::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    expect($model->title)->toBe('Test Title');

    $model->title = 'New Title';
    expect($model->title)->toBe('New Title');
});

test('dynamic accessor uses fallback if translation is missing', function () {
    $model = TestModelWithTranslations::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    expect($model->title)->toBe('Test Title');

    app()->setLocale('de');
    expect($model->title)->toBe('Test Title');
});

test('can disable translations for a model', function () {
    $model = TestModelWithTranslations::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    expect($model->title)->toBe('Test Title');

    $notTranslatedAttrValue = TestModelWithTranslations::withoutTranslations(fn () => $model->title);

    // app()->setLocale('de');
    expect($notTranslatedAttrValue)->toBe('not translated')
        ->and($model->title)->toBe('Test Title');
});

test('scope order by translation', function () {
    $orderedTitles = collect([
        'foo',
        'bar',
        'baz',
        'qux',
        'quux',
    ])->sort()->values()->toArray();

    $shuffledTitles = collect($orderedTitles)->shuffle()->values()->toArray();

    $models = collect($shuffledTitles)->map(function ($title) {
        $model = TestModelWithTranslations::create();

        $model->translations()->create([
            'key' => 'title',
            'locale' => 'en',
            'text' => $title,
        ]);

        return $model;
    });

    expect(TestModelWithTranslations::count())->toBe(5);

    expect($models->pluck('title')->toArray())->toBe($shuffledTitles);
    $orderedModels = TestModelWithTranslations::orderByTranslation('title')->get();

    $modelTitles = $orderedModels->pluck('title')->toArray();

    expect($modelTitles)->not->toBe($shuffledTitles)
        ->and($models->pluck('title')->toArray())->toBe($shuffledTitles);

    expect($modelTitles)->toBe($orderedTitles);
});

test('scope search by translation', function () {
    $models = collect([
        'foo',
        'bar',
        'baz',
        'qux',
        'quux',
    ])->map(function ($title) {
        $model = TestModelWithTranslations::create();

        $model->translations()->create([
            'key' => 'title',
            'locale' => 'en',
            'text' => $title,
        ]);

        return $model;
    });

    expect(TestModelWithTranslations::count())->toBe(5);

    $searchResults = TestModelWithTranslations::searchByTranslation('title', 'ba')->get();

    expect($searchResults->count())->toBe(2);
    expect($searchResults->pluck('title')->toArray())->toContain('bar', 'baz');
});

test('expect translations to resolved via loaded relation', function () {
    $model = TestModelWithTranslations::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    // Check if the relation is not loaded but the translation is still resolved
    expect($model->relationLoaded('translations'))->toBeFalse();
    expect($model->getTranslation('title', 'en')->text)->toBe('Test Title');

    expect($model->relationLoaded('translations'))->toBeFalse();

    // Load the relation and check if the translation is still resolved even if the relation is loaded
    $model->load('translations');
    expect($model->relationLoaded('translations'))->toBeTrue();
    Translatable::query()->delete();

    expect(Translatable::count())->toBe(0);
    expect($model->getTranslation('title', 'en')->text)->toBe('Test Title');
});
