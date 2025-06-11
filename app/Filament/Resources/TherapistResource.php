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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TherapistResource extends Resource
{
    protected static ?string $model = Therapist::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('locations')
                    ->relationship('locations', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('phone')
                    ->required()
                    ->tel()
                    ->maxLength(255),

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

                DatePicker::make('work_start_date')
                    ->label('Job Start Date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->helperText('Select when the therapist starts/started this job'),

                Toggle::make('status')
                    ->label('Active')
                    ->default(true),

                Select::make('services')
                    ->relationship('services', 'title')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),
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
                    ->sortable(),

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
                            return 'warning'; // Future start date
                        }

                        if ($startDate->isToday()) {
                            return 'info'; // Starting today
                        }

                        return 'success'; // Currently active
                    })
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('services.title')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('availabilities_count')
                    ->label('Availability Slots')
                    ->counts('availabilities')
                    ->badge()
                    ->color('success'),

                TextColumn::make('available_days')
                    ->label('Available Days')
                    ->state(function (Therapist $record): string {
                        $days = $record->availabilities()
                            ->where('is_active', true)
                            ->distinct('day_of_week')
                            ->pluck('day_of_week')
                            ->map(fn($day) => substr(ucfirst($day), 0, 3))
                            ->toArray();

                        return implode(', ', $days);
                    })
                    ->badge()
                    ->color('info'),

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

                Tables\Filters\SelectFilter::make('work_status')
                    ->label('Work Status')
                    ->options([
                        'future' => 'Future Start Date',
                        'today' => 'Starting Today',
                        'active' => 'Currently Active',
                        'no_date' => 'No Start Date',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value']) {
                            return $query;
                        }

                        $status = $data['value'];
                        $now = now();

                        return match ($status) {
                            'future' => $query->where('work_start_date', '>', $now),
                            'today' => $query->whereDate('work_start_date', $now->toDateString()),
                            'active' => $query->where('work_start_date', '<=', $now)->whereNotNull('work_start_date'),
                            'no_date' => $query->whereNull('work_start_date'),
                            default => $query,
                        };
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
