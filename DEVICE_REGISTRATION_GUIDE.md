# Device Registration Guide

## 📱 How to Check Registered Devices

You now have multiple ways to check how many devices are registered for push notifications!

---

## 🎯 Method 1: Admin Panel (Filament)

### Access the Admin Panel

1. Log in to your Filament admin panel: `https://your-domain.com/admin`

2. Navigate to **"Push Notifications" → "Registered Devices"**

3. You'll see a beautiful dashboard with:

#### **📊 Statistics Overview**
- **Total Active Devices** - Devices ready to receive notifications
- **Unique Users** - How many users have registered devices
- **Android Devices** - Count and percentage
- **iOS Devices** - Count and percentage
- **Recently Active** - Devices used in last 7 days
- **Inactive Devices** - Deactivated or invalid tokens

#### **📋 Device List**
- User name and email
- Device type (Android/iOS)
- Active status
- Last used time
- Registration date
- FCM token (copyable)

#### **⚡ Quick Actions**
- **Activate/Deactivate** individual devices
- **View** device details
- **Delete** invalid devices
- **Bulk actions** on multiple devices

#### **🔍 Filters**
- Filter by device type (Android/iOS)
- Filter by status (Active/Inactive)
- Filter by recently used (last 7 days)
- Search by user name or email

---

## 🚀 Method 2: API Endpoints

### 1. Get Device Statistics

**Endpoint:** `GET /api/fcm-token/stats`

