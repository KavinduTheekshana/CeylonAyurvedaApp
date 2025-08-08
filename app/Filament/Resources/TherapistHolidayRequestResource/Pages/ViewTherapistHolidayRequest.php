<?php

namespace App\Filament\Resources\TherapistHolidayRequestResource\Pages;

use App\Filament\Resources\TherapistHolidayRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;

class ViewTherapistHolidayRequest extends ViewRecord
{
    protected static string $resource = TherapistHolidayRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn() => $this->record->status === 'pending'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Holiday Request Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('therapist.name')
                                    ->label('Therapist')
                                    ->icon('heroicon-o-user'),
                                
                                TextEntry::make('date')
                                    ->label('Holiday Date')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar'),
                            ]),

                        TextEntry::make('reason')
                            ->label('Reason')
                            ->prose()
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                    }),
                                
                                TextEntry::make('created_at')
                                    ->label('Requested On')
                                    ->dateTime('M d, Y H:i'),
                                
                                TextEntry::make('reviewed_at')
                                    ->label('Reviewed On')
                                    ->dateTime('M d, Y H:i')
                                    ->placeholder('Not reviewed'),
                            ]),
                    ]),

                Section::make('Admin Review')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('reviewedBy.name')
                                    ->label('Reviewed By')
                                    ->placeholder('Not reviewed')
                                    ->icon('heroicon-o-user-circle'),
                                
                                TextEntry::make('reviewed_at')
                                    ->label('Review Date')
                                    ->dateTime('M d, Y H:i')
                                    ->placeholder('Not reviewed')
                                    ->icon('heroicon-o-clock'),
                            ]),

                        TextEntry::make('admin_notes')
                            ->label('Admin Notes')
                            ->prose()
                            ->placeholder('No admin notes')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record->status !== 'pending'),
            ]);
    }
}