<?php

namespace App\Filament\Resources\TherapistResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AvailabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'availabilities';

    protected static ?string $title = 'Availability Schedule';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day_of_week')
            ->columns([
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
                    ->label('Duration')
                    ->state(function ($record): string {
                        $start = $record->start_time;
                        $end = $record->end_time;
                        $duration = $start->diff($end);
                        return $duration->format('%H:%I');
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('day_of_week')
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Availability'),
                Tables\Actions\Action::make('quick_schedule')
                    ->label('Quick Schedule Setup')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->form([
                        Forms\Components\CheckboxList::make('days')
                            ->label('Select Days')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->columns(2)
                            ->required(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Start Time')
                                    ->required()
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->default('09:00'),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('End Time')
                                    ->required()
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->default('17:00')
                                    ->after('start_time'),
                            ]),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $therapist = $livewire->getOwnerRecord();
                        
                        foreach ($data['days'] as $day) {
                            // Check if availability already exists for this day and time
                            $exists = $therapist->availabilities()
                                ->where('day_of_week', $day)
                                ->where('start_time', $data['start_time'])
                                ->where('end_time', $data['end_time'])
                                ->exists();
                            
                            if (!$exists) {
                                $therapist->availabilities()->create([
                                    'day_of_week' => $day,
                                    'start_time' => $data['start_time'],
                                    'end_time' => $data['end_time'],
                                    'is_active' => true,
                                ]);
                            }
                        }
                        
                        $livewire->dispatch('notify', [
                            'type' => 'success',
                            'message' => 'Availability schedule created successfully!'
                        ]);
                    }),
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
            ->defaultSort('day_of_week')
            ->defaultSort('start_time');
    }
}