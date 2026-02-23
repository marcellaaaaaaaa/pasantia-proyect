<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetTenantScope;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Widgets\CollectorPerformanceWidget;
use App\Filament\Widgets\PendingRemittancesWidget;
use App\Filament\Widgets\RevenueChartWidget;
use Filament\Pages;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('CommunityERP')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                NavigationGroup::make('Territorial'),
                NavigationGroup::make('Cobros'),
                NavigationGroup::make('Finanzas'),
                NavigationGroup::make('Sistema'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('
                    <style>
                        .fi-ta-filters-above-content-ctn {
                            background-color: rgb(248 250 252);
                            padding-top: 1.25rem !important;
                            padding-bottom: 1.25rem !important;
                            border-bottom: 2px solid rgb(226 232 240) !important;
                        }
                        :is(.dark .fi-ta-filters-above-content-ctn) {
                            background-color: rgba(255, 255, 255, 0.03);
                            border-bottom-color: rgba(255, 255, 255, 0.15) !important;
                        }
                    </style>
                '),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                PendingRemittancesWidget::class,
                RevenueChartWidget::class,
                CollectorPerformanceWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
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
                SetTenantScope::class,
            ]);
    }
}
