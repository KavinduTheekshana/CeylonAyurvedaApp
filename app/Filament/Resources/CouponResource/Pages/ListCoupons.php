<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Coupons'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->active())
                ->badge(fn () => CouponResource::getModel()::active()->count()),
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('valid_until', '<', now()))
                ->badge(fn () => CouponResource::getModel()::where('valid_until', '<', now())->count()),
            'exhausted' => Tab::make('Exhausted')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('usage_limit')->whereColumn('usage_count', '>=', 'usage_limit'))
                ->badge(fn () => CouponResource::getModel()::whereNotNull('usage_limit')->whereColumn('usage_count', '>=', 'usage_limit')->count()),
        ];
    }
}
