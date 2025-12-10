# Testing Push Notifications

## Quick Test Commands

### 1. Test Booking Creation Notification

Create a test booking via Tinker:
```bash
php artisan tinker
```

```php
// In Tinker:
$user = \App\Models\User::first(); // Get first user
$service = \App\Models\Service::first(); // Get first service
$therapist = \App\Models\Therapist::first(); // Get first therapist

$booking = \App\Models\Booking::create([
    'user_id' => $user->id,
    'service_id' => $service->id,
    'therapist_id' => $therapist->id,
    'date' => now()->addDays(3),
    'time' => '14:00:00',
    'name' => $user->name,
    'email' => $user->email,
    'phone' => '1234567890',
    'address_line1' => '123 Test St',
    'city' => 'London',
    'postcode' => 'SW1A 1AA',
    'price' => 50.00,
    'original_price' => 50.00,
    'reference' => strtoupper(\Illuminate\Support\Str::random(8)),
    'status' => 'confirmed', // This will trigger notification
    'payment_status' => 'paid',
]);

// Manually load relationships and fire event
$booking->load(['service', 'therapist', 'location']);
event(new \App\Events\BookingCreated($booking));
```

### 2. Test Status Change Notification

Update an existing booking's status:
```bash
php artisan tinker
```

```php
// In Tinker:
$booking = \App\Models\Booking::where('user_id', '!=', null)->first();
$booking->load(['service', 'therapist', 'user']);

// Change status - this will automatically trigger the observer
$booking->status = 'confirmed'; // or 'completed', 'cancelled', etc.
$booking->save();

// Check logs to see if notification was sent
```

### 3. Test Cancellation Notification

```bash
php artisan tinker
```

```php
// In Tinker:
$booking = \App\Models\Booking::where('user_id', '!=', null)
    ->where('status', 'confirmed')
    ->first();

$booking->load(['service', 'therapist', 'user']);
$booking->status = 'cancelled';
$booking->save();

event(new \App\Events\BookingCancelled($booking));
```

### 4. Test Admin Broadcast Notification

1. Go to your Filament admin panel
2. Navigate to "Push Notifications"
3. Create a new notification
4. Click "Send to All Users"
5. Check the logs

OR via Tinker:
```php
$notification = \App\Models\Notification::create([
    'title' => 'Test Notification',
    'message' => 'This is a test notification from the backend!',
    'type' => 'promotional',
    'is_active' => true,
]);

// Dispatch the job
\App\Jobs\SendBroadcastNotificationJob::dispatch($notification);
```

## Check if User Has FCM Tokens

```bash
php artisan tinker
```

```php
// Get all active FCM tokens
$tokens = \App\Models\UserFcmToken::where('is_active', true)->get();
$tokens->count(); // Should be > 0

// Check specific user's tokens
$user = \App\Models\User::first();
$userTokens = \App\Models\UserFcmToken::where('user_id', $user->id)
    ->where('is_active', true)
    ->get();

echo "User has " . $userTokens->count() . " active tokens\n";
```

## Manual Notification Test

Send a test notification directly:
```bash
php artisan tinker
```

```php
$user = \App\Models\User::first();
$tokens = \App\Models\UserFcmToken::where('user_id', $user->id)
    ->where('is_active', true)
    ->pluck('fcm_token')
    ->toArray();

if (!empty($tokens)) {
    $fcmService = app(\App\Services\FCMService::class);

    foreach ($tokens as $token) {
        try {
            $result = $fcmService->sendToDevice(
                $token,
                [
                    'title' => 'Test Notification',
                    'body' => 'This is a manual test notification!'
                ],
                [
                    'type' => 'test',
                    'test_id' => '123'
                ]
            );
            echo "Notification sent successfully!\n";
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "No FCM tokens found for user.\n";
}
```

## Check Logs

### View Recent Logs
```bash
tail -50 storage/logs/laravel.log
```

### Watch Logs in Real-Time
```bash
tail -f storage/logs/laravel.log
```

### Filter for Notification Logs
```bash
grep -i "notification" storage/logs/laravel.log | tail -20
```

