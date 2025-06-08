<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\UserMenuItem;
use Filament\PluginServiceProvider;

use App\Filament\Widgets\FinancialStats;
use App\Filament\Widgets\SaleOptionsButton;
use App\Filament\Widgets\ClientInstallmentPaymentWidget;
use App\Filament\Widgets\CashSalesProfitChart;
use App\Filament\Widgets\MonthlySalesChart;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->userMenuItems([
    UserMenuItem::make()
    ->label('English')
    ->url(fn (): string => route('lang.switch', 'en'))
    ,
UserMenuItem::make()
    ->label('العربية')
    ->url(fn (): string => route('lang.switch', 'ar'))
   ,
            ])
            ->brandLogo(fn () => view('filament.logo-with-text'))
            ->favicon(asset('favicon-removebg-preview (1).png'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\ClientInstallmentPayments::class,
                \App\Filament\Pages\SalesProfitSummary::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
                // FinancialStats::class, 
                // SaleOptionsButton::class, 
                // ClientInstallmentPaymentWidget::class,
                // MonthlySalesChart::class,
                // CashSalesProfitChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                \App\Http\Middleware\SetLocale::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
