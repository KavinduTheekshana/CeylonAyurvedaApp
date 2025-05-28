<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TherapistAvailabilityResource\Pages;
use App\Models\TherapistAvailability;
use App\Models\Therapist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class TherapistAvailabilityResource extends Resource
{
    protected static ?string $model = TherapistAvailability::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Therapist Availability';
    protected static ?string $pluralLabel = 'Therapist Availabilities';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('therapist_id')
                    ->label('Therapist')
                    ->options(Therapist::active()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('day_of_week')
                    ->label('Day of Week')
                    ->options([
                        'monday' => 'Monday',
                        'tuesday' => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday' => 'Thursday',
                        'friday' => 'Friday',
                        'saturday' => 'Saturday',
                        'sunday' => 'Sunday',
                    ])
                    ->required()
                    ->native(false),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->seconds(false)
                            ->minutesStep(15),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('End Time')
                            ->required()
                            ->seconds(false)
                            ->minutesStep(15)
                            ->after('start_time'),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Uncheck to temporarily disable this availability slot'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'monday' => 'primary',
                        'tuesday' => 'success',
                        'wednesday' => 'warning',
                        'thursday' => 'danger',
                        'friday' => 'info',
                        'saturday' => 'gray',
                        'sunday' => 'secondary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End Time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time_range')
                    ->label('Time Range')
                    ->state(function (TherapistAvailability $record): string {
                        return $record->start_time->format('H:i') . ' - ' . $record->end_time->format('H:i');
                    })
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('therapist_id')
                    ->label('Therapist')
                    ->options(Therapist::active()->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('day_of_week')
                    ->label('Day of Week')
                    ->options([
                        'monday' => 'Monday',
                        'tuesday' => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday' => 'Thursday',
                        'friday' => 'Friday',
                        'saturday' => 'Saturday',
                        'sunday' => 'Sunday',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('therapist_id')
            ->defaultSort('day_of_week')
            ->defaultSort('start_time');
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
            'index' => Pages\ListTherapistAvailabilities::route('/'),
            'create' => Pages\CreateTherapistAvailability::route('/create'),
            'edit' => Pages\EditTherapistAvailability::route('/{record}/edit'),
        ];
    }
}