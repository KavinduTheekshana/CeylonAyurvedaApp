<?php

// app/Filament/Therapist/Widgets/TherapistStatsWidget.php
namespace App\Filament\Therapist\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TherapistStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $therapistId = Auth::guard('therapist')->id();

        // Today's bookings
        $todayBookings = Booking::where('therapist_id', $therapistId)
            ->whereDate('date', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();

        // This week's bookings
        $thisWeekBookings = Booking::where('therapist_id', $therapistId)
            ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereIn('status', ['confirmed', 'pending', 'completed'])
            ->count();

        // Total completed bookings
        $totalCompleted = Booking::where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->count();

        // This month's revenue
        $monthlyRevenue = Booking::where('therapist_id', $therapistId)
            ->where('status', 'completed')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('price');

        // Next booking
        $nextBooking = Booking::where('therapist_id', $therapistId)
            ->where('date', '>=', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('date')
            ->orderBy('time')
            ->first();

        return [
            Stat::make('Today\'s Appointments', $todayBookings)
                ->description('Scheduled for today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($todayBookings > 0 ? 'success' : 'gray'),

            Stat::make('This Week', $thisWeekBookings)
                ->description('Total bookings this week')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Total Completed', $totalCompleted)
                ->description('All time completed bookings')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Monthly Revenue', '£' . number_format($monthlyRevenue, 2))
                ->description('Revenue this month')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Next Appointment', $nextBooking ? $nextBooking->date->format('M d') : 'None')
                ->description($nextBooking ? $nextBooking->time->format('H:i') . ' - ' . $nextBooking->name : 'No upcoming appointments')
                ->descriptionIcon('heroicon-m-clock')
                ->color($nextBooking ? 'primary' : 'gray'),
        ];
    }
}

// ============================================================================

// app/Filament/Therapist/Widgets/TherapistBookingsChart.php
namespace App\Filament\Therapist\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TherapistBookingsChart extends ChartWidget
{
    protected static ?string $heading = 'Bookings Overview (Last 30 Days)';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $therapistId = Auth::guard('therapist')->id();

        // Get data for the last 30 days
        $data = Booking::select(
                DB::raw('DATE(date) as booking_date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('therapist_id', $therapistId)
            ->where('date', '>=', now()->subDays(30))
            ->whereIn('status', ['confirmed', 'pending', 'completed'])
            ->groupBy('booking_date')
            ->orderBy('booking_date')
            ->get();

        // Fill in missing dates with 0 count
        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $data->where('booking_date', $date)->first()?->count ?? 0;
            $dates->push([
                'date' => $date,
                'count' => $count
            ]);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily Bookings',
                    'data' => $dates->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $dates->map(function ($item) {
                return \Carbon\Carbon::parse($item['date'])->format('M j');
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

// ============================================================================

// app/Filament/Therapist/Widgets/UpcomingBookingsWidget.php
namespace App\Filament\Therapist\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingBookingsWidget extends BaseWidget
{
    protected static ?string $heading = 'Upcoming Appointments';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('therapist_id', Auth::guard('therapist')->id())
                    ->where('date', '>=', today())
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->with(['service', 'user'])
                    ->orderBy('date')
                    ->orderBy('time')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time')
                    ->label('Time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('service.title')
                    ->label('Service')
                    ->limit(30),

                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn (Booking $record): string => $record->phone),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->money('GBP'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Booking $record) => "/therapist/therapist-bookings/{$record->id}"),
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Booking $record) {
                        $record->update(['status' => 'completed']);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => 
                        $record->status === 'confirmed' && $record->date <= today()
                    ),
            ]);
    }
}