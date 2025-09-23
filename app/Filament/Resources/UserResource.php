<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\TreatmentHistoryRelationManager;
use App\Models\User;
use App\Models\TreatmentHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\ActionGroup;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('User Information')
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true),
                        ]),
                    
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required(fn (string $context): bool => $context === 'create')
                        ->minLength(8)
                        ->maxLength(255)
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Leave blank to keep current password'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('treatment_histories_count')
                    ->label('Treatments')
                    ->getStateUsing(function (User $record): int {
                        return $record->treatmentHistories()->count();
                    })
                    ->badge()
                    ->color('success')
                    ->sortable(false),
                    
                Tables\Columns\TextColumn::make('total_invested')
                    ->label('Invested')
                    ->money('GBP')
                    ->sortable()
                    ->color('warning')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('latest_treatment')
                    ->label('Latest Treatment')
                    ->getStateUsing(function (User $record): ?string {
                        $latest = $record->treatmentHistories()
                            ->latest('treatment_completed_at')
                            ->first();
                        return $latest ? $latest->treatment_completed_at->diffForHumans() : 'Never';
                    })
                    ->color('info')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_bookings')
                    ->label('Has Bookings')
                    ->query(fn (Builder $query): Builder => $query->whereHas('bookings')),
                    
                Tables\Filters\Filter::make('has_treatment_history')
                    ->label('Has Treatment History')
                    ->query(fn (Builder $query): Builder => $query->whereHas('treatmentHistories')),
                    
                Tables\Filters\Filter::make('recent_patients')
                    ->label('Recent Patients (30 days)')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('treatmentHistories', function (Builder $subQuery) {
                            $subQuery->where('treatment_completed_at', '>=', now()->subDays(30));
                        })
                    ),
                    
                Tables\Filters\Filter::make('improved_condition')
                    ->label('Has Improved')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('treatmentHistories', function (Builder $subQuery) {
                            $subQuery->where('patient_condition', 'improved');
                        })
                    ),
                    
                Tables\Filters\SelectFilter::make('treatment_count')
                    ->label('Treatment Count')
                    ->options([
                        '1-3' => '1-3 treatments',
                        '4-10' => '4-10 treatments',
                        '10+' => '10+ treatments',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            function (Builder $query, $value) {
                                switch ($value) {
                                    case '1-3':
                                        return $query->whereHas('treatmentHistories', function (Builder $subQuery) {
                                            $subQuery->havingRaw('COUNT(*) BETWEEN 1 AND 3');
                                        });
                                    case '4-10':
                                        return $query->whereHas('treatmentHistories', function (Builder $subQuery) {
                                            $subQuery->havingRaw('COUNT(*) BETWEEN 4 AND 10');
                                        });
                                    case '10+':
                                        return $query->whereHas('treatmentHistories', function (Builder $subQuery) {
                                            $subQuery->havingRaw('COUNT(*) > 10');
                                        });
                                }
                            }
                        );
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\Action::make('view_treatment_history')
                        ->label('Treatment History')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->modalWidth('7xl')
                        ->modalContent(function (User $record) {
                            $treatmentHistories = $record->treatmentHistories()
                                ->with(['booking', 'therapist', 'service'])
                                ->orderBy('treatment_completed_at', 'desc')
                                ->get();
                                
                            return view('filament.admin.user-treatment-history', [
                                'user' => $record,
                                'treatmentHistories' => $treatmentHistories
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->visible(function (User $record): bool {
                            return $record->treatmentHistories()->exists();
                        }),

                    Tables\Actions\Action::make('export_treatment_history')
                        ->label('Export History')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (User $record) {
                            $histories = $record->treatmentHistories()
                                ->with(['booking', 'therapist', 'service'])
                                ->orderBy('treatment_completed_at', 'desc')
                                ->get();

                            return response()->streamDownload(function () use ($histories, $record) {
                                $csv = fopen('php://output', 'w');
                                
                                // UTF-8 BOM for proper Excel encoding
                                fwrite($csv, "\xEF\xBB\xBF");
                                
                                // Headers
                                fputcsv($csv, [
                                    'Date',
                                    'Service',
                                    'Therapist',
                                    'Patient Condition',
                                    'Pain Before',
                                    'Pain After',
                                    'Areas Treated',
                                    'Treatment Notes',
                                    'Observations',
                                    'Recommendations',
                                    'Next Treatment Plan',
                                    'Booking Reference',
                                    'Is Editable'
                                ]);
                                
                                // Data
                                foreach ($histories as $history) {
                                    fputcsv($csv, [
                                        $history->treatment_completed_at->format('Y-m-d H:i'),
                                        $history->service->title ?? '',
                                        $history->therapist->name ?? '',
                                        $history->patient_condition ?? '',
                                        $history->pain_level_before ?? '',
                                        $history->pain_level_after ?? '',
                                        $history->areas_treated ? implode(', ', $history->areas_treated) : '',
                                        $history->treatment_notes ?? '',
                                        $history->observations ?? '',
                                        $history->recommendations ?? '',
                                        $history->next_treatment_plan ?? '',
                                        $history->booking->reference ?? '',
                                        $history->is_editable ? 'Yes' : 'No'
                                    ]);
                                }
                                
                                fclose($csv);
                            }, "treatment-history-{$record->name}-" . now()->format('Y-m-d') . '.csv');
                        })
                        ->visible(function (User $record): bool {
                            return $record->treatmentHistories()->exists();
                        }),

                    Tables\Actions\Action::make('treatment_summary')
                        ->label('Treatment Summary')
                        ->icon('heroicon-o-chart-bar-square')
                        ->color('warning')
                        ->modalContent(function (User $record) {
                            $stats = $record->treatment_stats;
                            $mostTreatedAreas = $record->most_treated_areas;
                            
                            return view('filament.admin.user-treatment-summary', [
                                'user' => $record,
                                'stats' => $stats,
                                'mostTreatedAreas' => $mostTreatedAreas
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->visible(function (User $record): bool {
                            return $record->treatmentHistories()->exists();
                        }),

                    Tables\Actions\Action::make('view_bookings')
                        ->label('View Bookings')
                        ->icon('heroicon-o-calendar-days')
                        ->color('primary')
                        ->url(fn (User $record): string => "/admin/bookings?tableFilters[user_id][value]={$record->id}")
                        ->openUrlInNewTab()
                        ->visible(fn (User $record): bool => $record->bookings()->exists()),

                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete User Account')
                        ->modalDescription('Are you sure you want to delete this user? This will also delete all their bookings and treatment history.')
                        ->modalSubmitActionLabel('Yes, delete user'),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size('sm')
                ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('export_all_histories')
                        ->label('Export All Treatment Histories')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function ($records) {
                            $allHistories = TreatmentHistory::whereHas('booking', function (Builder $query) use ($records) {
                                $query->whereIn('user_id', $records->pluck('id'));
                            })
                            ->with(['booking.user', 'therapist', 'service'])
                            ->orderBy('treatment_completed_at', 'desc')
                            ->get();

                            return response()->streamDownload(function () use ($allHistories) {
                                $csv = fopen('php://output', 'w');
                                
                                // UTF-8 BOM
                                fwrite($csv, "\xEF\xBB\xBF");
                                
                                // Headers
                                fputcsv($csv, [
                                    'Patient Name',
                                    'Patient Email',
                                    'Date',
                                    'Service',
                                    'Therapist',
                                    'Patient Condition',
                                    'Pain Before',
                                    'Pain After',
                                    'Areas Treated',
                                    'Treatment Notes',
                                    'Booking Reference'
                                ]);
                                
                                foreach ($allHistories as $history) {
                                    fputcsv($csv, [
                                        $history->booking->user->name ?? $history->patient_name,
                                        $history->booking->user->email ?? '',
                                        $history->treatment_completed_at->format('Y-m-d H:i'),
                                        $history->service->title ?? '',
                                        $history->therapist->name ?? '',
                                        $history->patient_condition ?? '',
                                        $history->pain_level_before ?? '',
                                        $history->pain_level_after ?? '',
                                        $history->areas_treated ? implode(', ', $history->areas_treated) : '',
                                        $history->treatment_notes ?? '',
                                        $history->booking->reference ?? ''
                                    ]);
                                }
                                
                                fclose($csv);
                            }, 'bulk-treatment-histories-' . now()->format('Y-m-d') . '.csv');
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            TreatmentHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}