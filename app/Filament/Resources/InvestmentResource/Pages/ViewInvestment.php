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
use Filament\Forms;
use Illuminate\Support\Facades\Auth;

class ViewInvestment extends ViewRecord
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm_bank_transfer')
                ->label('Confirm Bank Transfer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->payment_method === 'bank_transfer' && $this->record->status === 'pending')
                ->form([
                    Forms\Components\Textarea::make('bank_transfer_details')
                        ->label('Bank Transfer Details')
                        ->placeholder('Enter details about the bank transfer confirmation...')
                        ->required()
                        ->maxLength(1000),
                    Forms\Components\Toggle::make('confirmed')
                        ->label('Confirm this bank transfer')
                        ->default(true)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => $data['confirmed'] ? 'completed' : 'failed',
                        'bank_transfer_details' => $data['bank_transfer_details'],
                        'bank_transfer_confirmed_at' => $data['confirmed'] ? now() : null,
                        'confirmed_by_admin_id' => Auth::id(),
                        'invested_at' => $data['confirmed'] ? ($this->record->invested_at ?? now()) : $this->record->invested_at,
                    ]);

                    if ($data['confirmed']) {
                        // Update location investment totals
                        $locationInvestment = $this->record->location->locationInvestment;
                        if ($locationInvestment) {
                            $locationInvestment->increment('total_invested', $this->record->amount);
                            
                            // Check if this is a new investor
                            $existingInvestorCount = \App\Models\Investment::where('location_id', $this->record->location_id)
                                ->where('user_id', $this->record->user_id)
                                ->where('status', 'completed')
                                ->count();
                                
                            if ($existingInvestorCount === 1) {
                                $locationInvestment->increment('total_investors');
                            }
                        }
                    }

                    $this->refreshFormData([
                        'status',
                        'bank_transfer_details',
                        'bank_transfer_confirmed_at',
                        'confirmed_by_admin_id',
                    ]);
                })
                ->successNotificationTitle('Bank transfer updated successfully'),

            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Investment Overview')
                    ->schema([
                        Grid::make(4)
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
                                
                                TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'card' => 'primary',
                                        'bank_transfer' => 'info',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'card' => 'Card Payment',
                                        'bank_transfer' => 'Bank Transfer',
                                        default => $state,
                                    }),
                                
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
                        // Card Payment Information
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
                            ])
                            ->visible(fn ($record) => $record->payment_method === 'card'),

                        // Bank Transfer Information
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('bank_transfer_confirmed_at')
                                    ->label('Bank Transfer Confirmed At')
                                    ->dateTime('M j, Y g:i A')
                                    ->placeholder('Not confirmed yet')
                                    ->color(fn ($record) => $record->bank_transfer_confirmed_at ? 'success' : 'warning'),
                                
                                TextEntry::make('confirmedByAdmin.name')
                                    ->label('Confirmed By Admin')
                                    ->placeholder('Not confirmed yet')
                                    ->icon('heroicon-o-user-circle'),
                            ])
                            ->visible(fn ($record) => $record->payment_method === 'bank_transfer'),

                        TextEntry::make('bank_transfer_details')
                            ->label('Bank Transfer Details')
                            ->placeholder('No details available')
                            ->prose()
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->payment_method === 'bank_transfer' && $record->bank_transfer_details),

                        // Show Stripe metadata for card payments
                        // KeyValueEntry::make('stripe_metadata')
                        //     ->label('Stripe Metadata')
                        //     ->columnSpanFull()
                        //     ->visible(fn ($record) => $record->payment_method === 'card' && !empty($record->stripe_metadata)),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => $record->payment_method === 'card' && empty($record->stripe_payment_intent_id)),

                Section::make('Bank Transfer Status')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('bank_transfer_status')
                                    ->label('Bank Transfer Status')
                                    ->state(function ($record) {
                                        if ($record->payment_method !== 'bank_transfer') {
                                            return 'Not applicable';
                                        }
                                        
                                        return match ($record->status) {
                                            'pending' => 'Awaiting admin confirmation',
                                            'completed' => 'Confirmed and processed',
                                            'failed' => 'Rejected by admin',
                                            default => ucfirst($record->status),
                                        };
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if ($record->payment_method !== 'bank_transfer') {
                                            return 'gray';
                                        }
                                        
                                        return match ($record->status) {
                                            'pending' => 'warning',
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            default => 'info',
                                        };
                                    }),
                            ]),

                        TextEntry::make('bank_transfer_workflow')
                            ->label('Next Steps')
                            ->state(function ($record) {
                                if ($record->payment_method !== 'bank_transfer') {
                                    return 'This is a card payment - no bank transfer workflow required.';
                                }
                                
                                return match ($record->status) {
                                    'pending' => 'Investment is awaiting admin review and confirmation. Admin team will verify the bank transfer and update the status.',
                                    'completed' => 'Bank transfer has been confirmed and the investment is complete.',
                                    'failed' => 'Bank transfer was rejected by admin. Investment has been declined.',
                                    default => 'Status unclear - please review investment details.',
                                };
                            })
                            ->prose()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->payment_method === 'bank_transfer')
                    ->collapsible(),

                Section::make('Investment Transactions')
                    ->schema([
                        TextEntry::make('transactions_summary')
                            ->label('Transaction History')
                            ->state(function ($record) {
                                $transactions = $record->transactions()->orderBy('created_at', 'desc')->get();
                                
                                if ($transactions->isEmpty()) {
                                    return 'No transactions recorded yet.';
                                }
                                
                                $summary = '';
                                foreach ($transactions as $transaction) {
                                    $summary .= "• {$transaction->type} - £{$transaction->amount} - {$transaction->status} ({$transaction->created_at->format('M j, Y g:i A')})\n";
                                }
                                
                                return trim($summary);
                            })
                            ->prose()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->transactions()->exists())
                    ->collapsible()
                    ->collapsed(),

                Section::make('Notes & Timeline')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Investment Notes')
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