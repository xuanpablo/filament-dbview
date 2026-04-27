<?php

namespace Xuanpablo\Dbview;

use Filament\Contracts\Plugin as PluginContract;
use Filament\Panel;
use Xuanpablo\Dbview\Pages\Dbview;

class DbviewPlugin implements PluginContract
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'dbview';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Dbview::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