### Check FCM Errors
```bash
grep -i "fcm" storage/logs/laravel.log | tail -20
```

## Database Checks

### Check Active FCM Tokens
```sql
SELECT id, user_id, device_type, is_active, last_used_at, created_at
FROM user_fcm_tokens
WHERE is_active = 1
ORDER BY created_at DESC;
```

### Check Recent Bookings with Users
```sql
SELECT id, user_id, reference, status, created_at
FROM bookings
WHERE user_id IS NOT NULL
ORDER BY created_at DESC
LIMIT 10;
```

### Check Sent Notifications
```sql
SELECT id, title, message, type, sent_at, total_sent
FROM notifications
WHERE sent_at IS NOT NULL
ORDER BY sent_at DESC
LIMIT 10;
```

## Common Issues & Solutions

### Issue: Notifications Not Received

**Check 1: FCM Token Exists**
```php
php artisan tinker
$user = \App\Models\User::find(1);
$tokens = $user->fcmTokens()->where('is_active', true)->count();
echo "Active tokens: $tokens\n";
```

**Check 2: Firebase Credentials**
```bash
ls -la storage/app/firebase-service-account.json
# Should show the file exists and has proper permissions
```

**Check 3: Booking Has User**
```php
$booking = \App\Models\Booking::find(1);
echo "User ID: " . ($booking->user_id ?? 'NULL') . "\n";
```

**Check 4: Events Are Registered**
```bash
php artisan event:list | grep Booking
# Should show BookingCreated and BookingCancelled events
```

### Issue: Invalid Token Errors

Tokens are automatically deactivated when invalid. Check logs:
```bash
grep "Deactivated invalid FCM token" storage/logs/laravel.log
```

User needs to re-register their token from the React Native app.

### Issue: Duplicate Notifications

Check if events are being fired multiple times:
```bash
grep "Booking notification sent" storage/logs/laravel.log | tail -20
```

## Testing from React Native App

### 1. Register Token on App Launch
```javascript
// Your app should already do this
import messaging from '@react-native-firebase/messaging';

async function registerToken() {
  const token = await messaging().getToken();

  // Send to backend
  await axios.post('/api/fcm-token', {
    fcm_token: token,
    device_type: Platform.OS, // 'ios' or 'android'
    device_id: uniqueDeviceId
  });
}
```

### 2. Listen for Notifications
```javascript
// Foreground notifications
messaging().onMessage(async remoteMessage => {
  console.log('Notification received!', remoteMessage);

  const { type, booking_id } = remoteMessage.data;

  // Handle based on type
  if (type === 'booking_confirmed') {
    navigation.navigate('BookingDetails', { id: booking_id });
  }
});
```

## Performance Testing

### Send 100 Test Notifications
```bash
php artisan tinker
```

```php
$fcmService = app(\App\Services\FCMService::class);
$tokens = \App\Models\UserFcmToken::where('is_active', true)
    ->limit(100)
    ->pluck('fcm_token')
    ->toArray();

$start = microtime(true);
foreach ($tokens as $index => $token) {
    $fcmService->sendToDevice(
        $token,
        ['title' => "Test $index", 'body' => 'Performance test'],
        ['type' => 'test']
    );
}
$duration = microtime(true) - $start;
echo "Sent 100 notifications in $duration seconds\n";
```

## Clear Caches After Changes

If you make any changes to listeners or observers:
```bash
php artisan config:clear
php artisan cache:clear
php artisan event:clear
php artisan optimize:clear
```

## Success Indicators

✅ You should see these log messages when notifications work:
- `"Notification sent to user"`
- `"User booking notification sent successfully"`
- `"Status change notification sent"`
- `"Booking notification sent successfully"`

✅ Your React Native app should receive:
- Push notification banner
- Notification data payload
- Ability to tap and navigate to booking

## Next Steps After Testing

1. Monitor logs for first few days
2. Check FCM token refresh rate
3. Verify users are receiving notifications
4. Gather user feedback
5. Adjust notification content if needed

Happy Testing! 🎉
