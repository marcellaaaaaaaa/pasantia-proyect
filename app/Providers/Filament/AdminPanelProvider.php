<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetTenantScope;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('admin-filters', asset('css/admin-filters.css')),
        ]);
    }

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
                NavigationGroup::make('Censo y Comunidad'),
                NavigationGroup::make('Gestión de Cobros'),
                NavigationGroup::make('Finanzas'),
                NavigationGroup::make('Opciones'),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): HtmlString => $this->portalButton(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
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

    private function portalButton(): HtmlString
    {
        $slug = auth()->user()?->tenant?->slug;

        if (! $slug) {
            return new HtmlString('');
        }

        $url = url("/portal/{$slug}");

        return new HtmlString(<<<HTML
            <a href="{$url}" target="_blank" rel="noopener noreferrer"
               style="display:inline-flex;align-items:center;gap:6px;margin-right:12px;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:500;color:#3b82f6;border:1px solid #3b82f6;text-decoration:none;transition:background .15s"
               onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='transparent'">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                Portal Vecinal
            </a>
        HTML);
    }
}
