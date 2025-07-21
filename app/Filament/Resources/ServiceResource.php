<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Models\Service;
use App\Models\Treatment;
use Filament\Forms;
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

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static ?string $navigationGroup = 'Treatment Management';
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('treatment_id')
                    ->label('Treatment')
                    ->options(Treatment::pluck('name', 'id'))
                    ->required(),
                FileUpload::make('image')
                    ->directory('services') // Store images in storage/app/public/services
                    ->image()
                    ->label('Service Image')
                    ->required(),
                TextInput::make('title')->required()->label('Title'),
                TextInput::make('subtitle')->label('Subtitle'),
                TextInput::make('price')->numeric()->label('Price'),
                TextInput::make('discount_price')->numeric()->label('Discount Price'),
                Toggle::make('offer')->label('Offer')->default(false),
                TextInput::make('duration')->numeric()->label('Time Duration (Minutes)'),
                Textarea::make('benefits')->label('Benefits'),
                Textarea::make('description')->label('Description'),
                Toggle::make('status')->label('Active')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->label('Image')->size(50),
                TextColumn::make('treatment.name')->label('Treatment')->sortable(),
                TextColumn::make('title')->label('Title')->sortable()->searchable(),
                TextColumn::make('subtitle')->label('Subtitle')->sortable(),
                TextColumn::make('price')->label('Price')->sortable(),
                TextColumn::make('discount_price')->label('Discount Price')->sortable(),
                BooleanColumn::make('offer')->label('Offer'),
                TextColumn::make('duration')->label('Duration (Min)')->sortable(),
                BooleanColumn::make('status')->label('Active'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
