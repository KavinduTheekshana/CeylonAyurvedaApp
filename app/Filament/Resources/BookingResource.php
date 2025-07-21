<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('date')->required(),
                TimePicker::make('time')->required(),

                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
                TextInput::make('phone')->tel()->required(),

                TextInput::make('address_line1')->required(),
                TextInput::make('address_line2')->nullable(),
                TextInput::make('city')->required(),
                TextInput::make('postcode')->required(),

                Textarea::make('notes')->nullable(),

                TextInput::make('price')->numeric()->required(),
                TextInput::make('reference')->unique()->required(),

                Select::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
                    ->default('confirmed')
                    ->required(),

                Section::make('Pricing & Discount')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('original_price')
                                    ->label('Original Price')
                                    ->prefix('£')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('price')
                                    ->label('Final Price')
                                    ->prefix('£')
                                    ->numeric()
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('coupon_code')
                                    ->label('Coupon Code')
                                    ->disabled()
                                    ->dehydrated(false),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->prefix('£')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->visible(fn($record) => $record && $record->coupon_id),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('phone')->sortable(),
                TextColumn::make('service.title')->label('Service')->sortable(),
                TextColumn::make('date')->sortable(),
                TextColumn::make('time')->sortable(),
                TextColumn::make('status')->sortable()->badge(),
                TextColumn::make('price')->sortable()->money('GBP'),
                TextColumn::make('created_at')->label('Booked On')->dateTime(),
                TextColumn::make('coupon_code')
                    ->label('Coupon')
                    ->searchable()
                    ->placeholder('No coupon')
                    ->toggleable(),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('GBP')
                    ->placeholder('-')
                    ->color('success')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),

                TernaryFilter::make('user_id')
                    ->label('Registered Users Only')
                    ->trueLabel('Yes')
                    ->falseLabel('Guests Only'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
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
}
