<?php

namespace Xuanpablo\Dbview\Providers;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DatabaseViewerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'database-viewer';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews();
    }
}
