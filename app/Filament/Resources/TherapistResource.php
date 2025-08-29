<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TherapistResource\Pages;
use App\Filament\Resources\TherapistResource\RelationManagers\AvailabilitiesRelationManager;
use App\Models\Therapist;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class TherapistResource extends Resource
{
    protected static ?string $model = Therapist::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->required()
                                    ->tel()
                                    ->maxLength(255),

                                TextInput::make('password')
                                    ->password()
                                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                                    ->dehydrated(fn($state) => filled($state))
                                    ->required(fn(string $context): bool => $context === 'create')
                                    ->minLength(8)
                                    ->helperText('Leave blank to keep current password (for edit)'),
                            ]),

                        FileUpload::make('image')
                            ->directory('therapists')
                            ->image()
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('300')
                            ->imageResizeTargetHeight('300'),

                        Textarea::make('bio')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                DatePicker::make('work_start_date')
                                    ->label('Job Start Date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->helperText('Select when the therapist starts/started this job'),

                                Toggle::make('status')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Enable/disable therapist account'),

                                Toggle::make('online_status')
                                    ->label('Online Status')
                                    ->default(false)
                                    ->helperText('Show as online/available to customers'),
                            ]),
                    ]),

                Forms\Components\Section::make('Service User Preferences')
                    ->description('Configure preferences for the types of service users this therapist prefers to work with')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Select::make('preferred_gender')
                                    ->label('Preferred Gender')
                                    ->options([
                                        'all' => 'All Genders',
                                        'male' => 'Male Only',
                                        'female' => 'Female Only',
                                    ])
                                    ->default('all')
                                    ->helperText('Which gender does this therapist prefer to work with?'),

                                TextInput::make('age_range_start')
                                    ->label('Minimum Age')
                                    ->numeric()
                                    ->default(18)
                                    ->minValue(16)
                                    ->maxValue(100)
                                    ->suffix('years'),

                                TextInput::make('age_range_end')
                                    ->label('Maximum Age')
                                    ->numeric()
                                    ->default(65)
                                    ->minValue(16)
                                    ->maxValue(100)
                                    ->suffix('years')
                                    ->gte('age_range_start'),
                            ]),

                        Select::make('preferred_language')
                            ->label('Preferred Language')
                            ->options([
                                'english' => 'English',
                                'spanish' => 'Spanish',
                                'french' => 'French',
                                'german' => 'German',
                                'italian' => 'Italian',
                                'portuguese' => 'Portuguese',
                                'mandarin' => 'Mandarin',
                                'arabic' => 'Arabic',
                                'hindi' => 'Hindi',
                                'urdu' => 'Urdu',
                                'punjabi' => 'Punjabi',
                                'bengali' => 'Bengali',
                                'polish' => 'Polish',
                                'russian' => 'Russian',
                            ])
                            ->default('english')
                            ->searchable()
                            ->helperText('Primary language this therapist is comfortable communicating in'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Service Delivery Preferences')
                    ->description('Configure how this therapist prefers to deliver their services')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('accept_new_patients')
                                    ->label('Accept New Patients')
                                    ->default(true)
                                    ->helperText('Is this therapist currently accepting new patients?'),

                                TextInput::make('max_travel_distance')
                                    ->label('Maximum Travel Distance')
                                    ->numeric()
                                    ->default(10)
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->suffix('miles')
                                    ->helperText('Maximum distance willing to travel for home visits'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('home_visits_only')
                                    ->label('Home Visits Only')
                                    ->default(false)
                                    ->helperText('Does this therapist only provide home visits?')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $set('clinic_visits_only', false);
                                        }
                                    }),

                                Toggle::make('clinic_visits_only')
                                    ->label('Clinic Visits Only')
                                    ->default(false)
                                    ->helperText('Does this therapist only provide clinic-based services?')
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $set('home_visits_only', false);
                                        }
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('weekends_available')
                                    ->label('Available on Weekends')
                                    ->default(false)
                                    ->helperText('Is this therapist available for weekend appointments?'),

                                Toggle::make('evenings_available')
                                    ->label('Available in Evenings')
                                    ->default(false)
                                    ->helperText('Is this therapist available for evening appointments (after 6 PM)?'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Assignments')
                    ->schema([
                        Select::make('locations')
                            ->relationship('locations', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        Select::make('services')
                            ->relationship('services', 'title')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->circular()
                    ->size(40),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('preferred_gender')
                    ->label('Gender Pref.')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'all' => 'success',
                        'male' => 'info',
                        'female' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'all' => 'All',
                        'male' => 'Male',
                        'female' => 'Female',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('age_range')
                    ->label('Age Range')
                    ->state(function (Therapist $record): string {
                        return "{$record->age_range_start} - {$record->age_range_end} years";
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('preferred_language')
                    ->label('Language')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),

                BooleanColumn::make('accept_new_patients')
                    ->label('New Patients')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('service_delivery')
                    ->label('Service Delivery')
                    ->state(function (Therapist $record): string {
                        if ($record->home_visits_only) return 'Home Only';
                        if ($record->clinic_visits_only) return 'Clinic Only';
                        return 'Both';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Home Only' => 'warning',
                        'Clinic Only' => 'info',
                        'Both' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('max_travel_distance')
                    ->label('Travel Distance')
                    ->suffix(' miles')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('availability_flags')
                    ->label('Availability')
                    ->state(function (Therapist $record): string {
                        $flags = [];
                        if ($record->weekends_available) $flags[] = 'Weekends';
                        if ($record->evenings_available) $flags[] = 'Evenings';
                        return empty($flags) ? 'Standard Hours' : implode(', ', $flags);
                    })
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('work_start_date')
                    ->label('Job Start Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('work_status')
                    ->label('Work Status')
                    ->state(function (Therapist $record): string {
                        if (!$record->work_start_date) {
                            return 'Active';
                        }

                        $startDate = \Carbon\Carbon::parse($record->work_start_date);

                        if ($startDate->isFuture()) {
                            return 'Starts ' . $startDate->format('M d, Y');
                        }

                        if ($startDate->isToday()) {
                            return 'Starting Today';
                        }

                        return 'Active';
                    })
                    ->badge()
                    ->color(function (Therapist $record): string {
                        if (!$record->work_start_date) {
                            return 'success';
                        }

                        $startDate = \Carbon\Carbon::parse($record->work_start_date);

                        if ($startDate->isFuture()) {
                            return 'warning';
                        }

                        if ($startDate->isToday()) {
                            return 'info';
                        }

                        return 'success';
                    })
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('services.title')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('availabilities_count')
                    ->label('Availability Slots')
                    ->counts('availabilities')
                    ->badge()
                    ->color('success'),

                TextColumn::make('preferences_updated_at')
                    ->label('Preferences Updated')
                    ->dateTime('M d, Y')
                    ->placeholder('Never updated')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email_verified_at')
                    ->label('Email Verified')
                    ->dateTime('M d, Y')
                    ->placeholder('Not verified')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                BooleanColumn::make('status')
                    ->label('Active'),

                BooleanColumn::make('online_status')
                    ->label('Online')
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Active')
                    ->trueLabel('Only Active')
                    ->falseLabel('Only Inactive')
                    ->nullable(),

                Tables\Filters\TernaryFilter::make('online_status')
                    ->label('Online Status')
                    ->trueLabel('Online Only')
                    ->falseLabel('Offline Only')
                    ->nullable(),

                Tables\Filters\SelectFilter::make('preferred_gender')
                    ->label('Gender Preference')
                    ->options([
                        'all' => 'All Genders',
                        'male' => 'Male Only',
                        'female' => 'Female Only',
                    ]),

                Tables\Filters\SelectFilter::make('preferred_language')
                    ->label('Preferred Language')
                    ->options([
                        'english' => 'English',
                        'spanish' => 'Spanish',
                        'french' => 'French',
                        'german' => 'German',
                        'italian' => 'Italian',
                        'portuguese' => 'Portuguese',
                        'mandarin' => 'Mandarin',
                        'arabic' => 'Arabic',
                        'hindi' => 'Hindi',
                        'urdu' => 'Urdu',
                        'punjabi' => 'Punjabi',
                        'bengali' => 'Bengali',
                        'polish' => 'Polish',
                        'russian' => 'Russian',
                    ]),

                Tables\Filters\TernaryFilter::make('accept_new_patients')
                    ->label('Accepting New Patients')
                    ->trueLabel('Accepting')
                    ->falseLabel('Not Accepting'),

                Tables\Filters\TernaryFilter::make('home_visits_only')
                    ->label('Home Visits Only')
                    ->trueLabel('Home Visits Only')
                    ->falseLabel('Clinic or Both'),

                Tables\Filters\TernaryFilter::make('clinic_visits_only')
                    ->label('Clinic Visits Only')
                    ->trueLabel('Clinic Only')
                    ->falseLabel('Home or Both'),

                Tables\Filters\TernaryFilter::make('weekends_available')
                    ->label('Weekend Availability')
                    ->trueLabel('Available Weekends')
                    ->falseLabel('Not Available Weekends'),

                Tables\Filters\TernaryFilter::make('evenings_available')
                    ->label('Evening Availability')
                    ->trueLabel('Available Evenings')
                    ->falseLabel('Not Available Evenings'),

                Tables\Filters\Filter::make('work_start_date')
                    ->form([
                        DatePicker::make('started_from')
                            ->label('Job Start From'),
                        DatePicker::make('started_until')
                            ->label('Job Start Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['started_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('work_start_date', '>=', $date),
                            )
                            ->when(
                                $data['started_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('work_start_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label('Email Verified')
                    ->trueLabel('Verified')
                    ->falseLabel('Not Verified')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn(Builder $query) => $query->whereNull('email_verified_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('update_preferences')
                    ->label('Update Preferences')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('info')
                    ->form([
                        Select::make('preferred_gender')
                            ->label('Preferred Gender')
                            ->options([
                                'all' => 'All Genders',
                                'male' => 'Male Only',
                                'female' => 'Female Only',
                            ])
                            ->required(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('age_range_start')
                                    ->label('Minimum Age')
                                    ->numeric()
                                    ->minValue(16)
                                    ->maxValue(100)
                                    ->required(),

                                TextInput::make('age_range_end')
                                    ->label('Maximum Age')
                                    ->numeric()
                                    ->minValue(16)
                                    ->maxValue(100)
                                    ->required(),
                            ]),

                        Select::make('preferred_language')
                            ->label('Preferred Language')
                            ->options([
                                'english' => 'English',
                                'spanish' => 'Spanish',
                                'french' => 'French',
                                'german' => 'German',
                                'italian' => 'Italian',
                                'portuguese' => 'Portuguese',
                                'mandarin' => 'Mandarin',
                                'arabic' => 'Arabic',
                                'hindi' => 'Hindi',
                                'urdu' => 'Urdu',
                                'punjabi' => 'Punjabi',
                                'bengali' => 'Bengali',
                                'polish' => 'Polish',
                                'russian' => 'Russian',
                            ])
                            ->required(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('accept_new_patients')
                                    ->label('Accept New Patients'),

                                TextInput::make('max_travel_distance')
                                    ->label('Max Travel Distance (miles)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('home_visits_only')
                                    ->label('Home Visits Only'),

                                Toggle::make('clinic_visits_only')
                                    ->label('Clinic Visits Only'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Toggle::make('weekends_available')
                                    ->label('Available on Weekends'),

                                Toggle::make('evenings_available')
                                    ->label('Available in Evenings'),
                            ]),
                    ])
                    ->fillForm(fn (Therapist $record): array => [
                        'preferred_gender' => $record->preferred_gender,
                        'age_range_start' => $record->age_range_start,
                        'age_range_end' => $record->age_range_end,
                        'preferred_language' => $record->preferred_language,
                        'accept_new_patients' => $record->accept_new_patients,
                        'home_visits_only' => $record->home_visits_only,
                        'clinic_visits_only' => $record->clinic_visits_only,
                        'max_travel_distance' => $record->max_travel_distance,
                        'weekends_available' => $record->weekends_available,
                        'evenings_available' => $record->evenings_available,
                    ])
                    ->action(function (Therapist $record, array $data) {
                        $data['preferences_updated_at'] = now();
                        $record->update($data);

                        Notification::make()
                            ->title('Preferences Updated')
                            ->body("Preferences updated for {$record->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->default('password123'),
                    ])
                    ->action(function (Therapist $record, array $data) {
                        $record->update([
                            'password' => Hash::make($data['new_password']),
                            'email_verified_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Password Reset')
                            ->body("Password has been reset for {$record->name}")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reset Therapist Password')
                    ->modalDescription('This will reset the therapist\'s password and mark their email as verified.'),

                Tables\Actions\Action::make('login_as')
                    ->label('Login URL')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->action(function (Therapist $record) {
                        $url = url('/therapist/login');

                        Notification::make()
                            ->title('Login Information')
                            ->body("Email: {$record->email}\nLogin URL: {$url}")
                            ->info()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('toggle_online_status')
                        ->label('Toggle Online Status')
                        ->icon('heroicon-o-signal')
                        ->color('primary')
                        ->form([
                            Toggle::make('online_status')
                                ->label('Set Online Status')
                                ->default(true),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update([
                                    'online_status' => $data['online_status'],
                                ]);
                                $count++;
                            }

                            $status = $data['online_status'] ? 'online' : 'offline';
                            Notification::make()
                                ->title('Online Status Updated')
                                ->body("{$count} therapist(s) marked as {$status}")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('update_accept_new_patients')
                        ->label('Toggle New Patient Acceptance')
                        ->icon('heroicon-o-users')
                        ->color('info')
                        ->form([
                            Toggle::make('accept_new_patients')
                                ->label('Accept New Patients')
                                ->default(true),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update([
                                    'accept_new_patients' => $data['accept_new_patients'],
                                    'preferences_updated_at' => now(),
                                ]);
                                $count++;
                            }

                            Notification::make()
                                ->title('Preferences Updated')
                                ->body("{$count} therapist(s) updated")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('reset_passwords')
                        ->label('Reset Passwords')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->form([
                            TextInput::make('new_password')
                                ->label('New Password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->default('password123'),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update([
                                    'password' => Hash::make($data['new_password']),
                                    'email_verified_at' => now(),
                                ]);
                                $count++;
                            }

                            Notification::make()
                                ->title('Passwords Reset')
                                ->body("Passwords have been reset for {$count} therapists")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AvailabilitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTherapists::route('/'),
            'create' => Pages\CreateTherapist::route('/create'),
            'edit' => Pages\EditTherapist::route('/{record}/edit'),
        ];
    }
}