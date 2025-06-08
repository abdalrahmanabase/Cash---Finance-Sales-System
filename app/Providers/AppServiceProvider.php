<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
{
    Filament::serving(function (): void {
        // your global CSS/JS...
        FilamentAsset::register([
            Css::make('custom-style', asset('css/style.css')),
            Js::make('table-labels',    asset('js/table-labels.js')),
        ]);

        // inject our language‐switcher dropdown at the end of the navbar
        Filament::registerRenderHook(
            'navbar.end',
            fn (): string => view('filament.lang-switch')->render(),
        );
    });
}
}
