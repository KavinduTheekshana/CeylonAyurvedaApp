<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Location Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                        if ($operation !== 'create') {
                            return;
                        }
                        $set('slug', Str::slug($state));
                    }),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(),

                Forms\Components\Textarea::make('address')
                    ->required()
                    ->maxLength(500),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('postcode')
                            ->required()
                            ->maxLength(20),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step(0.00000001),

                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step(0.00000001),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                    ]),

                Forms\Components\Textarea::make('description')
                    ->maxLength(1000),

                Forms\Components\KeyValue::make('operating_hours')
                    ->keyLabel('Day')
                    ->valueLabel('Hours')
                    ->default([
                        'Monday' => '9:00 AM - 6:00 PM',
                        'Tuesday' => '9:00 AM - 6:00 PM',
                        'Wednesday' => '9:00 AM - 6:00 PM',
                        'Thursday' => '9:00 AM - 6:00 PM',
                        'Friday' => '9:00 AM - 6:00 PM',
                        'Saturday' => '9:00 AM - 4:00 PM',
                        'Sunday' => 'Closed',
                    ]),

                Forms\Components\FileUpload::make('image')
                    ->directory('locations')
                    ->image()
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('16:9'),

                Forms\Components\TextInput::make('service_radius_miles')
                    ->numeric()
                    ->default(5)
                    ->suffix('miles'),

                Forms\Components\Toggle::make('status')
                    ->label('Active')
                    ->default(true),

                Forms\Components\Select::make('therapists')
                    ->relationship('therapists', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('postcode')
                    ->searchable(),

                Tables\Columns\TextColumn::make('therapists_count')
                    ->counts('therapists')
                    ->badge()
                    ->color('primary'),


                Tables\Columns\IconColumn::make('status')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
