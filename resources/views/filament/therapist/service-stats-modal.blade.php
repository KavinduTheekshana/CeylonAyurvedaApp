{{-- resources/views/filament/therapist/service-stats-modal.blade.php --}}
<div class="space-y-6">
    <div class="flex items-center space-x-4">
        @if($service->image)
            <img src="{{ asset('storage/' . $service->image) }}" alt="{{ $service->title }}" class="w-16 h-16 object-cover rounded-lg">
        @endif
        <div>
            <h3 class="text-lg font-semibold">{{ $service->title }}</h3>
            <p class="text-sm text-gray-600">{{ $service->treatment->name }}</p>
            <p class="text-sm text-gray-500">{{ $service->subtitle }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="bg-blue-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['total_bookings'] }}</div>
            <div class="text-sm text-blue-600">Total Bookings</div>
        </div>

        <div class="bg-green-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-green-600">{{ $stats['this_month'] }}</div>
            <div class="text-sm text-green-600">This Month</div>
        </div>

        <div class="bg-purple-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['completed'] }}</div>
            <div class="text-sm text-purple-600">Completed</div>
        </div>

        <div class="bg-yellow-50 p-4 rounded-lg">
            <div class="text-2xl font-bold text-yellow-600">£{{ number_format($stats['revenue'], 2) }}</div>
            <div class="text-sm text-yellow-600">Total Revenue</div>
        </div>
    </div>

    <div class="space-y-2">
        <h4 class="font-medium">Service Details</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Price:</span>
                <span class="font-medium">£{{ number_format($service->price, 2) }}</span>
            </div>
            <div>
                <span class="text-gray-600">Duration:</span>
                <span class="font-medium">{{ $service->duration }} minutes</span>
            </div>
            <div>
                <span class="text-gray-600">Status:</span>
                <span class="font-medium {{ $service->status ? 'text-green-600' : 'text-red-600' }}">
                    {{ $service->status ? 'Active' : 'Inactive' }}
                </span>
            </div>
            @if($service->discount_price)
            <div>
                <span class="text-gray-600">Discount Price:</span>
                <span class="font-medium text-red-600">£{{ number_format($service->discount_price, 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    @if($service->description)
    <div class="space-y-2">
        <h4 class="font-medium">Description</h4>
        <p class="text-sm text-gray-600">{{ $service->description }}</p>
    </div>
    @endif

    @if($service->benefits)
    <div class="space-y-2">
        <h4 class="font-medium">Benefits</h4>
        <p class="text-sm text-gray-600">{{ $service->benefits }}</p>
    </div>
    @endif
</div>