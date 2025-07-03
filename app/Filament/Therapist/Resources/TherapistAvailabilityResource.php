<?php

namespace App\Filament\Therapist\Resources;

use App\Filament\Therapist\Resources\TherapistAvailabilityResource\Pages;
use App\Models\TherapistAvailability;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class TherapistAvailabilityResource extends Resource
{
    protected static ?string $model = TherapistAvailability::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    
    protected static ?string $navigationLabel = 'My Availability';
    
    protected static ?string $modelLabel = 'Availability';
    
    protected static ?string $pluralModelLabel = 'My Availability';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        // Only show availability for the authenticated therapist
        return parent::getEloquentQuery()
            ->where('therapist_id', Auth::guard('therapist')->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Availability Details')
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
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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

                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Bookings This Week')
                    ->state(function ($record): int {
                        $dayName = $record->day_of_week;
                        $startOfWeek = now()->startOfWeek();
                        $endOfWeek = now()->endOfWeek();
                        
                        return \App\Models\Booking::where('therapist_id', Auth::guard('therapist')->id())
                            ->whereBetween('date', [$startOfWeek, $endOfWeek])
                            ->whereRaw('LOWER(DAYNAME(date)) = ?', [strtolower($dayName)])
                            ->whereIn('status', ['confirmed', 'pending'])
                            ->count();
                    })
                    ->badge()
                    ->color('primary'),
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Availability')
                    ->modalDescription('Are you sure you want to delete this availability slot? This may affect future bookings.')
                    ->action(function (TherapistAvailability $record) {
                        // Check if there are future bookings in this time slot
                        $futureBookings = \App\Models\Booking::where('therapist_id', $record->therapist_id)
                            ->where('date', '>=', today())
                            ->whereRaw('LOWER(DAYNAME(date)) = ?', [strtolower($record->day_of_week)])
                            ->whereTime('time', '>=', $record->start_time)
                            ->whereTime('time', '<', $record->end_time)
                            ->whereIn('status', ['confirmed', 'pending'])
                            ->count();

                        if ($futureBookings > 0) {
                            Notification::make()
                                ->title('Cannot Delete')
                                ->body("This time slot has {$futureBookings} future booking(s). Please reschedule or cancel them first.")
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->title('Availability Deleted')
                            ->body('The availability slot has been deleted successfully.')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (TherapistAvailability $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (TherapistAvailability $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (TherapistAvailability $record): string => $record->is_active ? 'warning' : 'success')
                    ->action(function (TherapistAvailability $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        
                        Notification::make()
                            ->title('Availability Updated')
                            ->body('The availability slot has been ' . ($record->is_active ? 'activated' : 'deactivated') . '.')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('quick_setup')
                    ->label('Quick Weekly Setup')
                    ->icon('heroicon-o-calendar-days')
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
                    ->action(function (array $data) {
                        $therapistId = Auth::guard('therapist')->id();
                        $created = 0;
                        
                        foreach ($data['days'] as $day) {
                            // Check if availability already exists for this day and time
                            $exists = TherapistAvailability::where('therapist_id', $therapistId)
                                ->where('day_of_week', $day)
                                ->where('start_time', $data['start_time'])
                                ->where('end_time', $data['end_time'])
                                ->exists();
                            
                            if (!$exists) {
                                TherapistAvailability::create([
                                    'therapist_id' => $therapistId,
                                    'day_of_week' => $day,
                                    'start_time' => $data['start_time'],
                                    'end_time' => $data['end_time'],
                                    'is_active' => true,
                                ]);
                                $created++;
                            }
                        }
                        
                        Notification::make()
                            ->title('Availability Created')
                            ->body("{$created} availability slots have been created successfully.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['is_active' => true]);
                            });
                            
                            Notification::make()
                                ->title('Availability Activated')
                                ->body('Selected availability slots have been activated.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['is_active' => false]);
                            });
                            
                            Notification::make()
                                ->title('Availability Deactivated')
                                ->body('Selected availability slots have been deactivated.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('day_of_week')
            ->defaultSort('start_time');
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