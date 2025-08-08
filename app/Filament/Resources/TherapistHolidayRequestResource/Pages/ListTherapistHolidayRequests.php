<?php

namespace App\Filament\Resources\TherapistHolidayRequestResource\Pages;

use App\Filament\Resources\TherapistHolidayRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTherapistHolidayRequests extends ListRecords
{
    protected static string $resource = TherapistHolidayRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Holiday Request'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Requests'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge(fn() => TherapistHolidayRequestResource::getModel()::where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved'))
                ->badge(fn() => TherapistHolidayRequestResource::getModel()::where('status', 'approved')->count())
                ->badgeColor('success'),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'rejected'))
                ->badge(fn() => TherapistHolidayRequestResource::getModel()::where('status', 'rejected')->count())
                ->badgeColor('danger'),
        ];
    }
}