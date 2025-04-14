<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AddressResource\Pages;
use App\Filament\Resources\AddressResource\RelationManagers;
use App\Models\Address;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AddressResource extends Resource
{
    protected static ?string $model = Address::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()
                ->required(),
            TextInput::make('name')->required(),
            TextInput::make('phone')->required(),
            TextInput::make('email')->email()->required(),
            TextInput::make('address_line1')->required(),
            TextInput::make('address_line2'),
            TextInput::make('city')->required(),
            TextInput::make('postcode')->required(),
            Checkbox::make('is_default')
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User'),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('phone')->sortable(),
                TextColumn::make('email')->sortable(),
                TextColumn::make('address_line1')->sortable(),
                TextColumn::make('city')->sortable(),
                TextColumn::make('postcode')->sortable(),
                BooleanColumn::make('is_default')->label('Default Address'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => Pages\ListAddresses::route('/'),
            'create' => Pages\CreateAddress::route('/create'),
            'edit' => Pages\EditAddress::route('/{record}/edit'),
        ];
    }
}
