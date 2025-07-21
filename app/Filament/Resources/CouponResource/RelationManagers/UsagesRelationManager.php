<?php
// app/Filament/Resources/CouponResource/RelationManagers/UsagesRelationManager.php

namespace App\Filament\Resources\CouponResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Usage History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Used At')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('Guest'),
                
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->url(fn($record) => $record->booking_id ? "/admin/bookings/{$record->booking_id}" : null),
                
                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Original Price')
                    ->money('GBP')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('GBP')
                    ->sortable()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Final Price')
                    ->money('GBP')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}

