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

                        Forms\Components\Grid::make(2)
                            ->schema([
                                DatePicker::make('work_start_date')
                                    ->label('Job Start Date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->format('Y-m-d')
                                    ->helperText('Select when the therapist starts/started this job'),

                                Toggle::make('status')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),

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
