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
use Filament\Support\Enums\Alignment;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\Tabs;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\ActionGroup;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\Grid as InfoGrid;
use Illuminate\Database\Eloquent\Model;

class TherapistResource extends Resource
{
    protected static ?string $model = Therapist::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationGroup = 'Staff Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Therapists';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Therapist Management')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Forms\Components\Section::make('Personal Details')
                                    ->description('Basic personal and contact information for the therapist')
                                    ->icon('heroicon-o-identification')
                                    ->columns(3)
                                    ->schema([
                                        FileUpload::make('image')
                                            ->label('Profile Photo')
                                            ->directory('therapists')
                                            ->image()
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('400')
                                            ->imageResizeTargetHeight('400')
                                            ->optimize('webp')
                                            ->columnSpan(1)
                                            ->helperText('Upload a professional profile photo'),

                                        Forms\Components\Grid::make(1)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Full Name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->live()
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->placeholder('Enter therapist full name'),

                                                TextInput::make('email')
                                                    ->label('Email Address')
                                                    ->email()
                                                    ->required()
                                                    ->unique(ignoreRecord: true)
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-envelope')
                                                    ->placeholder('therapist@example.com'),

                                                TextInput::make('phone')
                                                    ->label('Phone Number')
                                                    ->required()
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->prefixIcon('heroicon-m-phone')
                                                    ->placeholder('+44 7XXX XXXXXX'),
                                            ])
                                            ->columnSpan(2),
                                    ]),

                                Forms\Components\Section::make('Professional Information')
                                    ->description('Professional background and biography')
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Textarea::make('bio')
                                            ->label('Professional Biography')
                                            ->maxLength(1000)
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->placeholder('Brief professional biography, qualifications, and specializations...'),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                DatePicker::make('work_start_date')
                                                    ->label('Employment Start Date')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->format('Y-m-d')
                                                    ->helperText('When did this therapist start working?')
                                                    ->prefixIcon('heroicon-m-calendar'),

                                                TextInput::make('password')
                                                    ->label('Password')
                                                    ->password()
                                                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                                                    ->dehydrated(fn($state) => filled($state))
                                                    ->required(fn(string $context): bool => $context === 'create')
                                                    ->minLength(8)
                                                    ->helperText('Leave blank to keep current password (for edit)')
                                                    ->prefixIcon('heroicon-m-key'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Account Status')
                            ->icon('heroicon-m-shield-check')
                            ->schema([
                                Forms\Components\Section::make('Account Status Management')
                                    ->description('Control account access and visibility settings')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Card::make()
                                                    ->schema([
                                                        Toggle::make('status')
                                                            ->label('Active Account')
                                                            ->helperText('Enable/disable therapist login access')
                                                            ->default(true)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                                if (!$state) {
                                                                    $set('online_status', false);
                                                                }
                                                            })
                                                            ->columnSpanFull(),

                                                        Forms\Components\Placeholder::make('status_info')
                                                            ->label('')
                                                            ->content('When disabled, therapist cannot log in to their account')
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columnSpan(1),

                                                Forms\Components\Card::make()
                                                    ->schema([
                                                        Toggle::make('online_status')
                                                            ->label('Online Status')
                                                            ->helperText('Show as available to customers')
                                                            ->default(false)
                                                            ->disabled(fn (Forms\Get $get) => !$get('status'))
                                                            ->columnSpanFull(),

                                                        Forms\Components\Placeholder::make('online_info')
                                                            ->label('')
                                                            ->content('Controls visibility to customers for booking')
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columnSpan(1),
                                            ]),

                                        Forms\Components\Grid::make(1)
                                            ->schema([
                                                Forms\Components\Card::make()
                                                    ->schema([
                                                        Toggle::make('accept_new_patients')
                                                            ->label('Accepting New Patients')
                                                            ->helperText('Allow new bookings from customers')
                                                            ->default(true)
                                                            ->columnSpanFull(),

                                                        Forms\Components\Placeholder::make('patients_info')
                                                            ->label('')
                                                            ->content('When disabled, only existing patients can book appointments')
                                                            ->columnSpanFull(),
                                                    ]),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Preferences')
                            ->icon('heroicon-m-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make('Service User Preferences')
                                    ->description('Configure preferences for the types of service users this therapist prefers to work with')
                                    ->icon('heroicon-o-users')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Select::make('preferred_gender')
                                                    ->label('Preferred Client Gender')
                                                    ->options([
                                                        'all' => 'All Genders',
                                                        'male' => 'Male Clients Only',
                                                        'female' => 'Female Clients Only',
                                                    ])
                                                    ->default('all')
                                                    ->native(false)
                                                    ->helperText('Which gender does this therapist prefer to work with?'),

                                                TextInput::make('age_range_start')
                                                    ->label('Minimum Age')
                                                    ->numeric()
                                                    ->default(18)
                                                    ->minValue(16)
                                                    ->maxValue(100)
                                                    ->suffix('years')
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                        $endAge = $get('age_range_end');
                                                        if ($state && $endAge && $state >= $endAge) {
                                                            $set('age_range_end', $state + 1);
                                                        }
                                                    }),

                                                TextInput::make('age_range_end')
                                                    ->label('Maximum Age')
                                                    ->numeric()
                                                    ->default(65)
                                                    ->minValue(17)
                                                    ->maxValue(100)
                                                    ->suffix('years')
                                                    ->gte('age_range_start'),
                                            ]),

                                        Select::make('preferred_language')
                                            ->label('Primary Communication Language')
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
                                            ->native(false)
                                            ->helperText('Primary language for client communication'),
                                    ]),

                                Forms\Components\Section::make('Service Delivery Preferences')
                                    ->description('Configure how this therapist prefers to deliver their services')
                                    ->icon('heroicon-o-truck')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                TextInput::make('max_travel_distance')
                                                    ->label('Maximum Travel Distance')
                                                    ->numeric()
                                                    ->default(10)
                                                    ->minValue(1)
                                                    ->maxValue(50)
                                                    ->suffix('miles')
                                                    ->helperText('Maximum distance willing to travel for home visits'),

                                                Forms\Components\Placeholder::make('delivery_info')
                                                    ->label('Service Delivery Options')
                                                    ->content('Configure below which types of appointments this therapist can provide'),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Toggle::make('home_visits_only')
                                                    ->label('Home Visits Only')
                                                    ->default(false)
                                                    ->helperText('Only provides home visits')
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                        if ($state) {
                                                            $set('clinic_visits_only', false);
                                                        }
                                                    }),

                                                Toggle::make('clinic_visits_only')
                                                    ->label('Clinic Visits Only')
                                                    ->default(false)
                                                    ->helperText('Only provides clinic-based services')
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                        if ($state) {
                                                            $set('home_visits_only', false);
                                                        }
                                                    }),
                                            ]),

                                        Forms\Components\Fieldset::make('Extended Availability Options')
                                            ->schema([
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Toggle::make('weekends_available')
                                                            ->label('Weekend Availability')
                                                            ->default(false)
                                                            ->helperText('Available on Saturdays and Sundays'),

                                                        Toggle::make('evenings_available')
                                                            ->label('Evening Availability')
                                                            ->default(false)
                                                            ->helperText('Available after 6:00 PM'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Assignments')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Forms\Components\Section::make('Location & Service Assignments')
                                    ->description('Assign therapist to locations and services they can provide')
                                    ->icon('heroicon-o-map-pin')
                                    ->schema([
                                        Select::make('locations')
                                            ->relationship('locations', 'name')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->required()
                                            ->helperText('Select all locations where this therapist can work')
                                            ->columnSpanFull(),

                                        Select::make('services')
                                            ->relationship('services', 'title')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->required()
                                            ->helperText('Select all services this therapist can provide')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->circular()
                    ->size(45)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('name')
                    ->label('Therapist')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Therapist $record): ?string => $record->email)
                    ->wrap(),

                TextColumn::make('phone')
                    ->label('Contact')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-phone')
                    ->color('gray')
                    ->size('sm'),

                TextColumn::make('account_status')
                    ->label('Account Status')
                    ->state(function (Therapist $record): string {
                        if (!$record->status) {
                            return 'Inactive';
                        }
                        
                        if (!$record->email_verified_at) {
                            return 'Unverified';
                        }
                        
                        return 'Active';
                    })
                    ->badge()
                    ->color(function (Therapist $record): string {
                        if (!$record->status) {
                            return 'danger';
                        }
                        
                        if (!$record->email_verified_at) {
                            return 'warning';
                        }
                        
                        return 'success';
                    }),

                TextColumn::make('availability_status')
                    ->label('Availability')
                    ->state(function (Therapist $record): string {
                        if (!$record->status) {
                            return 'Account Disabled';
                        }
                        
                        if (!$record->accept_new_patients) {
                            return 'Not Accepting Patients';
                        }
                        
                        if ($record->online_status) {
                            return 'Online & Available';
                        }
                        
                        return 'Offline';
                    })
                    ->badge()
                    ->color(function (Therapist $record): string {
                        if (!$record->status) {
                            return 'danger';
                        }
                        
                        if (!$record->accept_new_patients) {
                            return 'warning';
                        }
                        
                        if ($record->online_status) {
                            return 'success';
                        }
                        
                        return 'gray';
                    }),

                TextColumn::make('work_experience')
                    ->label('Experience')
                    ->state(function (Therapist $record): string {
                        if (!$record->work_start_date) {
                            return 'Not Set';
                        }

                        $startDate = \Carbon\Carbon::parse($record->work_start_date);

                        if ($startDate->isFuture()) {
                            return 'Starts ' . $startDate->format('M d');
                        }

                        if ($startDate->isToday()) {
                            return 'Starting Today';
                        }

                        $years = $startDate->diffInYears(\Carbon\Carbon::now());
                        $months = $startDate->diffInMonths(\Carbon\Carbon::now()) % 12;
                        
                        if ($years > 0) {
                            return $months > 0 ? "{$years}y {$months}m" : "{$years}y";
                        }
                        
                        return $months > 0 ? "{$months}m" : "New";
                    })
                    ->badge()
                    ->color(function (Therapist $record): string {
                        if (!$record->work_start_date) {
                            return 'gray';
                        }

                        $startDate = \Carbon\Carbon::parse($record->work_start_date);
                        $years = $startDate->diffInYears(\Carbon\Carbon::now());
                        
                        if ($years >= 3) return 'success';
                        if ($years >= 1) return 'primary';
                        return 'info';
                    })
                    ->sortable('work_start_date'),

                TextColumn::make('client_preferences')
                    ->label('Preferences')
                    ->state(function (Therapist $record): string {
                        $gender = match($record->preferred_gender) {
                            'all' => 'All',
                            'male' => 'Male',
                            'female' => 'Female',
                            default => 'All',
                        };
                        
                        return "{$gender} • {$record->age_range_start}-{$record->age_range_end}y";
                    })
                    ->size('sm')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('service_delivery')
                    ->label('Service Type')
                    ->state(function (Therapist $record): string {
                        if ($record->home_visits_only) return 'Home Only';
                        if ($record->clinic_visits_only) return 'Clinic Only';
                        return 'Both';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Home Only' => 'info',
                        'Clinic Only' => 'warning',
                        'Both' => 'success',
                        default => 'gray',
                    })
                    ->size('sm'),

                TextColumn::make('extended_availability')
                    ->label('Extended Hours')
                    ->state(function (Therapist $record): string {
                        $flags = [];
                        if ($record->weekends_available) $flags[] = 'Weekends';
                        if ($record->evenings_available) $flags[] = 'Evenings';
                        
                        return empty($flags) ? 'Standard' : implode(' • ', $flags);
                    })
                    ->badge()
                    ->color(function (Therapist $record): string {
                        $hasExtended = $record->weekends_available || $record->evenings_available;
                        return $hasExtended ? 'primary' : 'gray';
                    })
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('locations_count')
                    ->label('Locations')
                    ->counts('locations')
                    ->badge()
                    ->color('primary')
                    ->suffix(fn ($state) => $state == 1 ? ' loc' : ' locs'),

                TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->badge()
                    ->color('success')
                    ->suffix(fn ($state) => $state == 1 ? ' svc' : ' svcs'),

                TextColumn::make('availabilities_count')
                    ->label('Schedule')
                    ->counts('availabilities')
                    ->badge()
                    ->color('info')
                    ->suffix(' slots')
                    ->toggleable(),

                TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('M d, H:i')
                    ->placeholder('Never')
                    ->size('sm')
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M d, Y')
                    ->size('sm')
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Primary Status Filters
                Tables\Filters\SelectFilter::make('account_status')
                    ->label('Account Status')
                    ->options([
                        'active' => 'Active Accounts',
                        'inactive' => 'Inactive Accounts', 
                        'unverified' => 'Unverified Email',
                        'online' => 'Currently Online',
                        'accepting' => 'Accepting New Patients',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            function (Builder $query, $value) {
                                match($value) {
                                    'active' => $query->where('status', true),
                                    'inactive' => $query->where('status', false),
                                    'unverified' => $query->whereNull('email_verified_at'),
                                    'online' => $query->where('online_status', true)->where('status', true),
                                    'accepting' => $query->where('accept_new_patients', true)->where('status', true),
                                };
                            }
                        );
                    }),

                Tables\Filters\TernaryFilter::make('status')
                    ->label('Active Status')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->nullable(),

                Tables\Filters\TernaryFilter::make('online_status')
                    ->label('Online Status')
                    ->trueLabel('Online Only')
                    ->falseLabel('Offline Only')
                    ->nullable(),

                Tables\Filters\TernaryFilter::make('accept_new_patients')
                    ->label('Patient Acceptance')
                    ->trueLabel('Accepting New Patients')
                    ->falseLabel('Not Accepting')
                    ->nullable(),

                // Preference Filters
                Tables\Filters\SelectFilter::make('preferred_gender')
                    ->label('Client Gender Preference')
                    ->options([
                        'all' => 'All Genders',
                        'male' => 'Male Only',
                        'female' => 'Female Only',
                    ]),

                Tables\Filters\SelectFilter::make('preferred_language')
                    ->label('Communication Language')
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
                    ->multiple(),

                // Service Delivery Filters
                Tables\Filters\Filter::make('service_delivery_type')
                    ->label('Service Delivery')
                    ->form([
                        Forms\Components\CheckboxList::make('types')
                            ->options([
                                'home_only' => 'Home Visits Only',
                                'clinic_only' => 'Clinic Visits Only',
                                'both' => 'Both Home & Clinic',
                            ])
                            ->columns(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['types'],
                            function (Builder $query, $types) {
                                $query->where(function (Builder $q) use ($types) {
                                    if (in_array('home_only', $types)) {
                                        $q->orWhere('home_visits_only', true);
                                    }
                                    if (in_array('clinic_only', $types)) {
                                        $q->orWhere('clinic_visits_only', true);
                                    }
                                    if (in_array('both', $types)) {
                                        $q->orWhere(function (Builder $subQ) {
                                            $subQ->where('home_visits_only', false)
                                                 ->where('clinic_visits_only', false);
                                        });
                                    }
                                });
                            }
                        );
                    }),

                Tables\Filters\TernaryFilter::make('weekends_available')
                    ->label('Weekend Availability')
                    ->trueLabel('Available Weekends')
                    ->falseLabel('Not Available Weekends'),

                Tables\Filters\TernaryFilter::make('evenings_available')
                    ->label('Evening Availability') 
                    ->trueLabel('Available Evenings')
                    ->falseLabel('Not Available Evenings'),

                // Date Range Filter
                Tables\Filters\Filter::make('employment_period')
                    ->form([
                        DatePicker::make('employed_from')
                            ->label('Employed From'),
                        DatePicker::make('employed_until')
                            ->label('Employed Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['employed_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('work_start_date', '>=', $date),
                            )
                            ->when(
                                $data['employed_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('work_start_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->color('warning'),
                    
                    // Quick Status Actions
                    Tables\Actions\Action::make('toggle_status')
                        ->label(fn (Therapist $record) => $record->status ? 'Deactivate' : 'Activate')
                        ->icon(fn (Therapist $record) => $record->status ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn (Therapist $record) => $record->status ? 'danger' : 'success')
                        ->action(function (Therapist $record) {
                            $newStatus = !$record->status;
                            $record->update([
                                'status' => $newStatus,
                                'online_status' => $newStatus ? $record->online_status : false,
                            ]);

                            Notification::make()
                                ->title('Status Updated')
                                ->body("{$record->name}'s account has been " . ($newStatus ? 'activated' : 'deactivated'))
                                ->color($newStatus ? 'success' : 'warning')
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\Action::make('toggle_online')
                        ->label(fn (Therapist $record) => $record->online_status ? 'Set Offline' : 'Set Online')
                        ->icon(fn (Therapist $record) => $record->online_status ? 'heroicon-o-signal-slash' : 'heroicon-o-signal')
                        ->color(fn (Therapist $record) => $record->online_status ? 'warning' : 'success')
                        ->visible(fn (Therapist $record) => $record->status)
                        ->action(function (Therapist $record) {
                            $newStatus = !$record->online_status;
                            $record->update(['online_status' => $newStatus]);

                            Notification::make()
                                ->title('Online Status Updated')
                                ->body("{$record->name} is now " . ($newStatus ? 'online' : 'offline'))
                                ->color($newStatus ? 'success' : 'info')
                                ->send();
                        }),

                    Tables\Actions\Action::make('toggle_patients')
                        ->label(fn (Therapist $record) => $record->accept_new_patients ? 'Stop New Patients' : 'Accept Patients')
                        ->icon(fn (Therapist $record) => $record->accept_new_patients ? 'heroicon-o-user-minus' : 'heroicon-o-user-plus')
                        ->color(fn (Therapist $record) => $record->accept_new_patients ? 'warning' : 'success')
                        ->action(function (Therapist $record) {
                            $newStatus = !$record->accept_new_patients;
                            $record->update([
                                'accept_new_patients' => $newStatus,
                                'preferences_updated_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Patient Acceptance Updated')
                                ->body("{$record->name} is " . ($newStatus ? 'now accepting' : 'no longer accepting') . ' new patients')
                                ->color($newStatus ? 'success' : 'warning')
                                ->send();
                        }),

                    Tables\Actions\Action::make('quick_preferences')
                        ->label('Update Preferences')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->color('info')
                        ->form([
                            Forms\Components\Section::make('Quick Preference Update')
                                ->schema([
                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Select::make('preferred_gender')
                                                ->label('Preferred Gender')
                                                ->options([
                                                    'all' => 'All Genders',
                                                    'male' => 'Male Only',
                                                    'female' => 'Female Only',
                                                ])
                                                ->required()
                                                ->native(false),

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
                                                ->required()
                                                ->native(false)
                                                ->searchable(),
                                        ]),

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

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            TextInput::make('max_travel_distance')
                                                ->label('Max Travel Distance (miles)')
                                                ->numeric()
                                                ->minValue(1)
                                                ->maxValue(50)
                                                ->required(),

                                            Toggle::make('accept_new_patients')
                                                ->label('Accept New Patients'),
                                        ]),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Toggle::make('weekends_available')
                                                ->label('Available on Weekends'),

                                            Toggle::make('evenings_available')
                                                ->label('Available in Evenings'),
                                        ]),
                                ]),
                        ])
                        ->fillForm(fn (Therapist $record): array => [
                            'preferred_gender' => $record->preferred_gender,
                            'age_range_start' => $record->age_range_start,
                            'age_range_end' => $record->age_range_end,
                            'preferred_language' => $record->preferred_language,
                            'accept_new_patients' => $record->accept_new_patients,
                            'max_travel_distance' => $record->max_travel_distance,
                            'weekends_available' => $record->weekends_available,
                            'evenings_available' => $record->evenings_available,
                        ])
                        ->action(function (Therapist $record, array $data) {
                            $data['preferences_updated_at'] = now();
                            $record->update($data);

                            Notification::make()
                                ->title('Preferences Updated')
                                ->body("Preferences updated successfully for {$record->name}")
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
                                ->default('password123')
                                ->helperText('Therapist should change this on first login'),

                            Toggle::make('mark_verified')
                                ->label('Mark Email as Verified')
                                ->default(true)
                                ->helperText('Automatically verify email with password reset'),
                        ])
                        ->action(function (Therapist $record, array $data) {
                            $updateData = [
                                'password' => Hash::make($data['new_password']),
                            ];

                            if ($data['mark_verified']) {
                                $updateData['email_verified_at'] = now();
                            }

                            $record->update($updateData);

                            Notification::make()
                                ->title('Password Reset')
                                ->body("Password has been reset for {$record->name}")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Reset Therapist Password')
                        ->modalDescription('This will reset the password and optionally verify the email address.'),

                    Tables\Actions\Action::make('login_info')
                        ->label('Login Info')
                        ->icon('heroicon-o-information-circle')
                        ->color('gray')
                        ->action(function (Therapist $record) {
                            $loginUrl = url('/therapist/login');

                            Notification::make()
                                ->title('Login Information')
                                ->body("Email: {$record->email}\nLogin URL: {$loginUrl}")
                                ->info()
                                ->persistent()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Status Management
                    Tables\Actions\BulkAction::make('activate_selected')
                        ->label('Activate Accounts')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['status' => true]);
                                $count++;
                            }

                            Notification::make()
                                ->title('Accounts Activated')
                                ->body("{$count} therapist account(s) have been activated")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate_selected')
                        ->label('Deactivate Accounts')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => false,
                                    'online_status' => false,
                                ]);
                                $count++;
                            }

                            Notification::make()
                                ->title('Accounts Deactivated')
                                ->body("{$count} therapist account(s) have been deactivated")
                                ->warning()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    // Online Status Management
                    Tables\Actions\BulkAction::make('set_online_status')
                        ->label('Set Online Status')
                        ->icon('heroicon-o-signal')
                        ->color('primary')
                        ->form([
                            Toggle::make('online_status')
                                ->label('Set Online Status')
                                ->default(true)
                                ->helperText('Only affects active accounts'),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status) { // Only update if account is active
                                    $record->update(['online_status' => $data['online_status']]);
                                    $count++;
                                }
                            }

                            $status = $data['online_status'] ? 'online' : 'offline';
                            Notification::make()
                                ->title('Online Status Updated')
                                ->body("{$count} active therapist(s) set to {$status}")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Patient Acceptance
                    Tables\Actions\BulkAction::make('set_patient_acceptance')
                        ->label('Set Patient Acceptance')
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

                            $status = $data['accept_new_patients'] ? 'accepting' : 'not accepting';
                            Notification::make()
                                ->title('Patient Acceptance Updated')
                                ->body("{$count} therapist(s) are now {$status} new patients")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Bulk Password Reset
                    Tables\Actions\BulkAction::make('reset_passwords')
                        ->label('Reset Passwords')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->form([
                            TextInput::make('new_password')
                                ->label('New Password for All Selected')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->default('password123')
                                ->helperText('Same password will be set for all selected therapists'),

                            Toggle::make('mark_verified')
                                ->label('Mark Emails as Verified')
                                ->default(true),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                $updateData = [
                                    'password' => Hash::make($data['new_password']),
                                ];

                                if ($data['mark_verified']) {
                                    $updateData['email_verified_at'] = now();
                                }

                                $record->update($updateData);
                                $count++;
                            }

                            Notification::make()
                                ->title('Passwords Reset')
                                ->body("Passwords have been reset for {$count} therapist(s)")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    // Bulk Availability Update
                    Tables\Actions\BulkAction::make('update_availability')
                        ->label('Update Availability')
                        ->icon('heroicon-o-clock')
                        ->color('secondary')
                        ->form([
                            Forms\Components\Section::make('Bulk Availability Update')
                                ->schema([
                                    Toggle::make('weekends_available')
                                        ->label('Weekend Availability'),

                                    Toggle::make('evenings_available')
                                        ->label('Evening Availability'),

                                    TextInput::make('max_travel_distance')
                                        ->label('Maximum Travel Distance (miles)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(50)
                                        ->placeholder('Leave empty to keep current'),
                                ]),
                        ])
                        ->action(function ($records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                $updateData = [
                                    'preferences_updated_at' => now(),
                                ];

                                if (isset($data['weekends_available'])) {
                                    $updateData['weekends_available'] = $data['weekends_available'];
                                }

                                if (isset($data['evenings_available'])) {
                                    $updateData['evenings_available'] = $data['evenings_available'];
                                }

                                if (!empty($data['max_travel_distance'])) {
                                    $updateData['max_travel_distance'] = $data['max_travel_distance'];
                                }

                                $record->update($updateData);
                                $count++;
                            }

                            Notification::make()
                                ->title('Availability Updated')
                                ->body("Availability preferences updated for {$count} therapist(s)")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add First Therapist')
                    ->icon('heroicon-m-plus'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // Auto-refresh every 30 seconds for real-time status
            ->striped()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferLoading()
            ->paginated([10, 25, 50, 100]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Therapist Overview')
                    ->schema([
                        InfoGrid::make(3)
                            ->schema([
                                ImageEntry::make('image')
                                    ->circular()
                                    ->size(100),

                                InfoGrid::make(1)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Full Name')
                                            ->size('lg')
                                            ->weight('bold'),

                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->copyable()
                                            ->icon('heroicon-m-envelope'),

                                        TextEntry::make('phone')
                                            ->label('Phone')
                                            ->copyable()
                                            ->icon('heroicon-m-phone'),
                                    ])
                                    ->columnSpan(2),
                            ]),

                        TextEntry::make('bio')
                            ->label('Biography')
                            ->placeholder('No biography provided')
                            ->columnSpanFull(),
                    ]),

                InfoSection::make('Account Status')
                    ->schema([
                        InfoGrid::make(4)
                            ->schema([
                                IconEntry::make('status')
                                    ->label('Account Active')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                IconEntry::make('online_status')
                                    ->label('Online Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-signal')
                                    ->falseIcon('heroicon-o-signal-slash')
                                    ->trueColor('success')
                                    ->falseColor('gray'),

                                IconEntry::make('accept_new_patients')
                                    ->label('Accepting Patients')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-user-plus')
                                    ->falseIcon('heroicon-o-user-minus')
                                    ->trueColor('success')
                                    ->falseColor('warning'),

                                IconEntry::make('email_verified_at')
                                    ->label('Email Verified')
                                    ->boolean()
                                    ->getStateUsing(fn ($record) => !is_null($record->email_verified_at))
                                    ->trueIcon('heroicon-o-check-badge')
                                    ->falseIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('success')
                                    ->falseColor('warning'),
                            ]),
                    ]),

                InfoSection::make('Professional Information')
                    ->schema([
                        InfoGrid::make(2)
                            ->schema([
                                TextEntry::make('work_start_date')
                                    ->label('Employment Start Date')
                                    ->date('M d, Y')
                                    ->placeholder('Not set'),

                                TextEntry::make('years_experience')
                                    ->label('Years of Experience')
                                    ->getStateUsing(function ($record) {
                                        if (!$record->work_start_date) return 'Not set';
                                        
                                        $years = \Carbon\Carbon::parse($record->work_start_date)
                                            ->diffInYears(\Carbon\Carbon::now());
                                        
                                        return $years > 0 ? "{$years} years" : "Less than 1 year";
                                    }),

                                TextEntry::make('last_login_at')
                                    ->label('Last Login')
                                    ->dateTime('M d, Y H:i')
                                    ->placeholder('Never logged in'),

                                TextEntry::make('created_at')
                                    ->label('Account Created')
                                    ->dateTime('M d, Y H:i'),
                            ]),
                    ]),

                InfoSection::make('Preferences & Settings')
                    ->schema([
                        InfoGrid::make(3)
                            ->schema([
                                TextEntry::make('preferred_gender')
                                    ->label('Preferred Client Gender')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'all' => 'All Genders',
                                        'male' => 'Male Only',
                                        'female' => 'Female Only',
                                        default => $state,
                                    })
                                    ->badge()
                                    ->color('primary'),

                                TextEntry::make('age_range')
                                    ->label('Age Range Preference')
                                    ->getStateUsing(fn ($record) => "{$record->age_range_start} - {$record->age_range_end} years")
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('preferred_language')
                                    ->label('Communication Language')
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                    ->badge()
                                    ->color('secondary'),
                            ]),

                        InfoGrid::make(4)
                            ->schema([
                                TextEntry::make('max_travel_distance')
                                    ->label('Max Travel Distance')
                                    ->suffix(' miles')
                                    ->badge(),

                                IconEntry::make('weekends_available')
                                    ->label('Weekend Availability')
                                    ->boolean()
                                    ->trueColor('success')
                                    ->falseColor('gray'),

                                IconEntry::make('evenings_available')
                                    ->label('Evening Availability')
                                    ->boolean()
                                    ->trueColor('success')
                                    ->falseColor('gray'),

                                TextEntry::make('service_delivery_type')
                                    ->label('Service Delivery')
                                    ->getStateUsing(function ($record) {
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
                                    }),
                            ]),

                        TextEntry::make('preferences_updated_at')
                            ->label('Preferences Last Updated')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('Never updated'),
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

    public static function getNavigationBadge(): ?string
    {
        $activeCount = static::getModel()::where('status', true)->count();
        $totalCount = static::getModel()::count();
        
        return "{$activeCount}/{$totalCount}";
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Phone' => $record->phone,
            'Status' => $record->status ? 'Active' : 'Inactive',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }
}