<?php

namespace App\Filament\Therapist\Resources;

use App\Filament\Therapist\Resources\TherapistServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TherapistServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'My Services';

    protected static ?string $modelLabel = 'Service';

    protected static ?string $pluralModelLabel = 'My Services';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // Only show services that this therapist is assigned to
        return parent::getEloquentQuery()
            ->whereHas('therapists', function (Builder $query) {
                $query->where('therapist_id', Auth::guard('therapist')->id());
            })
            ->with(['treatment', 'therapists']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Service Information')
                    ->schema([
                        Forms\Components\TextInput::make('treatment_name')
                            ->label('Treatment Category')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (!$record || !$record->treatment) return 'N/A';
                                return $record->treatment->name;
                            }),

                        Forms\Components\TextInput::make('title')
                            ->label('Service Title')
                            ->disabled(),

                        Forms\Components\TextInput::make('subtitle')
                            ->label('Subtitle')
                            ->disabled(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label('Price (£)')
                                    ->prefix('£')
                                    ->numeric()
                                    ->disabled(),

                                Forms\Components\TextInput::make('duration')
                                    ->label('Duration (minutes)')
                                    ->suffix('min')
                                    ->numeric()
                                    ->disabled(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->disabled()
                            ->rows(3),

                        Forms\Components\Textarea::make('benefits')
                            ->label('Benefits')
                            ->disabled()
                            ->rows(3),

                        Forms\Components\FileUpload::make('image')
                            ->label('Service Image')
                            ->disabled()
                            ->image(),
                    ]),

                Forms\Components\Section::make('Service Statistics')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('total_bookings')
                                    ->label('Total Bookings')
                                    ->content(function ($record) {
                                        if (!$record) return '0';
                                        return $record->bookings()
                                            ->where('therapist_id', Auth::guard('therapist')->id())
                                            ->count();
                                    }),

                                Forms\Components\Placeholder::make('this_month_bookings')
                                    ->label('This Month Bookings')
                                    ->content(function ($record) {
                                        if (!$record) return '0';
                                        return $record->bookings()
                                            ->where('therapist_id', Auth::guard('therapist')->id())
                                            ->whereMonth('date', now()->month)
                                            ->whereYear('date', now()->year)
                                            ->count();
                                    }),

                                Forms\Components\Placeholder::make('revenue')
                                    ->label('Total Revenue')
                                    ->content(function ($record) {
                                        if (!$record) return '£0.00';
                                        $total = $record->bookings()
                                            ->where('therapist_id', Auth::guard('therapist')->id())
                                            ->where('status', 'completed')
                                            ->sum('price');
                                        return '£' . number_format($total, 2);
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Placeholder::make('average_rating')
                                    ->label('Average Rating')
                                    ->content(function ($record) {
                                        if (!$record) return 'N/A';
                                        // You can implement rating system later
                                        return 'N/A';
                                    }),

                                Forms\Components\Placeholder::make('last_booking')
                                    ->label('Last Booking')
                                    ->content(function ($record) {
                                        if (!$record) return 'Never';
                                        $lastBooking = $record->bookings()
                                            ->where('therapist_id', Auth::guard('therapist')->id())
                                            ->latest('created_at')
                                            ->first();

                                        return $lastBooking
                                            ? $lastBooking->created_at->diffForHumans()
                                            : 'Never';
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Recent Bookings')
                    ->schema([
                        Forms\Components\Placeholder::make('recent_bookings_list')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return 'No bookings yet';

                                $recentBookings = $record->bookings()
                                    ->where('therapist_id', Auth::guard('therapist')->id())
                                    ->latest('created_at')
                                    ->limit(5)
                                    ->get();

                                if ($recentBookings->isEmpty()) {
                                    return 'No bookings for this service yet';
                                }

                                $output = '<div class="space-y-2">';
                                foreach ($recentBookings as $booking) {
                                    $statusColor = match ($booking->status) {
                                        'confirmed' => 'text-green-600',
                                        'pending' => 'text-yellow-600',
                                        'completed' => 'text-blue-600',
                                        'cancelled' => 'text-red-600',
                                        default => 'text-gray-600'
                                    };

                                    $output .= '<div class="flex justify-between items-center p-2 bg-gray-50 rounded">';
                                    $output .= '<div>';
                                    $output .= '<div class="font-medium">' . $booking->name . '</div>';
                                    $output .= '<div class="text-sm text-gray-500">' . $booking->date->format('M d, Y') . ' at ' . $booking->time->format('H:i') . '</div>';
                                    $output .= '</div>';
                                    $output .= '<div class="text-right">';
                                    $output .= '<div class="' . $statusColor . ' font-medium capitalize">' . $booking->status . '</div>';
                                    $output .= '<div class="text-sm text-gray-500">£' . number_format($booking->price, 2) . '</div>';
                                    $output .= '</div>';
                                    $output .= '</div>';
                                }
                                $output .= '</div>';

                                return new \Illuminate\Support\HtmlString($output);
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->size(50),

                Tables\Columns\TextColumn::make('treatment.name')
                    ->label('Treatment')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Service Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Service $record): string => $record->subtitle ?? ''),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('GBP')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->suffix(' min')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Total Bookings')
                    ->state(function (Service $record): int {
                        return $record->bookings()
                            ->where('therapist_id', Auth::guard('therapist')->id())
                            ->count();
                    })
                    ->badge()
                    ->color('primary')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('this_month_bookings')
                    ->label('This Month')
                    ->state(function (Service $record): int {
                        return $record->bookings()
                            ->where('therapist_id', Auth::guard('therapist')->id())
                            ->whereMonth('date', now()->month)
                            ->whereYear('date', now()->year)
                            ->count();
                    })
                    ->badge()
                    ->color('warning')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('revenue')
                    ->label('Total Revenue')
                    ->state(function (Service $record): string {
                        $total = $record->bookings()
                            ->where('therapist_id', Auth::guard('therapist')->id())
                            ->where('status', 'completed')
                            ->sum('price');
                        return '£' . number_format($total, 2);
                    })
                    ->weight('bold')
                    ->color('success')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('last_booking')
                    ->label('Last Booked')
                    ->state(function (Service $record): string {
                        $lastBooking = $record->bookings()
                            ->where('therapist_id', Auth::guard('therapist')->id())
                            ->latest('created_at')
                            ->first();

                        return $lastBooking
                            ? $lastBooking->created_at->diffForHumans()
                            : 'Never';
                    })
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('status')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('treatment_id')
                    ->label('Treatment')
                    ->relationship('treatment', 'name')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('status')
                    ->label('Active')
                    ->trueLabel('Active Services')
                    ->falseLabel('Inactive Services'),

                Tables\Filters\Filter::make('has_bookings')
                    ->label('With Bookings')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('bookings', function (Builder $subQuery) {
                            $subQuery->where('therapist_id', Auth::guard('therapist')->id());
                        });
                    }),

                Tables\Filters\Filter::make('this_month_bookings')
                    ->label('Booked This Month')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('bookings', function (Builder $subQuery) {
                            $subQuery->where('therapist_id', Auth::guard('therapist')->id())
                                ->whereMonth('date', now()->month)
                                ->whereYear('date', now()->year);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('view_bookings')
                    ->label('View Bookings')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->url(function (Service $record): string {
                        return '/therapist/therapist-bookings?tableFilters[service_id][value]=' . $record->id;
                    }),

                Tables\Actions\Action::make('service_stats')
                    ->label('Quick Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color('warning')
                    ->modalContent(function (Service $record): \Illuminate\Contracts\View\View {
                        $stats = [
                            'total_bookings' => $record->bookings()
                                ->where('therapist_id', Auth::guard('therapist')->id())
                                ->count(),
                            'this_month' => $record->bookings()
                                ->where('therapist_id', Auth::guard('therapist')->id())
                                ->whereMonth('date', now()->month)
                                ->whereYear('date', now()->year)
                                ->count(),
                            'completed' => $record->bookings()
                                ->where('therapist_id', Auth::guard('therapist')->id())
                                ->where('status', 'completed')
                                ->count(),
                            'revenue' => $record->bookings()
                                ->where('therapist_id', Auth::guard('therapist')->id())
                                ->where('status', 'completed')
                                ->sum('price'),
                        ];

                        return view('filament.therapist.service-stats-modal', [
                            'service' => $record,
                            'stats' => $stats
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('title', 'asc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTherapistServices::route('/'),
            'view' => Pages\ViewTherapistService::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $servicesCount = static::getEloquentQuery()->count();
        return $servicesCount > 0 ? (string) $servicesCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
