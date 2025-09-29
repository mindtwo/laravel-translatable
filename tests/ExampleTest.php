<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use mindtwo\LaravelTranslatable\Models\Translatable;
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

            // Which columns should be overridden from the translatables table.
            // Start with just 'title' as requested.
            protected static function translatedKeys(): array
            {
                return ['title'];
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
        ['key' => 'header', 'locale' => 'en', 'text' => 'Test Header English'],
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
    $model = TestModel::create();

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    $model = TestModel::first();

    expect($model->title)->toBe('Test Title');
});

// test('dynamic accessor uses fallback if translation is missing', function () {
//     $model = TestModel::create();

//     $model->translations()->create([
//         'key' => 'title',
//         'locale' => 'en',
//         'text' => 'Test Title',
//     ]);

//     expect($model->title)->toBe('Test Title');

//     app()->setLocale('de');
//     expect($model->title)->toBe('Test Title');
// });

test('can disable translations for a model', function () {
    $model = TestModel::create([
        'title' => 'not translated',
    ]);

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Test Title',
    ]);

    $model = TestModel::first();

    expect($model->title)->toBe('Test Title');

    $notTranslated = TestModel::withoutTranslations()->first();
    expect($notTranslated->title)->toBe('not translated')
        ->and($model->title)->toBe('Test Title');
});

test('order by a translatable value', function () {
    $orderedTitles = collect([
        'foo',
        'bar',
        'baz',
        'qux',
        'quux',
    ])->sort()->values()->toArray();

    $shuffledTitles = collect($orderedTitles)->shuffle()->values()->toArray();

    collect($shuffledTitles)->each(function ($title) {
        $model = TestModel::create();

        $model->translations()->create([
            'key' => 'title',
            'locale' => 'en',
            'text' => $title,
        ]);
    });

    expect(TestModel::count())->toBe(5);

    // Test that all models have their translated titles
    $allModels = TestModel::all();
    $allTitles = $allModels->pluck('title')->toArray();
    expect($allTitles)->toHaveCount(5);
    expect($allTitles)->each->not->toBeNull();

    // Test ordering by translated field
    $orderedModels = TestModel::query()->orderBy('title')->get();
    $modelTitles = $orderedModels->pluck('title')->toArray();

    expect($modelTitles)->toBe($orderedTitles);
});

test('non-translated fields are accessible', function () {
    $model = TestModel::create([
        'name' => 'Test Name',
        'priority' => 5,
    ]);

    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Translated Title',
    ]);

    $retrieved = TestModel::first();

    // Should have access to both translated and non-translated fields
    expect($retrieved->title)->toBe('Translated Title')
        ->and($retrieved->name)->toBe('Test Name')
        ->and($retrieved->priority)->toBe(5)
        ->and($retrieved->id)->not->toBeNull()
        ->and($retrieved->created_at)->not->toBeNull()
        ->and($retrieved->updated_at)->not->toBeNull();

    // Should be able to order by non-translated fields
    TestModel::create(['name' => 'Another Name', 'priority' => 1]);

    $ordered = TestModel::orderBy('priority')->get();
    expect($ordered->first()->priority)->toBe(1)
        ->and($ordered->last()->priority)->toBe(5);
});

test('search by translation', function () {
    collect([
        'foo',
        'bar',
        'baz',
        'qux',
        'quux',
    ])->each(function ($title) {
        $model = TestModel::create();

        $model->translations()->create([
            'key' => 'title',
            'locale' => 'en',
            'text' => $title,
        ]);
    });

    expect(TestModel::count())->toBe(5);

    $searchResults = TestModel::query()->searchByTranslation('title', 'ba')->get();

    expect($searchResults->count())->toBe(2);
    expect($searchResults->pluck('title')->toArray())->toContain('bar', 'baz');
});
