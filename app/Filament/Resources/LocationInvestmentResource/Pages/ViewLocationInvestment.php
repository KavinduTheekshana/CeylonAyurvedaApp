<?php
namespace App\Filament\Resources\LocationInvestmentResource\Pages;

use App\Filament\Resources\LocationInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;

class ViewLocationInvestment extends ViewRecord
{
    protected static string $resource = LocationInvestmentResource::class;

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
                Section::make('Location Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('location.name')
                                    ->label('Location Name')
                                    ->size('lg')
                                    ->weight('bold'),
                                
                                TextEntry::make('location.city')
                                    ->label('City'),
                            ]),

                        TextEntry::make('location.address')
                            ->label('Address')
                            ->columnSpanFull(),
                    ]),

                Section::make('Investment Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_invested')
                                    ->label('Total Invested')
                                    ->money('GBP')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('success'),
                                
                                TextEntry::make('investment_limit')
                                    ->label('Investment Limit')
                                    ->money('GBP')
                                    ->size('lg')
                                    ->weight('bold'),
                                
                                TextEntry::make('remaining_amount')
                                    ->label('Remaining Available')
                                    ->state(function ($record) {
                                        return max(0, $record->investment_limit - $record->total_invested);
                                    })
                                    ->money('GBP')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->color('warning'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_investors')
                                    ->label('Total Investors')
                                    ->badge()
                                    ->color('primary'),
                                
                                TextEntry::make('progress_percentage')
                                    ->label('Investment Progress')
                                    ->state(function ($record) {
                                        if ($record->investment_limit == 0) return '0%';
                                        $percentage = ($record->total_invested / $record->investment_limit) * 100;
                                        return number_format($percentage, 1) . '%';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if ($record->investment_limit == 0) return 'gray';
                                        $percentage = ($record->total_invested / $record->investment_limit) * 100;
                                        if ($percentage >= 100) return 'success';
                                        if ($percentage >= 75) return 'warning';
                                        if ($percentage >= 50) return 'info';
                                        return 'gray';
                                    }),
                                
                                TextEntry::make('is_open_for_investment')
                                    ->label('Investment Status')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Open' : 'Closed')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                            ]),
                    ]),

                Section::make('Recent Investments')
                    ->schema([
                        TextEntry::make('recent_investments')
                            ->label('')
                            ->state(function ($record) {
                                $investments = $record->location->investments()
                                    ->where('status', 'completed')
                                    ->with('user')
                                    ->latest('invested_at')
                                    ->limit(5)
                                    ->get();

                                if ($investments->isEmpty()) {
                                    return 'No investments yet';
                                }

                                $output = '';
                                foreach ($investments as $investment) {
                                    $output .= "• £{$investment->amount} from {$investment->user->name} ({$investment->invested_at->format('M j, Y')})\n";
                                }
                                
                                return trim($output);
                            })
                            ->prose()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}