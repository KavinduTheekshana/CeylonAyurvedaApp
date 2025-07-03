<?php

// app/Filament/Therapist/Pages/Dashboard.php
namespace App\Filament\Therapist\Pages;

use App\Filament\Therapist\Widgets\TherapistStatsWidget;
use App\Filament\Therapist\Widgets\TherapistBookingsChart;
use App\Filament\Therapist\Widgets\UpcomingBookingsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament-panels::pages.dashboard';

    public function getWidgets(): array
    {
        return [
            TherapistStatsWidget::class,
            // TherapistBookingsChart::class,
            UpcomingBookingsWidget::class,
        ];
    }

    public function getTitle(): string
    {
        $therapist = Auth::guard('therapist')->user();
        return "Welcome back, {$therapist->name}!";
    }

    public function getSubheading(): ?string
    {
        return 'Here\'s an overview of your appointments and performance.';
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}