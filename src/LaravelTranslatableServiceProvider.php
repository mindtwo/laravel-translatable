<?php

namespace mindtwo\LaravelTranslatable;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTranslatableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-translatable')
            ->hasConfigFile()
            ->hasMigration('create_translatable_table');

        if (class_exists('Laravel\Nova\Nova')) {
            // @phpstan-ignore-next-line
            \Laravel\Nova\Nova::serving(function (\Laravel\Nova\Events\ServingNova $event) {
                \Laravel\Nova\Nova::script('translatable-field', __DIR__.'/../dist/js/field.js'); // @phpstan-ignore-line
                \Laravel\Nova\Nova::style('translatable-field', __DIR__.'/../dist/css/field.css'); // @phpstan-ignore-line
            });
        }
    }
}
