<?php

namespace Xuanpablo\Dbview\Providers;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DbviewServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dbview';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews();
    }
}
