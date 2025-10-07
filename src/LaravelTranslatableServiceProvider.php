<?php

namespace mindtwo\LaravelTranslatable;

use Illuminate\Foundation\Application;
use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;
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
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(LocaleResolver::class, function (Application $app) {
            return $app->make(config('translatable.resolver'));
        });
    }
}
