<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;

class ViewInvestment extends ViewRecord
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Investment Overview')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('reference')
                                    ->label('Reference Number')
                                    ->copyable()
                                    ->size('lg')
                                    ->weight('bold'),
                                
                                TextEntry::make('amount')
                                    ->label('Investment Amount')
                                    ->money('GBP')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('success'),
                                
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'gray',
                                    }),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('invested_at')
                                    ->label('Investment Date')
                                    ->dateTime('M j, Y g:i A')
                                    ->placeholder('Not completed yet'),
                                
                                TextEntry::make('currency')
                                    ->label('Currency')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'GBP' => 'British Pound (£)',
                                        'USD' => 'US Dollar ($)',
                                        'EUR' => 'Euro (€)',
                                        default => $state,
                                    }),
                            ]),
                    ]),

                Section::make('Investor Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Investor Name')
                                    ->icon('heroicon-o-user'),
                                
                                TextEntry::make('user.email')
                                    ->label('Email Address')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable(),
                            ]),

                        TextEntry::make('user.total_invested')
                            ->label('Total Invested by User')
                            ->money('GBP')
                            ->state(function ($record) {
                                return $record->user->investments()
                                    ->where('status', 'completed')
                                    ->sum('amount');
                            }),
                    ]),

                Section::make('Location Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('location.name')
                                    ->label('Location Name')
                                    ->icon('heroicon-o-map-pin'),
                                
                                TextEntry::make('location.city')
                                    ->label('City')
                                    ->icon('heroicon-o-building-office-2'),
                            ]),

                        TextEntry::make('location.address')
                            ->label('Address')
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('location_total_invested')
                                    ->label('Total Invested in Location')
                                    ->money('GBP')
                                    ->state(function ($record) {
                                        return $record->location->investments()
                                            ->where('status', 'completed')
                                            ->sum('amount');
                                    }),
                                
                                TextEntry::make('location_investment_limit')
                                    ->label('Investment Limit')
                                    ->money('GBP')
                                    ->state(function ($record) {
                                        return $record->location->locationInvestment?->investment_limit ?? 0;
                                    }),
                                
                                TextEntry::make('location_remaining')
                                    ->label('Remaining Available')
                                    ->money('GBP')
                                    ->state(function ($record) {
                                        $locationInvestment = $record->location->locationInvestment;
                                        if (!$locationInvestment) return 0;
                                        
                                        $totalInvested = $record->location->investments()
                                            ->where('status', 'completed')
                                            ->sum('amount');
                                        
                                        return max(0, $locationInvestment->investment_limit - $totalInvested);
                                    })
                                    ->color('warning'),
                            ]),
                    ]),

                Section::make('Payment Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('stripe_payment_intent_id')
                                    ->label('Stripe Payment Intent ID')
                                    ->placeholder('Manual entry - no Stripe payment')
                                    ->copyable(),
                                
                                TextEntry::make('stripe_payment_method_id')
                                    ->label('Stripe Payment Method ID')
                                    ->placeholder('Not available')
                                    ->copyable(),
                            ]),

                        KeyValueEntry::make('stripe_metadata')
                            ->label('Additional Metadata')
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->stripe_metadata)),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Notes & Timeline')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes available')
                            ->prose()
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Record Created')
                                    ->dateTime('M j, Y g:i A')
                                    ->since()
                                    ->icon('heroicon-o-clock'),
                                
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('M j, Y g:i A')
                                    ->since()
                                    ->icon('heroicon-o-pencil'),
                            ]),
                    ]),
            ]);
    }
}