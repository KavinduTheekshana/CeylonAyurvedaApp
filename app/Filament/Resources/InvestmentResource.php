<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentResource\Pages;
use App\Mail\BankTransferConfirmedMail;
use App\Mail\InvestmentConfirmationMail;
use App\Models\Investment;
use App\Models\Location;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvestmentResource extends Resource
{
    protected static ?string $model = Investment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Investment Management';

    protected static ?string $navigationLabel = 'Investments';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Investment Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Investor')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->unique(User::class, 'email')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('password')
                                            ->password()
                                            ->required()
                                            ->minLength(8),
                                    ]),

                                Forms\Components\Select::make('location_id')
                                    ->label('Location')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $location = Location::find($state);
                                            if ($location && $location->locationInvestment) {
                                                $remaining = $location->locationInvestment->remaining_amount;
                                                // Could set a helper text or validation
                                            }
                                        }
                                    }),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Investment Amount (£)')
                                    ->numeric()
                                    ->prefix('£')
                                    ->required()
                                    ->minValue(10)
                                    ->maxValue(10000)
                                    ->step(0.01)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        // Auto-generate reference when amount is set
                                        if ($state && !$get('reference')) {
                                            $set('reference', Investment::generateReference());
                                        }
                                    }),

                                Forms\Components\Select::make('currency')
                                    ->options([
                                        'GBP' => 'British Pound (£)',
                                        'USD' => 'US Dollar ($)',
                                        'EUR' => 'Euro (€)',
                                    ])
                                    ->default('GBP')
                                    ->required(),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'card' => 'Card Payment',
                                        'bank_transfer' => 'Bank Transfer',
                                    ])
                                    ->default('card')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Reset payment-specific fields when changing method
                                        if ($state === 'bank_transfer') {
                                            $set('stripe_payment_intent_id', null);
                                            $set('stripe_payment_method_id', null);
                                        } else {
                                            $set('bank_transfer_details', null);
                                            $set('bank_transfer_confirmed_at', null);
                                        }
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->default('completed') // For manual entries, usually completed
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('reference')
                                    ->label('Reference Number')
                                    ->unique(ignoreRecord: true)
                                    ->default(fn() => Investment::generateReference())
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\DateTimePicker::make('invested_at')
                            ->label('Investment Date')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Any additional notes about this investment...')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        // Card Payment Fields
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('stripe_payment_intent_id')
                                            ->label('Stripe Payment Intent ID')
                                            ->placeholder('pi_1234567890...')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('stripe_payment_method_id')
                                            ->label('Stripe Payment Method ID')
                                            ->placeholder('pm_1234567890...')
                                            ->maxLength(255),
                                    ]),
                            ])
                            ->visible(fn(Get $get): bool => $get('payment_method') === 'card'),

                        // Bank Transfer Fields
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('confirmed_by_admin_id')
                                            ->label('Confirmed by Admin')
                                            ->relationship('confirmedByAdmin', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select admin who confirmed'),

                                        Forms\Components\DateTimePicker::make('bank_transfer_confirmed_at')
                                            ->label('Bank Transfer Confirmed At')
                                            ->native(false),
                                    ]),

                                Forms\Components\Textarea::make('bank_transfer_details')
                                    ->label('Bank Transfer Details')
                                    ->placeholder('Admin notes about the bank transfer confirmation...')
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn(Get $get): bool => $get('payment_method') === 'bank_transfer'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Admin Actions')
                    ->schema([
                        Forms\Components\Placeholder::make('admin_actions_info')
                            ->label('')
                            ->content('Use the actions below to manage bank transfer confirmations and investment status updates.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record && $record->payment_method === 'bank_transfer' && $record->status === 'pending')
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Reference copied!')
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Investor')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Investment $record): string => $record->user->email),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Investment $record): string => $record->location->city),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('GBP')
                    ->sortable()
                    ->alignEnd()
                    ->weight(FontWeight::Bold),

                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Payment')
                    ->colors([
                        'primary' => 'card',
                        'info' => 'bank_transfer',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'card' => 'Card',
                        'bank_transfer' => 'Bank Transfer',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('invested_at')
                    ->label('Investment Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn(Investment $record): string => $record->invested_at?->diffForHumans() ?? 'Not completed'),

                Tables\Columns\TextColumn::make('bank_transfer_confirmed_at')
                    ->label('Bank Transfer Confirmed')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not confirmed'),

                Tables\Columns\TextColumn::make('confirmedByAdmin.name')
                    ->label('Confirmed By')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not confirmed'),

                Tables\Columns\TextColumn::make('stripe_payment_intent_id')
                    ->label('Stripe ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->placeholder('Manual/Bank transfer'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'card' => 'Card Payment',
                        'bank_transfer' => 'Bank Transfer',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_from')
                                    ->label('Amount From (£)')
                                    ->numeric()
                                    ->prefix('£'),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('Amount To (£)')
                                    ->numeric()
                                    ->prefix('£'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

                Tables\Filters\Filter::make('pending_bank_transfers')
                    ->label('Pending Bank Transfers')
                    ->query(fn(Builder $query): Builder => $query->where('payment_method', 'bank_transfer')->where('status', 'pending'))
                    ->toggle(),

                Tables\Filters\Filter::make('invested_date')
                    ->form([
                        Forms\Components\DatePicker::make('invested_from')
                            ->label('Invested From'),
                        Forms\Components\DatePicker::make('invested_until')
                            ->label('Invested Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['invested_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('invested_at', '>=', $date),
                            )
                            ->when(
                                $data['invested_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('invested_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm_bank_transfer')
                    ->label('Confirm Bank Transfer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Investment $record): bool => $record->payment_method === 'bank_transfer' && $record->status === 'pending')
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
                        Forms\Components\Toggle::make('send_email')
                            ->label('Send confirmation email to investor')
                            ->default(true)
                            ->helperText('Notify the investor via email about the confirmation'),
                    ])
                    ->action(function (Investment $record, array $data): void {
                        try {
                            $record->update([
                                'status' => $data['confirmed'] ? 'completed' : 'failed',
                                'bank_transfer_details' => $data['bank_transfer_details'],
                                'bank_transfer_confirmed_at' => $data['confirmed'] ? now() : null,
                                'confirmed_by_admin_id' => Auth::id(),
                                'invested_at' => $data['confirmed'] ? ($record->invested_at ?? now()) : $record->invested_at,
                            ]);

                            if ($data['confirmed']) {
                                // Update location investment totals
                                $locationInvestment = $record->location->locationInvestment;
                                if ($locationInvestment) {
                                    $locationInvestment->increment('total_invested', $record->amount);

                                    // Check if this is a new investor
                                    $existingInvestorCount = Investment::where('location_id', $record->location_id)
                                        ->where('user_id', $record->user_id)
                                        ->where('status', 'completed')
                                        ->count();

                                    if ($existingInvestorCount === 1) {
                                        $locationInvestment->increment('total_investors');
                                    }
                                }

                                // Send confirmation email if requested
                                if ($data['send_email']) {
            
                                    try {
                                        Log::info('Sending bank transfer confirmation email from Filament', [
                                            'investment_id' => $record->id,
                                            'user_email' => $record->user->email,
                                            'all data' => $record->toArray()

                                        ]);

                                        Mail::to($record->user->email)->send(new BankTransferConfirmedMail($record));

                                        Log::info('Bank transfer confirmation email sent successfully from Filament', [
                                            'investment_id' => $record->id
                                        ]);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Bank transfer confirmed and email sent')
                                            ->body('The investor has been notified via email.')
                                            ->success()
                                            ->send();

                                    } catch (\Exception $e) {
                                        Log::error('Failed to send bank transfer confirmation email from Filament', [
                                            'investment_id' => $record->id,
                                            'error' => $e->getMessage()
                                        ]);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Bank transfer confirmed but email failed')
                                            ->body('Investment confirmed successfully, but email notification failed to send.')
                                            ->warning()
                                            ->send();
                                    }
                                } else {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Bank transfer confirmed')
                                        ->body('Investment has been confirmed successfully.')
                                        ->success()
                                        ->send();
                                }
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Bank transfer rejected')
                                    ->body('Investment has been marked as failed.')
                                    ->warning()
                                    ->send();
                            }

                        } catch (\Exception $e) {
                            Log::error('Failed to confirm bank transfer from Filament', [
                                'investment_id' => $record->id,
                                'error' => $e->getMessage()
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Confirmation failed')
                                ->body('Failed to confirm bank transfer: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->successNotificationTitle('Bank transfer updated successfully'),

                Tables\Actions\Action::make('confirm_card_payment')
                    ->label('Confirm Card Payment')
                    ->icon('heroicon-o-credit-card')
                    ->color('info')
                    ->visible(fn (Investment $record): bool => $record->payment_method === 'card' && 
                        $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes (Optional)')
                            ->placeholder('Add any notes about this payment confirmation...')
                            ->maxLength(500),
                        Forms\Components\Toggle::make('send_confirmation_email')
                            ->label('Send confirmation email to investor')
                            ->default(true)
                            ->helperText('Notify the investor that their payment has been confirmed'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Card Payment')
                    ->modalDescription('This will mark the investment as completed and optionally notify the investor.')
                    ->action(function (Investment $record, array $data): void {
                        try {
                            // Update investment status
                            $record->update([
                                'status' => 'completed',
                                'invested_at' => $record->invested_at ?? now(),
                                'notes' => $data['admin_notes'] ? 
                                    ($record->notes ? $record->notes . "\n\nAdmin: " . $data['admin_notes'] : "Admin: " . $data['admin_notes']) : 
                                    $record->notes,
                            ]);
                            
                            // Update location investment totals
                            $locationInvestment = $record->location->locationInvestment;
                            if ($locationInvestment) {
                                $locationInvestment->increment('total_invested', $record->amount);
                                
                                // Check if this is a new investor
                                $existingInvestorCount = Investment::where('location_id', $record->location_id)
                                    ->where('user_id', $record->user_id)
                                    ->where('status', 'completed')
                                    ->count();
                                    
                                if ($existingInvestorCount === 1) {
                                    $locationInvestment->increment('total_investors');
                                }
                            }

                            // Send confirmation email if requested
                            if ($data['send_confirmation_email']) {
                                try {
                                    Log::info('Sending card payment confirmation email from Filament', [
                                        'investment_id' => $record->id,
                                        'user_email' => $record->user->email
                                    ]);

                                    Mail::to($record->user->email)->send(new InvestmentConfirmationMail($record));

                                    Log::info('Card payment confirmation email sent successfully from Filament', [
                                        'investment_id' => $record->id
                                    ]);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Payment confirmed and email sent')
                                        ->body('The investor has been notified via email.')
                                        ->success()
                                        ->send();

                                } catch (\Exception $e) {
                                    Log::error('Failed to send card payment confirmation email from Filament', [
                                        'investment_id' => $record->id,
                                        'error' => $e->getMessage()
                                    ]);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Payment confirmed but email failed')
                                        ->body('Investment confirmed successfully, but email notification failed to send.')
                                        ->warning()
                                        ->send();
                                }
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Payment confirmed')
                                    ->body('Investment has been marked as completed.')
                                    ->success()
                                    ->send();
                            }
                            
                        } catch (\Exception $e) {
                            Log::error('Failed to confirm card payment from Filament', [
                                'investment_id' => $record->id,
                                'error' => $e->getMessage()
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('Confirmation failed')
                                ->body('Failed to confirm payment: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('resend_stripe_webhook')
                    ->label('Retry Stripe Processing')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Investment $record): bool => $record->payment_method === 'card' && 
                        $record->status === 'pending' && 
                        !empty($record->stripe_payment_intent_id))
                    ->requiresConfirmation()
                    ->modalHeading('Retry Stripe Processing')
                    ->modalDescription('This will attempt to re-verify the payment status with Stripe. Only use this if you believe the payment was successful but not properly recorded.')
                    ->action(function (Investment $record): void {
                        try {
                            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                            $paymentIntent = \Stripe\PaymentIntent::retrieve($record->stripe_payment_intent_id);
                            
                            if ($paymentIntent->status === 'succeeded') {
                                $record->update([
                                    'status' => 'completed',
                                    'invested_at' => $record->invested_at ?? now(),
                                ]);
                                
                                // Update location investment totals
                                $locationInvestment = $record->location->locationInvestment;
                                if ($locationInvestment) {
                                    $locationInvestment->increment('total_invested', $record->amount);
                                    
                                    $existingInvestorCount = Investment::where('location_id', $record->location_id)
                                        ->where('user_id', $record->user_id)
                                        ->where('status', 'completed')
                                        ->count();
                                        
                                    if ($existingInvestorCount === 1) {
                                        $locationInvestment->increment('total_investors');
                                    }
                                }

                                // Automatically send confirmation email for successful Stripe verification
                                try {
                                    Mail::to($record->user->email)->send(new InvestmentConfirmationMail($record));
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Payment verified and email sent')
                                        ->body('Stripe payment was successfully verified, investment completed, and investor notified.')
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    Log::error('Failed to send email after Stripe verification', [
                                        'investment_id' => $record->id,
                                        'error' => $e->getMessage()
                                    ]);
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Payment verified but email failed')
                                        ->body('Stripe payment was verified and investment completed, but email notification failed.')
                                        ->warning()
                                        ->send();
                                }
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Payment Not Completed')
                                    ->body("Stripe payment status is: {$paymentIntent->status}")
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Stripe Error')
                                ->body("Failed to verify payment: {$e->getMessage()}")
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Investment')
                    ->modalDescription('Are you sure you want to delete this investment? This action cannot be undone and will affect location totals.')
                    ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => 'completed',
                                    'invested_at' => $record->invested_at ?? now(),
                                ]);

                                // Update location totals for newly completed investments
                                if ($record->wasChanged('status') && $record->status === 'completed') {
                                    $locationInvestment = $record->location->locationInvestment;
                                    if ($locationInvestment) {
                                        $locationInvestment->increment('total_invested', $record->amount);

                                        $existingInvestorCount = Investment::where('location_id', $record->location_id)
                                            ->where('user_id', $record->user_id)
                                            ->where('status', 'completed')
                                            ->count();

                                        if ($existingInvestorCount === 1) {
                                            $locationInvestment->increment('total_investors');
                                        }
                                    }
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('confirm_bank_transfers')
                        ->label('Confirm Bank Transfers')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Multiple Bank Transfers')
                        ->modalDescription('This will mark all selected bank transfer investments as completed.')
                        ->action(function ($records) {
                            $bankTransferRecords = $records->filter(function ($record) {
                                return $record->payment_method === 'bank_transfer' && $record->status === 'pending';
                            });

                            foreach ($bankTransferRecords as $record) {
                                $record->update([
                                    'status' => 'completed',
                                    'bank_transfer_confirmed_at' => now(),
                                    'confirmed_by_admin_id' => Auth::id(),
                                    'invested_at' => $record->invested_at ?? now(),
                                    'bank_transfer_details' => 'Bulk confirmed by admin',
                                ]);

                                // Update location totals
                                $locationInvestment = $record->location->locationInvestment;
                                if ($locationInvestment) {
                                    $locationInvestment->increment('total_invested', $record->amount);

                                    $existingInvestorCount = Investment::where('location_id', $record->location_id)
                                        ->where('user_id', $record->user_id)
                                        ->where('status', 'completed')
                                        ->count();

                                    if ($existingInvestorCount === 1) {
                                        $locationInvestment->increment('total_investors');
                                    }
                                }

                                // Send email for each confirmed bank transfer
                                try {
                                    Mail::to($record->user->email)->send(new BankTransferConfirmedMail($record));
                                    Log::info('Bulk bank transfer confirmation email sent', [
                                        'investment_id' => $record->id
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('Failed to send bulk bank transfer confirmation email', [
                                        'investment_id' => $record->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('export')
                        ->label('Export to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            return response()->streamDownload(function () use ($records) {
                                $csv = fopen('php://output', 'w');

                                // Headers
                                fputcsv($csv, [
                                    'Reference',
                                    'Investor Name',
                                    'Investor Email',
                                    'Location',
                                    'Amount',
                                    'Currency',
                                    'Payment Method',
                                    'Status',
                                    'Investment Date',
                                    'Bank Transfer Confirmed',
                                    'Confirmed By',
                                    'Created At'
                                ]);

                                // Data
                                foreach ($records as $record) {
                                    fputcsv($csv, [
                                        $record->reference,
                                        $record->user->name,
                                        $record->user->email,
                                        $record->location->name,
                                        $record->amount,
                                        $record->currency,
                                        $record->payment_method,
                                        $record->status,
                                        $record->invested_at?->format('Y-m-d H:i:s'),
                                        $record->bank_transfer_confirmed_at?->format('Y-m-d H:i:s'),
                                        $record->confirmedByAdmin?->name,
                                        $record->created_at->format('Y-m-d H:i:s'),
                                    ]);
                                }

                                fclose($csv);
                            }, 'investments-' . now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvestments::route('/'),
            'create' => Pages\CreateInvestment::route('/create'),
            'view' => Pages\ViewInvestment::route('/{record}'),
            'edit' => Pages\EditInvestment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = static::getModel()::where('status', 'pending')->count();
        $bankTransferCount = static::getModel()::where('payment_method', 'bank_transfer')
            ->where('status', 'pending')->count();

        return $bankTransferCount > 0 ? $bankTransferCount : ($pendingCount ?: null);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $bankTransferCount = static::getModel()::where('payment_method', 'bank_transfer')
            ->where('status', 'pending')->count();
        $pendingCount = static::getModel()::where('status', 'pending')->count();

        return $bankTransferCount > 5 ? 'danger' : ($bankTransferCount > 0 ? 'warning' :
            ($pendingCount > 10 ? 'danger' : ($pendingCount > 0 ? 'warning' : null)));
    }
}