**Headers:**
```
Authorization: Bearer {your_auth_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_active_devices": 45,
    "total_inactive_devices": 12,
    "android_devices": 30,
    "ios_devices": 15,
    "unique_users": 38,
    "average_devices_per_user": 1.18
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/fcm-token/stats" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

### 2. Get Your Own Registered Devices

**Endpoint:** `GET /api/fcm-token/my-devices`

**Headers:**
```
Authorization: Bearer {your_auth_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "devices": [
      {
        "id": 1,
        "device_type": "ios",
        "device_id": "ios-1733857200-abc123",
        "is_active": true,
        "last_used_at": "2025-12-10 14:30:00",
        "created_at": "2025-12-01 10:00:00"
      },
      {
        "id": 2,
        "device_type": "android",
        "device_id": "android-1733857300-def456",
        "is_active": true,
        "last_used_at": "2025-12-09 16:45:00",
        "created_at": "2025-11-28 08:30:00"
      }
    ],
    "total": 2,
    "active": 2
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/fcm-token/my-devices" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## 🔧 Method 3: Laravel Tinker

Quick check via command line:

```bash
php artisan tinker
```

### Check Total Active Devices
```php
\App\Models\UserFcmToken::where('is_active', true)->count();
// Output: 45
```

### Check Devices by Platform
```php
// Android
\App\Models\UserFcmToken::where('is_active', true)
    ->where('device_type', 'android')
    ->count();

// iOS
\App\Models\UserFcmToken::where('is_active', true)
    ->where('device_type', 'ios')
    ->count();
```

### Check Unique Users with Devices
```php
\App\Models\UserFcmToken::where('is_active', true)
    ->distinct('user_id')
    ->count('user_id');
```

### Get All Device Details
```php
\App\Models\UserFcmToken::with('user:id,name,email')
    ->where('is_active', true)
    ->get(['id', 'user_id', 'device_type', 'last_used_at']);
```

### Check Recently Used Devices (Last 7 Days)
```php
\App\Models\UserFcmToken::where('is_active', true)
    ->where('last_used_at', '>=', now()->subDays(7))
    ->count();
```

### Check Specific User's Devices
```php
$user = \App\Models\User::find(1);
$user->fcmTokens()->where('is_active', true)->get();
```

---

## 📊 Method 4: Direct Database Query

### MySQL/PostgreSQL Query

```sql
-- Total active devices
SELECT COUNT(*) as total_active
FROM user_fcm_tokens
WHERE is_active = 1;

-- Devices by platform
SELECT
    device_type,
    COUNT(*) as count
FROM user_fcm_tokens
WHERE is_active = 1
GROUP BY device_type;

-- Users with devices
SELECT
    users.name,
    users.email,
    COUNT(user_fcm_tokens.id) as device_count
FROM users
JOIN user_fcm_tokens ON users.id = user_fcm_tokens.user_id
WHERE user_fcm_tokens.is_active = 1
GROUP BY users.id
ORDER BY device_count DESC;

-- Recently used devices (last 7 days)
SELECT COUNT(*) as recently_active
FROM user_fcm_tokens
WHERE is_active = 1
AND last_used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Complete device overview
SELECT
    u.name as user_name,
    u.email,
    uft.device_type,
    uft.is_active,
    uft.last_used_at,
    uft.created_at
FROM user_fcm_tokens uft
JOIN users u ON uft.user_id = u.id
ORDER BY uft.created_at DESC;
```

---

## 🧪 Testing Your Setup

### Test 1: Check If Any Devices Are Registered

```bash
php artisan tinker
```

```php
$count = \App\Models\UserFcmToken::where('is_active', true)->count();
echo "Total active devices: $count\n";

if ($count > 0) {
    echo "✅ Devices are registered!\n";
} else {
    echo "❌ No devices registered yet. Users need to log in to the mobile app.\n";
}
```

### Test 2: Verify User Has Device

```php
$user = \App\Models\User::first();
$hasDevice = $user->fcmTokens()->where('is_active', true)->exists();

if ($hasDevice) {
    echo "✅ User {$user->name} has registered device(s)\n";
} else {
    echo "❌ User {$user->name} has no registered devices\n";
}
```

### Test 3: Check Device Activity

```php
$activeLastWeek = \App\Models\UserFcmToken::where('is_active', true)
    ->where('last_used_at', '>=', now()->subDays(7))
    ->count();

$total = \App\Models\UserFcmToken::where('is_active', true)->count();

echo "Active last week: $activeLastWeek / $total\n";
echo "Activity rate: " . round(($activeLastWeek / max($total, 1)) * 100, 1) . "%\n";
```

---

## 📈 Understanding the Numbers

### What Each Metric Means:

1. **Total Active Devices**
   - Devices that can receive notifications
   - Includes all devices with valid FCM tokens

2. **Unique Users**
   - Number of users who have at least one registered device
   - One user can have multiple devices (phone + tablet)

3. **Average Devices Per User**
   - Most users have 1 device
   - Users with 2+ devices might have multiple phones or tablets

4. **Recently Active**
   - Devices that received a notification in the last 7 days
   - High number = good engagement

5. **Inactive Devices**
   - Devices with invalid/expired tokens
   - Automatically deactivated when FCM returns errors
   - Users need to re-login to re-register

---

## 🔔 Expected Device Registration Flow

```
User opens app → Requests notification permission
                           ↓
                  User grants permission
                           ↓
                  FCM token generated
                           ↓
                  User logs in
                           ↓
         Token sent to backend (POST /api/fcm-token)
                           ↓
                Saved to database ✅
                           ↓
              Device appears in admin panel
                           ↓
            Device can receive notifications!
```

---

## 🐛 Troubleshooting

### No Devices Showing Up?

**Check 1: Users Must Be Logged In**
```bash
# Check if you have any users
php artisan tinker
\App\Models\User::count()
```

**Check 2: Mobile App Must Register Token**
```bash
# Check if any tokens were ever registered
\App\Models\UserFcmToken::count()
# If 0, mobile app hasn't registered any tokens yet
```

**Check 3: Check API Logs**
```bash
tail -f storage/logs/laravel.log | grep "FCM"
# Should see "FCM token registered successfully"
```

### Devices Showing as Inactive?

**Reason 1: Token Expired/Invalid**
- FCM automatically invalidates old tokens
- User needs to re-login to mobile app

**Reason 2: App Uninstalled**
- When user uninstalls app, token becomes invalid
- Backend automatically deactivates on next notification attempt

**Reason 3: User Logged Out**
- Some apps deactivate token on logout
- Check your mobile app's logout logic

---

## 📱 Mobile App Integration

To see devices in the admin panel, your mobile app must:

1. **Register FCM token on login:**
```javascript
await notificationService.registerTokenWithBackend(authToken);
```

2. **Send correct data:**
```json
{
  "fcm_token": "ExponentPushToken[...]",
  "device_type": "ios",
  "device_id": "unique-device-id"
}
```

3. **Keep token updated:**
- Re-register on token refresh
- Re-register after app reinstall

---

## 🎯 Quick Stats Dashboard

### Create a Simple Stats Command

Create `app/Console/Commands/DeviceStats.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserFcmToken;

class DeviceStats extends Command
{
    protected $signature = 'devices:stats';
    protected $description = 'Show device registration statistics';

    public function handle()
    {
        $active = UserFcmToken::where('is_active', true)->count();
        $android = UserFcmToken::where('is_active', true)->where('device_type', 'android')->count();
        $ios = UserFcmToken::where('is_active', true)->where('device_type', 'ios')->count();
        $users = UserFcmToken::where('is_active', true)->distinct('user_id')->count('user_id');

        $this->info("📱 Device Statistics");
        $this->line("");
        $this->line("Total Active: {$active}");
        $this->line("Android: {$android}");
        $this->line("iOS: {$ios}");
        $this->line("Unique Users: {$users}");

        return 0;
    }
}
```

**Usage:**
```bash
php artisan devices:stats
```

---

## 🎉 Summary

You now have **4 ways** to check registered devices:

1. ✅ **Admin Panel** - Beautiful UI with stats and management
2. ✅ **API Endpoints** - Programmatic access
3. ✅ **Laravel Tinker** - Quick command-line queries
4. ✅ **Direct Database** - SQL queries

**Most users will use the Admin Panel** because it's the easiest and most visual way to monitor device registrations!

Happy monitoring! 📊
