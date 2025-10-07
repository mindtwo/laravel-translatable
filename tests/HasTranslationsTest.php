<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;
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
        class TestModel extends Model
        {
            use HasTranslations;

            protected $guarded = [];

            // Which columns should be overridden from the translatables table.
            // Include title and description for testing
            public function translatedKeys(): array
            {
                return ['title', 'description'];
            }
        }
    }

    Model::preventLazyLoading(true);
    // Set the default locale to English for tests
    app()->setLocale('en');
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

    expect($translation)->toBe('Test Title');
});

test('getTranslations returns the correct collection', function () {
    $model = TestModel::create();

    $model->translations()->createMany([
        ['key' => 'title', 'locale' => 'en', 'text' => 'Test Title English'],
        ['key' => 'title', 'locale' => 'de', 'text' => 'Test Title German'],
        ['key' => 'header', 'locale' => 'en', 'text' => 'Test Header English'],
    ]);

    $translations = $model->getAllTranslations('title');

    expect($translations)->toHaveCount(2)
        ->and(array_values($translations))->toContain('Test Title English', 'Test Title German');
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

test('dynamic accessor uses fallback if translation is missing', function () {
    $model = TestModel::create();

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

    // Set locales to empty array
    resolve(LocaleResolver::class)->setLocales([]);

    $notTranslated = TestModel::first();
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
    $orderedModels = TestModel::query()->orderByTranslation('title')->get();
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

test('fallback locale chain works correctly', function () {
    // Create a model with translations in different locales
    $model = TestModel::create();

    // Add translation only in fallback locale (English)
    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'English Title',
    ]);

    // Test that when current locale is different, it falls back to English
    app()->setLocale('de'); // German

    $retrieved = TestModel::first();
    expect($retrieved->title)->toBe('English Title');

    // Now add a German translation
    $model->translations()->create([
        'key' => 'title',
        'locale' => 'de',
        'text' => 'German Title',
    ]);

    // Refresh the model to get new translation
    $retrieved = TestModel::first();
    expect($retrieved->title)->toBe('German Title'); // Should prefer current locale

    // Test ordering with fallback locales
    $model2 = TestModel::create();
    $model2->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Another English Title',
    ]);

    $ordered = TestModel::orderBy('title')->get();
    expect($ordered->count())->toBe(2);

    // Should order by the appropriate locale (German first, then fallback to English)
    $titles = $ordered->pluck('title')->toArray();
    expect($titles)->toContain('German Title', 'Another English Title');

    // Reset locale
    app()->setLocale('en');
});

test('complex fallback locale chain fr -> de -> en works correctly', function () {
    // Set a complex fallback chain: fr -> de -> en
    resolve(LocaleResolver::class)->setLocales(['fr', 'de', 'en']);

    // Test 1: Only English translation exists, should fallback through chain
    $model1 = TestModel::create();
    $model1->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'English Only',
    ]);

    app()->setLocale('fr'); // French (current locale)

    $retrieved = TestModel::first();
    expect($retrieved->title)->toBe('English Only'); // Should fallback fr -> de -> en

    // Test 2: Add German translation, should prefer it over English
    $model1->translations()->create([
        'key' => 'title',
        'locale' => 'de',
        'text' => 'German Translation',
    ]);

    $retrieved = TestModel::first();
    expect($retrieved->title)->toBe('German Translation'); // Should fallback fr -> de (found!)

    // Test 3: Add French translation, should use it (highest priority)
    $model1->translations()->create([
        'key' => 'title',
        'locale' => 'fr',
        'text' => 'French Translation',
    ]);

    $retrieved = TestModel::first();
    expect($retrieved->title)->toBe('French Translation'); // Should use fr (current locale)

    // Test 4: Test ordering with complex fallback chain
    $model2 = TestModel::create();
    $model2->translations()->createMany([
        ['key' => 'title', 'locale' => 'de', 'text' => 'Allemand'], // German only
    ]);

    $model3 = TestModel::create();
    $model3->translations()->createMany([
        ['key' => 'title', 'locale' => 'en', 'text' => 'Another English'], // English only
    ]);

    // Test 4: No translations at all, should use base table value
    $model4 = TestModel::create(['title' => 'Base Table Title']);

    $retrieved = TestModel::find($model4->id);
    expect($retrieved->title)->toBe('Base Table Title'); // Should use base table value

    // Reset locale
    app()->setLocale('en');
});

