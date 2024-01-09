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
        class TestModel extends Model
        {
            use HasTranslations;

            protected $guarded = [];
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
