<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationGroup;
use App\Filament\Therapist\Pages\Dashboard;

class TherapistPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('therapist')
            ->path('therapist')
            ->login()
            ->authGuard('therapist')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('Therapist Portal')
            ->favicon(asset('favicon.ico'))
            ->discoverResources(in: app_path('Filament/Therapist/Resources'), for: 'App\\Filament\\Therapist\\Resources')
            ->discoverPages(in: app_path('Filament/Therapist/Pages'), for: 'App\\Filament\\Therapist\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Therapist/Widgets'), for: 'App\\Filament\\Therapist\\Widgets')
            ->widgets([
                // Widgets will be auto-discovered
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Appointments')
                    ->icon('heroicon-o-calendar-days'),
                NavigationGroup::make()
                    ->label('Services & Schedule')
                    ->icon('heroicon-o-cog-6-tooth'),
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
            ])
            ->profile()
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Edit profile')
                    ->url('/therapist/profile')
                    ->icon('heroicon-m-user-circle'),
                'logout' => \Filament\Navigation\MenuItem::make()
                    ->label('Log out')
                    ->url('/therapist/logout')
                    ->icon('heroicon-m-arrow-left-on-rectangle'),
            ]);
    }
}