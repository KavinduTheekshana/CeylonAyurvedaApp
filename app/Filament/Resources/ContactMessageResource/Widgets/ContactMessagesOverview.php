<?php

// app/Filament/Widgets/ContactMessagesOverview.php
namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ContactMessagesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Get current period stats
        $totalMessages = ContactMessage::count();
        $pendingMessages = ContactMessage::where('status', 'pending')->count();
        $todayMessages = ContactMessage::whereDate('created_at', today())->count();
        $responseRate = $this->getResponseRate();

        // Get previous period for comparison
        $lastWeekMessages = ContactMessage::whereBetween('created_at', [
            now()->subWeeks(2)->startOfWeek(),
            now()->subWeek()->endOfWeek()
        ])->count();

        $thisWeekMessages = ContactMessage::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->count();

        $weeklyChange = $lastWeekMessages > 0 
            ? (($thisWeekMessages - $lastWeekMessages) / $lastWeekMessages) * 100 
            : 0;

        return [
            Stat::make('Total Messages', $totalMessages)
                ->description('All time messages received')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('Pending Messages', $pendingMessages)
                ->description('Awaiting response')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingMessages > 10 ? 'danger' : ($pendingMessages > 5 ? 'warning' : 'success'))
                ->url(route('filament.admin.resources.contact-messages.index', ['tableFilters[status][value]' => 'pending'])),

            Stat::make('Today\'s Messages', $todayMessages)
                ->description('Messages received today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('This Week', $thisWeekMessages)
                ->description($weeklyChange >= 0 ? "{$weeklyChange}% increase" : abs($weeklyChange) . "% decrease")
                ->descriptionIcon($weeklyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weeklyChange >= 0 ? 'success' : 'danger'),

            Stat::make('Response Rate', number_format($responseRate, 1) . '%')
                ->description('Messages with admin response')
                ->descriptionIcon('heroicon-m-chat-bubble-left-ellipsis')
                ->color($responseRate >= 80 ? 'success' : ($responseRate >= 60 ? 'warning' : 'danger')),
        ];
    }

    private function getResponseRate(): float
    {
        $totalMessages = ContactMessage::count();
        if ($totalMessages === 0) {
            return 100;
        }

        $respondedMessages = ContactMessage::whereNotNull('admin_response')->count();
        return ($respondedMessages / $totalMessages) * 100;
    }
}

// app/Filament/Widgets/ContactMessagesChart.php
namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ContactMessagesChart extends ChartWidget
{
    protected static ?string $heading = 'Messages Over Time';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get data for the last 30 days
        $data = ContactMessage::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill in missing dates with 0 count
        $dates = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $data->where('date', $date)->first()?->count ?? 0;
            $dates->push([
                'date' => $date,
                'count' => $count
            ]);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Messages per day',
                    'data' => $dates->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(154, 86, 58, 0.1)',
                    'borderColor' => 'rgba(154, 86, 58, 1)',
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

// app/Filament/Widgets/RecentContactMessages.php
namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentContactMessages extends BaseWidget
{
    protected static ?string $heading = 'Recent Messages';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ContactMessage::query()
                    ->with(['branch', 'user'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'resolved',
                        'gray' => 'closed',
                    ]),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->limit(20),

                Tables\Columns\IconColumn::make('is_guest')
                    ->label('Guest')
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-user-circle'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ContactMessage $record) => route('filament.admin.resources.contact-messages.view', $record)),
                Tables\Actions\Action::make('respond')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->url(fn (ContactMessage $record) => route('filament.admin.resources.contact-messages.edit', $record))
                    ->visible(fn (ContactMessage $record) => $record->status === 'pending'),
            ]);
    }
}