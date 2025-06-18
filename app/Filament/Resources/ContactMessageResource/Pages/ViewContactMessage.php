<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;

class ViewContactMessage extends ViewRecord
{
   protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Respond to Message'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Customer Name')
                                    ->icon('heroicon-o-user'),
                                TextEntry::make('email')
                                    ->label('Email Address')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('branch.name')
                                    ->label('Branch')
                                    ->icon('heroicon-o-building-office'),
                                IconEntry::make('is_guest')
                                    ->label('User Type')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-user')
                                    ->falseIcon('heroicon-o-user-circle')
                                    ->formatStateUsing(fn ($state) => $state ? 'Guest User' : 'Registered User'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'in_progress' => 'info',
                                        'resolved' => 'success',
                                        'closed' => 'gray',
                                    }),
                            ]),
                    ]),

                Section::make('Message Details')
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Subject')
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('message')
                            ->label('Message')
                            ->html()
                            ->prose(),
                    ]),

                Section::make('Admin Response')
                    ->schema([
                        TextEntry::make('admin_response')
                            ->label('Response')
                            ->html()
                            ->prose()
                            ->placeholder('No response yet'),
                    ])
                    ->visible(fn ($record) => !empty($record->admin_response)),

                Section::make('Timeline')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Message Received')
                                    ->dateTime('M j, Y g:i A')
                                    ->icon('heroicon-o-chat-bubble-left'),
                                TextEntry::make('responded_at')
                                    ->label('Response Sent')
                                    ->dateTime('M j, Y g:i A')
                                    ->placeholder('Not responded yet')
                                    ->icon('heroicon-o-chat-bubble-left-right'),
                                TextEntry::make('respondedBy.name')
                                    ->label('Responded By')
                                    ->placeholder('Not responded yet')
                                    ->icon('heroicon-o-user-circle'),
                            ]),
                    ]),
            ]);
    }
}
