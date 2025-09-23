{{-- resources/views/filament/admin/user-treatment-history.blade.php --}}
<div class="space-y-4">
    {{-- Simple Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ $user->name }}'s Treatment History
        </h3>
        <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">
            {{ $user->email }} • {{ $treatmentHistories->count() }} treatments
        </p>
    </div>

    @if($treatmentHistories->isEmpty())
        {{-- Empty State --}}
        <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg">
            <div class="text-6xl mb-4">📋</div>
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No treatments yet</h4>
            <p class="text-gray-500">Treatment records will appear here once available.</p>
        </div>
    @else
        {{-- Quick Stats --}}
        <div class="flex gap-4">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center border flex-1">
                <div class="text-2xl font-bold text-blue-600">{{ $treatmentHistories->count() }}</div>
                <div class="text-sm text-gray-500">Total</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center border flex-1">
                <div class="text-2xl font-bold text-green-600">{{ $treatmentHistories->where('patient_condition', 'improved')->count() }}</div>
                <div class="text-sm text-gray-500">Improved</div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg text-center border flex-1">
                <div class="text-2xl font-bold text-purple-600">{{ $treatmentHistories->pluck('service')->unique('id')->count() }}</div>
                <div class="text-sm text-gray-500">Services</div>
            </div>
        </div>

        {{-- Treatment List --}}
        <div class="space-y-3 max-h-96 overflow-y-auto">
            @foreach($treatmentHistories as $history)
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border hover:shadow-sm transition-shadow">
                    {{-- Main Info --}}
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">
                                {{ $history->service->title ?? 'Treatment' }}
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $history->therapist->name ?? 'Therapist' }} • 
                                {{ $history->treatment_completed_at->format('M j, Y') }}
                            </p>
                        </div>
                        
                        {{-- Status Badge --}}
                        @if($history->patient_condition)
                            <span class="px-2 py-1 text-xs rounded-full
                                {{ $history->patient_condition === 'improved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                {{ $history->patient_condition === 'same' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                {{ $history->patient_condition === 'worse' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                            ">
                                {{ ucfirst($history->patient_condition) }}
                            </span>
                        @endif
                    </div>

                    {{-- Pain Levels (Simple) --}}
                    @if($history->pain_level_before || $history->pain_level_after)
                        <div class="flex gap-4 mb-3 text-sm">
                            @if($history->pain_level_before)
                                <span class="text-red-600">Before: {{ $history->pain_level_before }}/10</span>
                            @endif
                            @if($history->pain_level_after)
                                <span class="text-green-600">After: {{ $history->pain_level_after }}/10</span>
                            @endif
                        </div>
                    @endif

                    {{-- Areas Treated (Compact) --}}
                    @if($history->areas_treated && is_array($history->areas_treated) && count($history->areas_treated) > 0)
                        <div class="mb-2">
                            <span class="text-xs text-gray-500">Areas: </span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ implode(', ', $history->areas_treated) }}
                            </span>
                        </div>
                    @endif

                    {{-- Notes (Collapsible) --}}
                    @if($history->treatment_notes || $history->observations || $history->recommendations)
                        <details class="mt-3">
                            <summary class="text-sm text-blue-600 cursor-pointer hover:text-blue-700">
                                View Notes
                            </summary>
                            <div class="mt-2 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                @if($history->treatment_notes)
                                    <div>
                                        <strong>Treatment:</strong> {{ $history->treatment_notes }}
                                    </div>
                                @endif
                                @if($history->observations)
                                    <div>
                                        <strong>Observations:</strong> {{ $history->observations }}
                                    </div>
                                @endif
                                @if($history->recommendations)
                                    <div>
                                        <strong>Recommendations:</strong> {{ $history->recommendations }}
                                    </div>
                                @endif
                                @if($history->next_treatment_plan)
                                    <div>
                                        <strong>Next Plan:</strong> {{ $history->next_treatment_plan }}
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif

                    {{-- Footer (Minimal) --}}
                    <div class="flex justify-between items-center mt-3 pt-2 border-t text-xs text-gray-400">
                        <span>#{{ $history->id }}</span>
                        @if($history->booking)
                            <span>{{ $history->booking->reference }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>