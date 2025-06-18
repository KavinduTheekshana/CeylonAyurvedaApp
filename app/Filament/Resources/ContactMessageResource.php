<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Filament\Resources\ContactMessageResource\RelationManagers;
use App\Models\ContactMessage;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessageReply;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Contact Messages';

    protected static ?string $modelLabel = 'Contact Message';

    protected static ?string $pluralModelLabel = 'Contact Messages';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationGroup = 'Communication';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Message Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        'closed' => 'Closed',
                                    ])
                                    ->required()
                                    ->default('pending')
                                    ->live()
                                    ->afterStateUpdated(function ($state, $record) {
                                        if ($record && in_array($state, ['resolved', 'closed']) && !$record->responded_at) {
                                            $record->update([
                                                'responded_at' => now(),
                                                'responded_by' => Auth::id(),
                                            ]);
                                        }
                                    }),
                            ]),

                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Admin Response')
                    ->schema([
                        Forms\Components\RichEditor::make('admin_response')
                            ->label('Your Response')
                            ->placeholder('Type your response to the customer here...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\Hidden::make('responded_by')
                                    ->default(Auth::id()),
                                Forms\Components\Hidden::make('responded_at')
                                    ->default(now()),
                            ]),
                    ])
                    ->visible(fn ($record) => $record && $record->status !== 'closed'),

                Section::make('Message Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('user_type')
                                    ->label('User Type')
                                    ->content(fn ($record) => $record ? ($record->is_guest ? 'Guest User' : 'Registered User') : '-'),
                                
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Received At')
                                    ->content(fn ($record) => $record ? $record->created_at->format('M j, Y g:i A') : '-'),
                                
                                Forms\Components\Placeholder::make('responded_at')
                                    ->label('Last Response')
                                    ->content(fn ($record) => $record && $record->responded_at ? $record->responded_at->format('M j, Y g:i A') : 'Not responded'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'resolved',
                        'gray' => 'closed',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),


                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('responded_at')
                    ->label('Responded')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Not responded')
                    ->sortable(),

                Tables\Columns\TextColumn::make('respondedBy.name')
                    ->label('Responded By')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->default('pending'),

                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('is_guest')
                    ->label('Guest Users Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_guest', true)),

                Filter::make('unresponded')
                    ->label('Unresponded Messages')
                    ->query(fn (Builder $query): Builder => $query->whereNull('responded_at')),

                Filter::make('today')
                    ->label('Today\'s Messages')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('Respond')
                    ->after(function (ContactMessage $record, array $data) {
                        // Send email notification if response is provided
                        if (!empty($data['admin_response']) && $record->status !== 'closed') {
                            try {
                                Mail::to($record->email)->send(new ContactMessageReply($record));
                                
                                Notification::make()
                                    ->title('Response sent successfully!')
                                    ->body("Email notification sent to {$record->email}")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Email sending failed')
                                    ->body('Response saved but email notification failed to send.')
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_as_resolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'status' => 'resolved',
                                    'responded_at' => now(),
                                    'responded_by' => Auth::id(),
                                ]);
                            });
                            
                            Notification::make()
                                ->title('Messages marked as resolved')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
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
            'index' => Pages\ListContactMessages::route('/'),
            'create' => Pages\CreateContactMessage::route('/create'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
            'edit' => Pages\EditContactMessage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 10 ? 'danger' : 'warning';
    }
}