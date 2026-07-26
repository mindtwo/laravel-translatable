<?php

namespace mindtwo\LaravelTranslatable;

use mindtwo\LaravelTranslatable\Resolvers\LocaleResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelTranslatableServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-translatable')
            ->hasConfigFile()
            ->hasMigration('create_translatable_table');
    }

    /**
     * Register the package's services.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(LocaleResolver::class, function () {
            return new (config('translatable.resolver'));
        });
    }
}
