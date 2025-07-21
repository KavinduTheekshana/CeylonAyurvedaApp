<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;

class ViewCoupon extends ViewRecord
{
     protected static string $resource = CouponResource::class;

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
                Section::make('Coupon Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Coupon Code')
                                    ->copyable()
                                    ->size('lg')
                                    ->weight('bold'),
                                
                                TextEntry::make('type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'percentage' => 'primary',
                                        'fixed' => 'success',
                                    }),
                                
                                TextEntry::make('value')
                                    ->label('Discount Value')
                                    ->formatStateUsing(fn($state, $record) => 
                                        $record->type === 'percentage' 
                                            ? "{$state}%" 
                                            : "£" . number_format($state, 2)
                                    )
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('success'),
                            ]),

                        TextEntry::make('description')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('minimum_amount')
                                    ->label('Minimum Purchase Amount')
                                    ->money('GBP')
                                    ->placeholder('No minimum'),
                                
                                TextEntry::make('is_active')
                                    ->label('Status')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                            ]),
                    ]),

                Section::make('Validity & Usage')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('valid_from')
                                    ->dateTime('M j, Y H:i'),
                                
                                TextEntry::make('valid_until')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('No expiration'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('usage_count')
                                    ->label('Times Used')
                                    ->badge()
                                    ->color('info'),
                                
                                TextEntry::make('usage_limit')
                                    ->label('Usage Limit')
                                    ->placeholder('Unlimited'),
                                
                                TextEntry::make('usage_limit_per_user')
                                    ->label('Per User Limit')
                                    ->placeholder('Unlimited'),
                            ]),
                    ]),

                Section::make('Applicable Services')
                    ->schema([
                        TextEntry::make('services_display')
                            ->label('Services')
                            ->state(function ($record) {
                                if ($record->services->isEmpty()) {
                                    return 'All Services';
                                }
                                return $record->services->map(fn($service) => 
                                    "{$service->treatment->name} - {$service->title}"
                                )->join(', ');
                            })
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Usage Statistics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_discount_given')
                                    ->label('Total Discount Given')
                                    ->state(function ($record) {
                                        return '£' . number_format($record->usages->sum('discount_amount'), 2);
                                    })
                                    ->badge()
                                    ->color('success'),
                                
                                TextEntry::make('average_discount')
                                    ->label('Average Discount')
                                    ->state(function ($record) {
                                        $avg = $record->usages->avg('discount_amount') ?? 0;
                                        return '£' . number_format($avg, 2);
                                    }),
                                
                                TextEntry::make('unique_users')
                                    ->label('Unique Users')
                                    ->state(function ($record) {
                                        return $record->usages->unique('user_id')->count();
                                    })
                                    ->badge()
                                    ->color('primary'),
                                
                                TextEntry::make('last_used')
                                    ->label('Last Used')
                                    ->state(function ($record) {
                                        $lastUsage = $record->usages()->latest()->first();
                                        return $lastUsage ? $lastUsage->created_at->diffForHumans() : 'Never';
                                    }),
                            ]),
                    ]),
            ]);
    }
}
