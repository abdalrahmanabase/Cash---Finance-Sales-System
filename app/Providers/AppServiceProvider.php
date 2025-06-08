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
            FilamentAsset::register([
                // Stylesheet in public/css/style.css
                Css::make('custom-style', asset('css/style.css')),
                // Your “auto‐label” script in public/js/table-labels.js
                Js::make('table-labels',    asset('js/table-labels.js')),
            ]);
        });
    }
}
