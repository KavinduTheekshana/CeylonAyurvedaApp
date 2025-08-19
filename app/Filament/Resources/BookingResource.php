<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Models\Therapist;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'Bookings';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Customer Information')
                    ->description('Manage customer details and contact information')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Registered User')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select registered user (optional)')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $user = User::find($state);
                                            if ($user) {
                                                $set('name', $user->name);
                                                $set('email', $user->email);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),
                                
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Customer full name'),
                                
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->placeholder('customer@email.com'),
                                
                                TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->placeholder('+44 123 456 7890'),
                                
                                Placeholder::make('')
                                    ->content(''),
                            ]),
                    ]),

                Section::make('Address Information')
                    ->description('Customer address details')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('address_line1')
                                    ->label('Address Line 1')
                                    ->required()
                                    ->placeholder('Street address')
                                    ->columnSpan(2),
                                
                                TextInput::make('address_line2')
                                    ->label('Address Line 2')
                                    ->placeholder('Apartment, suite, etc. (optional)')
                                    ->columnSpan(2),
                                
                                TextInput::make('city')
                                    ->required()
                                    ->placeholder('City'),
                                
                                TextInput::make('postcode')
                                    ->required()
                                    ->placeholder('Postcode')
                                    // ->rules(['regex:/^[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}$/i']),
                            ]),
                    ]),

                Section::make('Service & Appointment Details')
                    ->description('Configure service, date, time and therapist')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('service_id')
                                    ->label('Service')
                                    ->relationship('service', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $service = Service::find($state);
                                            if ($service) {
                                                $set('price', $service->price);
                                                $set('original_price', $service->price);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),
                                
                                Select::make('location_id')
                                    ->label('Location')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select location'),
                                
                                Select::make('therapist_id')
                                    ->label('Assigned Therapist')
                                    ->relationship('therapist', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select therapist (optional)')
                                    ->helperText('You can assign or reassign a therapist at any time'),
                                
                                DatePicker::make('date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    // ->minDate(now())
                                    ->helperText('Select appointment date'),
                                
                                TimePicker::make('time')
                                    ->required()
                                    ->native(false)
                                    ->minutesStep(15)
                                    ->helperText('Select appointment time'),
                            ]),
                    ]),

                Section::make('Pricing & Discount Information')
                    ->description('View and manage pricing details')
                    ->icon('heroicon-o-currency-pound')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('original_price')
                                    ->label('Original Price')
                                    ->prefix('£')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Service base price'),

                                TextInput::make('price')
                                    ->label('Final Price')
                                    ->prefix('£')
                                    ->numeric()
                                    ->required()
                                    ->helperText('Price after discount'),
                                
                                TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->prefix('£')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total discount applied'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('coupon_code')
                                    ->label('Coupon Code')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('No coupon applied'),

                                Placeholder::make('discount_percentage')
                                    ->label('Discount Percentage')
                                    ->content(function ($record) {
                                        if (!$record || !$record->original_price || $record->original_price == 0) {
                                            return '0%';
                                        }
                                        $percentage = (($record->original_price - $record->price) / $record->original_price) * 100;
                                        return round($percentage, 1) . '%';
                                    }),
                            ]),
                    ])
                    ->visible(fn($record) => $record && ($record->coupon_id || $record->original_price != $record->price)),

                Section::make('Payment Information')
                    ->description('Payment status and transaction details')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('payment_status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'paid' => 'Paid',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->default('pending')
                                    ->required(),
                                
                                TextInput::make('payment_method')
                                    ->placeholder('card, bank_transfer, etc.'),
                                
                                TextInput::make('stripe_payment_intent_id')
                                    ->label('Stripe Payment ID')
                                    ->placeholder('pi_xxxxxxxxx')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->visible(fn($record) => $record && ($record->stripe_payment_intent_id || $record->payment_method)),

                Section::make('Booking Management')
                    ->description('Status, notes and reference information')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'confirmed' => 'Confirmed',
                                        'in_progress' => 'In Progress',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                        'no_show' => 'No Show',
                                    ])
                                    ->default('confirmed')
                                    ->required()
                                    ->reactive(),
                                
                                TextInput::make('reference')
                                    ->unique(ignoreRecord: true)
                                    ->required()
                                    ->disabled(fn($record) => $record !== null) // Disable editing for existing records
                                    ->dehydrated(fn($record) => $record === null) // Only save for new records
                                    ->default(fn() => 'BK-' . strtoupper(Str::random(8)))
                                    ->helperText('Auto-generated booking reference'),
                            ]),
                        
                        Textarea::make('notes')
                            ->placeholder('Add any special notes or requirements...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),
                
                TextColumn::make('name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable()
                    ->description(fn (Booking $record): string => $record->email),
                
                TextColumn::make('service.title')
                    ->label('Service')
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                
                TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->sortable()
                    ->placeholder('Not assigned')
                    ->badge()
                    ->color('info'),
                
                TextColumn::make('date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->description(fn (Booking $record): string => $record->time ? date('g:i A', strtotime($record->time)) : ''),
                
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'info' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'no_show',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'confirmed',
                        'heroicon-o-play' => 'in_progress',
                        'heroicon-o-check-badge' => 'completed',
                        'heroicon-o-x-circle' => 'cancelled',
                        'heroicon-o-user-minus' => 'no_show',
                    ]),
                
                BadgeColumn::make('payment_status')
                    ->label('Payment')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ])
                    ->toggleable(),
                
                TextColumn::make('price')
                    ->label('Amount')
                    ->money('GBP')
                    ->sortable()
                    ->description(function (Booking $record): ?string {
                        if ($record->discount_amount > 0) {
                            return 'Discount: £' . number_format($record->discount_amount, 2);
                        }
                        return null;
                    }),
                
                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('Not specified')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('coupon_code')
                    ->label('Coupon')
                    ->placeholder('No coupon')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Booked On')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                IconColumn::make('user_id')
                    ->label('User Type')
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-user-plus')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Booking $record): string => $record->user_id ? 'Registered User' : 'Guest User')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'no_show' => 'No Show',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                SelectFilter::make('therapist_id')
                    ->label('Therapist')
                    ->relationship('therapist', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'title')
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('From Date'),
                        DatePicker::make('date_to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                TernaryFilter::make('user_id')
                    ->label('User Type')
                    ->placeholder('All bookings')
                    ->trueLabel('Registered Users')
                    ->falseLabel('Guest Users'),

                TernaryFilter::make('has_discount')
                    ->label('Has Discount')
                    ->placeholder('All bookings')
                    ->trueLabel('With Discount')
                    ->falseLabel('No Discount')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('coupon_id'),
                        false: fn (Builder $query) => $query->whereNull('coupon_id'),
                    ),
            ])
            ->actions([
                Action::make('assign_therapist')
                    ->label('Assign Therapist')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->form([
                        Select::make('therapist_id')
                            ->label('Select Therapist')
                            ->options(Therapist::where('status', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $record->update(['therapist_id' => $data['therapist_id']]);
                        
                        $therapistName = Therapist::find($data['therapist_id'])->name;
                        
                        Notification::make()
                            ->title('Therapist Assigned')
                            ->body("Therapist '{$therapistName}' has been assigned to booking {$record->reference}")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Booking $record): bool => !$record->therapist_id),

                Action::make('change_therapist')
                    ->label('Change Therapist')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Select::make('therapist_id')
                            ->label('Select New Therapist')
                            ->options(Therapist::where('status', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $oldTherapist = $record->therapist->name ?? 'None';
                        $record->update(['therapist_id' => $data['therapist_id']]);
                        
                        $newTherapist = Therapist::find($data['therapist_id'])->name;
                        
                        Notification::make()
                            ->title('Therapist Changed')
                            ->body("Therapist changed from '{$oldTherapist}' to '{$newTherapist}' for booking {$record->reference}")
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (Booking $record): bool => (bool) $record->therapist_id),

                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $count = $records->count();
                            foreach ($records as $record) {
                                $record->update(['status' => 'completed']);
                            }
                            
                            Notification::make()
                                ->title('Bookings Updated')
                                ->body("{$count} booking(s) marked as completed")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}