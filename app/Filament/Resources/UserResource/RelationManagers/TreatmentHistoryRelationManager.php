<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\TreatmentHistory;

class TreatmentHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentHistories';

    protected static ?string $title = 'Treatment History';

    protected static ?string $recordTitleAttribute = 'patient_name';

    // Custom query to get treatment histories through bookings
    public function getEloquentQuery(): Builder
    {
        $owner = $this->getOwnerRecord();
        
        return TreatmentHistory::query()
            ->whereHas('booking', function (Builder $query) use ($owner) {
                $query->where('user_id', $owner->id);
            })
            ->with(['booking', 'therapist', 'service']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Treatment Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('booking_id')
                                    ->label('Booking')
                                    ->relationship('booking', 'reference')
                                    ->searchable()
                                    ->preload()
                                    ->disabled(),
                                    
                                Forms\Components\Select::make('therapist_id')
                                    ->label('Therapist')
                                    ->relationship('therapist', 'name')
                                    ->searchable()
                                    ->disabled(),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('service_id')
                                    ->label('Service')
                                    ->relationship('service', 'title')
                                    ->searchable()
                                    ->disabled(),
                                    
                                Forms\Components\TextInput::make('patient_name')
                                    ->disabled(),
                            ]),
                    ]),

                Forms\Components\Section::make('Treatment Information')
                    ->schema([
                        Forms\Components\Textarea::make('treatment_notes')
                            ->rows(3)
                            ->disabled(),
                            
                        Forms\Components\Textarea::make('observations')
                            ->rows(3)
                            ->disabled(),
                            
                        Forms\Components\Textarea::make('recommendations')
                            ->rows(3)
                            ->disabled(),
                            
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('patient_condition')
                                    ->options([
                                        'improved' => 'Improved',
                                        'same' => 'Same',
                                        'worse' => 'Worse',
                                    ])
                                    ->disabled(),
                                    
                                Forms\Components\TextInput::make('pain_level_before')
                                    ->label('Pain Level Before (1-10)')
                                    ->numeric()
                                    ->disabled(),
                                    
                                Forms\Components\TextInput::make('pain_level_after')
                                    ->label('Pain Level After (1-10)')
                                    ->numeric()
                                    ->disabled(),
                            ]),
                            
                        Forms\Components\TagsInput::make('areas_treated')
                            ->disabled(),
                            
                        Forms\Components\Textarea::make('next_treatment_plan')
                            ->rows(2)
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('treatment_completed_at')
                                    ->disabled(),
                                    
                                Forms\Components\DateTimePicker::make('edit_deadline_at')
                                    ->disabled(),
                            ]),
                            
                        Forms\Components\Toggle::make('is_editable')
                            ->disabled(),
                    ])
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('treatment_completed_at')
                    ->label('Date')
                    ->dateTime('M j, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('service.title')
                    ->label('Service')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('patient_condition')
                    ->label('Condition')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'improved' => 'success',
                        'same' => 'warning',
                        'worse' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('pain_levels')
                    ->label('Pain Level')
                    ->state(function (TreatmentHistory $record): string {
                        $before = $record->pain_level_before ? $record->pain_level_before : '?';
                        $after = $record->pain_level_after ? $record->pain_level_after : '?';
                        return "{$before} → {$after}";
                    })
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('areas_treated')
                    ->label('Areas')
                    ->state(function (TreatmentHistory $record): string {
                        if (!$record->areas_treated || !is_array($record->areas_treated)) {
                            return 'None specified';
                        }
                        return implode(', ', array_slice($record->areas_treated, 0, 3)) . 
                               (count($record->areas_treated) > 3 ? '...' : '');
                    })
                    ->wrap()
                    ->limit(50),
                    
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\IconColumn::make('is_editable')
                    ->label('Editable')
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('patient_condition')
                    ->options([
                        'improved' => 'Improved',
                        'same' => 'Same',
                        'worse' => 'Worse',
                    ]),
                    
                Tables\Filters\SelectFilter::make('therapist_id')
                    ->label('Therapist')
                    ->relationship('therapist', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'title')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\TernaryFilter::make('is_editable')
                    ->label('Editable Status'),
                    
                Tables\Filters\Filter::make('recent')
                    ->label('Recent (Last 30 days)')
                    ->query(fn (Builder $query): Builder => $query->where('treatment_completed_at', '>=', now()->subDays(30))),
            ])
            ->headerActions([
                // No create action as treatment histories are created by therapists
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth('4xl'),
                    
                Tables\Actions\Action::make('view_booking')
                    ->label('View Booking')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->url(fn (TreatmentHistory $record): string => "/admin/bookings/{$record->booking_id}")
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('contact_therapist')
                    ->label('Contact Therapist')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->action(function (TreatmentHistory $record) {
                        // You can implement contact functionality here
                        \Filament\Notifications\Notification::make()
                            ->title('Contact Therapist')
                            ->body("Contact {$record->therapist->name} at {$record->therapist->email}")
                            ->info()
                            ->persistent()
                            ->send();
                    })
                    ->visible(fn (TreatmentHistory $record): bool => $record->therapist && $record->therapist->email),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // Implement export functionality
                            return response()->streamDownload(function () use ($records) {
                                $csv = fopen('php://output', 'w');
                                
                                // Headers
                                fputcsv($csv, [
                                    'Date',
                                    'Service',
                                    'Therapist',
                                    'Patient Condition',
                                    'Pain Before',
                                    'Pain After',
                                    'Treatment Notes',
                                    'Booking Reference'
                                ]);
                                
                                // Data
                                foreach ($records as $record) {
                                    fputcsv($csv, [
                                        $record->treatment_completed_at->format('Y-m-d H:i'),
                                        $record->service->title ?? '',
                                        $record->therapist->name ?? '',
                                        $record->patient_condition ?? '',
                                        $record->pain_level_before ?? '',
                                        $record->pain_level_after ?? '',
                                        $record->treatment_notes ?? '',
                                        $record->booking->reference ?? '',
                                    ]);
                                }
                                
                                fclose($csv);
                            }, 'treatment-history-' . now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('treatment_completed_at', 'desc')
            ->poll('30s');
    }
}