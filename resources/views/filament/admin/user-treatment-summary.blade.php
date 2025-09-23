{{-- resources/views/filament/admin/user-treatment-summary.blade.php --}}
<div class="space-y-4">
    {{-- Simple Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ $user->name }}'s Treatment Summary
        </h3>
        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">
            Progress overview and key insights
        </p>
    </div>

    {{-- Key Stats --}}
    <div class="flex gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center border flex-1">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['total_treatments'] }}</div>
            <div class="text-sm text-gray-500">Total Treatments</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center border flex-1">
            <div class="text-2xl font-bold text-green-600">{{ $stats['improved_conditions'] }}</div>
            <div class="text-sm text-gray-500">Improved</div>
            @if($stats['total_treatments'] > 0)
                <div class="text-xs text-green-600 mt-1">
                    {{ round(($stats['improved_conditions'] / $stats['total_treatments']) * 100, 1) }}% success
                </div>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center border flex-1">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['unique_services'] }}</div>
            <div class="text-sm text-gray-500">Services Used</div>
        </div>
    </div>

    {{-- Progress Overview --}}
    @if($stats['total_treatments'] > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
            <h4 class="font-medium text-gray-900 dark:text-white mb-3">Treatment Outcomes</h4>
            
            {{-- Simple Progress Bar --}}
            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-6 mb-3">
                <div class="flex h-full rounded-full overflow-hidden">
                    @if($stats['improved_conditions'] > 0)
                        <div class="bg-green-500 flex items-center justify-center text-white text-xs font-medium"
                             style="width: {{ ($stats['improved_conditions'] / $stats['total_treatments']) * 100 }}%">
                            @if(($stats['improved_conditions'] / $stats['total_treatments']) * 100 >= 15)
                                {{ $stats['improved_conditions'] }}
                            @endif
                        </div>
                    @endif
                    @if($stats['same_conditions'] > 0)
                        <div class="bg-yellow-500 flex items-center justify-center text-white text-xs font-medium"
                             style="width: {{ ($stats['same_conditions'] / $stats['total_treatments']) * 100 }}%">
                            @if(($stats['same_conditions'] / $stats['total_treatments']) * 100 >= 15)
                                {{ $stats['same_conditions'] }}
                            @endif
                        </div>
                    @endif
                    @if($stats['worse_conditions'] > 0)
                        <div class="bg-red-500 flex items-center justify-center text-white text-xs font-medium"
                             style="width: {{ ($stats['worse_conditions'] / $stats['total_treatments']) * 100 }}%">
                            @if(($stats['worse_conditions'] / $stats['total_treatments']) * 100 >= 15)
                                {{ $stats['worse_conditions'] }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Colorful Legend --}}
            <div class="flex justify-center gap-6 text-sm">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-500 rounded-full mr-2"></div>
                    <span class="text-green-600 font-medium">
                        Improved ({{ $stats['improved_conditions'] }})
                    </span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-500 rounded-full mr-2"></div>
                    <span class="text-yellow-600 font-medium">
                        Same ({{ $stats['same_conditions'] }})
                    </span>
                </div>
                @if($stats['worse_conditions'] > 0)
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-red-500 rounded-full mr-2"></div>
                        <span class="text-red-600 font-medium">
                            Worse ({{ $stats['worse_conditions'] }})
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Pain Level & Timeline Row --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Pain Analysis --}}
        @if($stats['average_pain_reduction'] !== null)
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border text-center">
                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Pain Level Change</h4>
                <div class="text-3xl font-bold {{ $stats['average_pain_reduction'] > 0 ? 'text-green-500' : ($stats['average_pain_reduction'] < 0 ? 'text-red-500' : 'text-yellow-500') }}">
                    {{ $stats['average_pain_reduction'] > 0 ? '-' : ($stats['average_pain_reduction'] < 0 ? '+' : '') }}{{ abs($stats['average_pain_reduction']) }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    @if($stats['average_pain_reduction'] > 0)
                        <span class="text-green-600">Average decrease</span>
                    @elseif($stats['average_pain_reduction'] < 0)
                        <span class="text-red-600">Average increase</span>
                    @else
                        <span class="text-yellow-600">No change</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Treatment Period --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border text-center">
            <h4 class="font-medium text-gray-900 dark:text-white mb-2">Treatment Period</h4>
            @if($stats['first_treatment'] && $stats['latest_treatment'])
                @php
                    $days = max(1, (int) \Carbon\Carbon::parse($stats['first_treatment'])->diffInDays(\Carbon\Carbon::parse($stats['latest_treatment'])));
                    $months = (int) \Carbon\Carbon::parse($stats['first_treatment'])->diffInMonths(\Carbon\Carbon::parse($stats['latest_treatment']));
                @endphp
                <div class="text-3xl font-bold text-blue-600">
                    @if($months >= 1)
                        {{ $months }}
                    @else
                        {{ $days }}
                    @endif
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    @if($months >= 1)
                        {{ $months == 1 ? 'month' : 'months' }} total
                    @else
                        {{ $days == 1 ? 'day' : 'days' }} total
                    @endif
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    {{ \Carbon\Carbon::parse($stats['first_treatment'])->format('M Y') }} - {{ \Carbon\Carbon::parse($stats['latest_treatment'])->format('M Y') }}
                </div>
            @else
                <div class="text-gray-500">No treatments yet</div>
            @endif
        </div>
    </div>

    {{-- Most Treated Areas --}}
    @if(!empty($mostTreatedAreas))
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
            <h4 class="font-medium text-gray-900 dark:text-white mb-3">Most Treated Areas</h4>
            <div class="space-y-2">
                @foreach(array_slice($mostTreatedAreas, 0, 3, true) as $area => $count)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-900 dark:text-white text-sm">{{ $area }}</span>
                        <div class="flex items-center gap-2">
                            <div class="w-20 bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full"
                                     style="width: {{ ($count / max(array_values($mostTreatedAreas))) * 100 }}%">
                                </div>
                            </div>
                            <span class="text-sm text-gray-500 w-8 text-right">{{ $count }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Quick Insights --}}
    @if($stats['total_treatments'] > 0)
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
            <h4 class="font-medium text-gray-900 dark:text-white mb-2 flex items-center">
                💡 Quick Insights
            </h4>
            <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                <div>• {{ round(($stats['improved_conditions'] / $stats['total_treatments']) * 100, 1) }}% success rate across all treatments</div>
                @if($stats['unique_therapists'] > 1)
                    <div>• Worked with {{ $stats['unique_therapists'] }} different therapists</div>
                @endif
                @if($stats['average_pain_reduction'] !== null && $stats['average_pain_reduction'] > 2)
                    <div>• Excellent pain management with {{ $stats['average_pain_reduction'] }} point average reduction</div>
                @endif
            </div>
        </div>
    @endif
</div>