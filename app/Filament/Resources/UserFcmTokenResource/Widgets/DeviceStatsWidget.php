<?php

namespace App\Filament\Resources\UserFcmTokenResource\Widgets;

use App\Models\UserFcmToken;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeviceStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalActive = UserFcmToken::where('is_active', true)->count();
        $totalInactive = UserFcmToken::where('is_active', false)->count();
        $androidDevices = UserFcmToken::where('is_active', true)
            ->where('device_type', 'android')
            ->count();
        $iosDevices = UserFcmToken::where('is_active', true)
            ->where('device_type', 'ios')
            ->count();
        $uniqueUsers = UserFcmToken::where('is_active', true)
            ->distinct('user_id')
            ->count('user_id');

        $recentlyUsed = UserFcmToken::where('is_active', true)
            ->where('last_used_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Total Active Devices', $totalActive)
                ->description('Devices ready to receive notifications')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color('success')
                ->chart([7, 12, 15, 18, 22, 25, $totalActive]),

            Stat::make('Unique Users', $uniqueUsers)
                ->description('Users with registered devices')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Android Devices', $androidDevices)
                ->description($androidDevices > 0 ? round(($androidDevices / $totalActive) * 100, 1) . '% of total' : 'No devices')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color('success'),

            Stat::make('iOS Devices', $iosDevices)
                ->description($iosDevices > 0 ? round(($iosDevices / $totalActive) * 100, 1) . '% of total' : 'No devices')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color('primary'),

            Stat::make('Recently Active', $recentlyUsed)
                ->description('Used in last 7 days')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Inactive Devices', $totalInactive)
                ->description('Deactivated or invalid tokens')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
