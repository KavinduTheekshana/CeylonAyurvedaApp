<?php

namespace App\Filament\Widgets;

use App\Models\Investment;
use App\Models\Location;
use App\Models\LocationInvestment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InvestmentOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get current period stats
        $totalInvestments = Investment::where('status', 'completed')->sum('amount');
        $totalInvestmentCount = Investment::where('status', 'completed')->count();
        $pendingInvestments = Investment::where('status', 'pending')->count();
        $totalInvestors = Investment::where('status', 'completed')->distinct('user_id')->count();
        $activeLocations = LocationInvestment::where('is_open_for_investment', true)->count();

        // Get previous period for comparison (last 30 days vs previous 30 days)
        $thisMonth = Investment::where('status', 'completed')
            ->whereDate('invested_at', '>=', now()->subDays(30))
            ->sum('amount');

        $lastMonth = Investment::where('status', 'completed')
            ->whereBetween('invested_at', [
                now()->subDays(60),
                now()->subDays(30)
            ])
            ->sum('amount');

        $monthlyChange = $lastMonth > 0 
            ? (($thisMonth - $lastMonth) / $lastMonth) * 100 
            : ($thisMonth > 0 ? 100 : 0);

        // Get today's investments
        $todayInvestments = Investment::where('status', 'completed')
            ->whereDate('invested_at', today())
            ->sum('amount');

        // Get average investment amount
        $averageInvestment = Investment::where('status', 'completed')
            ->avg('amount') ?? 0;

        // Get fully funded locations
        $fullyFundedLocations = LocationInvestment::whereRaw('total_invested >= investment_limit')->count();

        return [
            Stat::make('Total Investments', '£' . number_format($totalInvestments, 2))
                ->description('Total amount invested')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->getInvestmentChart()),

            Stat::make('This Month', '£' . number_format($thisMonth, 2))
                ->description($monthlyChange >= 0 ? "+{$monthlyChange}% increase" : "{$monthlyChange}% decrease")
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger'),

            Stat::make('Total Investors', $totalInvestors)
                ->description("{$totalInvestmentCount} total investments")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Pending Investments', $pendingInvestments)
                ->description('Awaiting completion')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingInvestments > 0 ? 'warning' : 'success')
                ->url($pendingInvestments > 0 ? '/admin/investments?tableFilters[status][values][0]=pending' : null),

            Stat::make("Today's Investments", '£' . number_format($todayInvestments, 2))
                ->description('Investments received today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Average Investment', '£' . number_format($averageInvestment, 2))
                ->description('Per investment')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }

    private function getInvestmentChart(): array
    {
        // Get investment data for the last 7 days
        $data = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            return Investment::where('status', 'completed')
                ->whereDate('invested_at', $date)
                ->sum('amount');
        });

        return $data->toArray();
    }

    protected static ?int $sort = 1;
}