test('getUntranslated method returns base table values', function () {
    // Create a model with base table values
    $model = TestModel::create([
        'title' => 'Original Title',
        'name' => 'Original Name',
    ]);

    // Add translations that override the base values
    $model->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Translated Title',
    ]);

    // Retrieve the model (should get translated values)
    $retrieved = TestModel::first();

    // The normal accessor should return translated value
    expect($retrieved->title)->toBe('Translated Title');

    // getUntranslated should return the base table value
    expect($retrieved->getUntranslated('title'))->toBe('Original Title');

    // For non-translated fields, getUntranslated should return null (no backup column)
    expect($retrieved->getUntranslated('name'))->toBe('Original Name');

    // Test with a key that has no translation - should still return base value
    $model->update(['title' => 'Updated Original']);
    $retrieved = TestModel::first();

    // Should still get translated value
    expect($retrieved->title)->toBe('Translated Title');
    // But getUntranslated should get the updated base value
    expect($retrieved->getUntranslated('title'))->toBe('Updated Original');

    // Test with null base value
    $model2 = TestModel::create(['title' => null]);
    $model2->translations()->create([
        'key' => 'title',
        'locale' => 'en',
        'text' => 'Only Translation',
    ]);

    $retrieved2 = TestModel::find($model2->id);
    expect($retrieved2->title)->toBe('Only Translation');
    expect($retrieved2->getUntranslated('title'))->toBeNull();
});

test('improved searchByTranslation with fallback locales and operators', function () {
    // Create test data with different locales
    $model1 = TestModel::create();
    $model1->translations()->createMany([
        ['key' => 'title', 'locale' => 'en', 'text' => 'English Product'],
        ['key' => 'title', 'locale' => 'de', 'text' => 'Deutsches Produkt'],
    ]);

    $model2 = TestModel::create();
    $model2->translations()->createMany([
        ['key' => 'title', 'locale' => 'en', 'text' => 'Another English Item'],
        ['key' => 'description', 'locale' => 'en', 'text' => 'Product description'],
    ]);

    $model3 = TestModel::create();
    $model3->translations()->create([
        'key' => 'title', 'locale' => 'fr', 'text' => 'Produit Français',
    ]);

    // Test 1: Basic search with fallback locale support
    app()->setLocale('es'); // Spanish - will fallback to English
    $results = TestModel::searchByTranslation('title', 'English')->get();
    expect($results->count())->toBe(2); // Should find both English titles

    // Test 2: Exact search
    $results = TestModel::searchByTranslationExact('title', 'English Product')->get();
    expect($results->count())->toBe(1);
    expect($results->first()->id)->toBe($model1->id);

    // Test 3: Starts with search
    $results = TestModel::searchByTranslationStartsWith('title', 'English')->get();
    expect($results->count())->toBe(1);
    expect($results->first()->id)->toBe($model1->id);

    // Test 4: Ends with search
    $results = TestModel::searchByTranslationEndsWith('title', 'Product')->get();
    expect($results->count())->toBe(1);
    expect($results->first()->id)->toBe($model1->id);

    // Test 5: Multiple keys search
    $results = TestModel::searchByTranslation(['title', 'description'], 'Product')->get();
    expect($results->count())->toBe(2); // Should find title and description matches

    // Test 6: Case insensitive search (default behavior)
    $results = TestModel::searchByTranslation('title', 'english')->get();
    expect($results->count())->toBe(2); // Should match regardless of case

    // Test 8: Search in specific locale
    app()->setLocale('en');
    $results = TestModel::searchByTranslation('title', 'Deutsch', 'de')->get();
    expect($results->count())->toBe(1);
    expect($results->first()->id)->toBe($model1->id);

    // Reset locale
    app()->setLocale('en');
});

test('searchByTranslation performance with complex fallback chains', function () {
    // Set a complex fallback chain: es -> de -> en -> fr
    resolve(LocaleResolver::class)->setLocales(['es', 'de', 'en', 'fr']);

    // Create test data
    $model = TestModel::create();
    $model->translations()->createMany([
        ['key' => 'title', 'locale' => 'fr', 'text' => 'Titre Français'],
        ['key' => 'title', 'locale' => 'en', 'text' => 'English Title'],
    ]);

    // Set locale to Spanish (not in fallback chain)
    app()->setLocale('es');

    // Should search through fallback chain: es -> de -> en -> fr
    $results = TestModel::searchByTranslation('title', 'English')->get();
    expect($results->count())->toBe(1); // Should find English title through fallback

    $results = TestModel::searchByTranslation('title', 'Français')->get();
    expect($results->count())->toBe(1); // Should find French title through fallback

    // Reset locale
    app()->setLocale('en');
});
