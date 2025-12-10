# Push Notification Flow Diagram

## 📊 Complete System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER'S MOBILE DEVICE                        │
│                       (React Native App)                            │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ 1. App launches
                             │ 2. Request FCM token
                             ▼
                    ┌────────────────┐
                    │  Firebase SDK  │
                    │  (FCM Client)  │
                    └────────┬───────┘
                             │
                             │ 3. Returns FCM token
                             │    "ExponentPushToken[...]"
                             ▼
                    ┌────────────────┐
                    │  Mobile App    │
                    │  sends token   │
                    └────────┬───────┘
                             │
                             │ 4. POST /api/fcm-token
                             │    {fcm_token, device_type, device_id}
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     YOUR LARAVEL BACKEND                            │
│                  (Already Configured! ✅)                            │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ 5. Saves to database
                             ▼
                ┌────────────────────────────┐
                │  user_fcm_tokens table     │
                │  - user_id                 │
                │  - fcm_token               │
                │  - device_type             │
                │  - is_active               │
                └────────────┬───────────────┘
                             │
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
   [Trigger 1]         [Trigger 2]          [Trigger 3]

1️⃣  Booking Created    2️⃣  Status Changed    3️⃣  Admin Broadcast
     or Cancelled          by Admin             Notification
        │                    │                    │
        │                    │                    │
        ▼                    ▼                    ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│ BookingCreated│    │BookingObserver│    │ Filament UI   │
│    Event      │    │   detects     │    │ "Send to All" │
│               │    │   change      │    │    button     │
└───────┬───────┘    └───────┬───────┘    └───────┬───────┘
        │                    │                    │
        │                    │                    │
        ▼                    ▼                    ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│  Listener:    │    │UserNotification│   │SendBroadcast  │
│NotifyUserOf   │    │Service sends   │    │NotificationJob│
│Confirmation   │    │  notification  │    │               │
└───────┬───────┘    └───────┬───────┘    └───────┬───────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             │
                             │ 6. Gets FCM tokens from database
                             ▼
                    ┌────────────────┐
                    │   FCMService   │
                    │  prepares msg  │
                    └────────┬───────┘
                             │
                             │ 7. Sends via Firebase Admin SDK
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      FIREBASE CLOUD                                 │
│                   MESSAGING (FCM) SERVER                            │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ 8. Firebase routes notification
                             │    to device using FCM token
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
    [iOS Device]        [Android Device]     [Other Devices]
        │                    │                    │
        │                    │                    │
        ▼                    ▼                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                  USER RECEIVES NOTIFICATION! 📱                     │
│                                                                     │
│  ┌───────────────────────────────────────────────┐                │
│  │  🔔  Booking Confirmed!                       │                │
│  │  Your booking for Ayurvedic Massage on       │                │
│  │  Dec 15 at 2:00 PM has been confirmed.       │                │
│  │                                         [Tap] │                │
│  └───────────────────────────────────────────────┘                │
│                                                                     │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
                              │ 9. User taps notification
                              │
                              ▼
                     ┌─────────────────┐
                     │ React Native    │
                     │ onNotification  │
                     │   OpenedApp     │
                     └────────┬────────┘
                              │
                              │ 10. Reads data payload:
                              │     {booking_id: "123"}
                              │
                              ▼
                     ┌─────────────────┐
                     │  Navigation     │
                     │  .navigate(     │
                     │  'BookingDetails'│
                     │   {id: 123})    │
                     └────────┬────────┘
                              │
                              ▼
                     ┌─────────────────┐
                     │ Booking Details │
                     │ Screen Opens! ✅│
                     └─────────────────┘
```

---

## 🔄 Detailed Flow for Each Trigger

### 1️⃣ Booking Created/Confirmed Flow

```
User Books Service
       │
       ▼
BookingController@store()
       │
       │ Booking saved to database
       ▼
event(new BookingCreated($booking))
       │
       ├──► NotifyTherapistOfNewBooking  (Therapist gets notified)
       │
       ├──► NotifyUserOfBookingConfirmation  ⬅️ NEW!
       │           │
       │           ├─ Check if status is 'confirmed'
       │           ├─ Get user's FCM tokens
       │           ├─ Build notification message
       │           └─ Call FCMService
       │                   │
       │                   └──► Firebase sends to user's device 📱
       │
       └──► SendBookingConfirmationEmail  (Email sent)
```

### 2️⃣ Booking Status Changed Flow

```
Admin Updates Booking Status in Filament
       │
       ▼
Booking model ->status = 'completed'
       │
       ▼
BookingObserver@updating()
       │
       ├─ Captures old status: 'confirmed'
       ├─ Captures new status: 'completed'
       │
       ▼
BookingObserver@updated()
       │
       └──► UserNotificationService
                   │
                   ├─ Get user's FCM tokens
                   ├─ Build status change message
                   └─ Call FCMService
                           │
                           └──► Firebase sends to user's device 📱
```

### 3️⃣ Admin Broadcast Flow

```
Admin Creates Notification in Filament
       │
       ▼
NotificationResource → "Send to All Users" button
       │
       ▼
SendBroadcastNotificationJob dispatched
       │
       ▼
FCMService@sendNotificationToAll()
       │
       ├─ Get ALL active user FCM tokens
       ├─ Split into batches of 500 (FCM limit)
       ├─ Send to each batch
       │
       └──► Firebase sends to ALL users 📱📱📱
```

---

## 📱 Mobile App States & Notification Handling

```
┌─────────────────────────────────────────────────────────────┐
│                    NOTIFICATION ARRIVES                     │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┼───────────┐
         │           │           │
         ▼           ▼           ▼
    [Foreground] [Background] [Killed]
         │           │           │
         │           │           │
         ▼           ▼           ▼
   onMessage()  onNotification  getInitial
                 OpenedApp()    Notification()
         │           │           │
         └───────────┼───────────┘
                     │
                     ▼
          handleNotificationData()
                     │
                     ├─ booking_confirmed → BookingDetails
                     ├─ booking_cancelled → BookingDetails
                     ├─ status_changed    → BookingDetails
                     └─ promotional       → Notifications
```

---

## 🔐 Security & Token Management

```
┌─────────────────────────────────────────────────────────────┐
│                    TOKEN LIFECYCLE                          │
└─────────────────────────────────────────────────────────────┘

1. App Launch
   └──► Request permission → Get FCM token

2. User Logs In
   └──► Send token to backend with auth token

3. Token Saved in Database
   └──► {user_id, fcm_token, device_type, is_active: true}

4. Token Used for Notifications
   └──► Backend fetches active tokens only

5. Token Becomes Invalid (device uninstall, expired)
   └──► FCM returns error

6. Backend Deactivates Invalid Token
   └──► {is_active: false}

7. User Reinstalls App
   └──► New token generated & registered

8. User Logs Out (Optional)
   └──► Can delete token from backend
```

---

## 📊 Database Tables Involved

```
┌───────────────────────┐
│   user_fcm_tokens     │
├───────────────────────┤
│ id                    │
│ user_id          ────┼───► users.id
│ fcm_token             │     (The user who owns this device)
│ device_type           │     ('ios' or 'android')
│ device_id             │     (Unique device identifier)
│ is_active             │     (true/false - auto-deactivated if invalid)
│ last_used_at          │     (Updated when notification sent)
│ created_at            │
│ updated_at            │
└───────────────────────┘

┌───────────────────────┐
│      bookings         │
├───────────────────────┤
│ id                    │
│ user_id          ────┼───► Triggers notification to this user
│ status                │     (Changes trigger status update notification)
│ service_id            │
│ therapist_id          │
│ date                  │
│ time                  │
│ ...                   │
└───────────────────────┘

┌───────────────────────┐
│    notifications      │
├───────────────────────┤
│ id                    │
│ title                 │
│ message               │
│ type                  │     ('promotional' or 'system')
│ is_active             │
│ sent_at               │     (When broadcast notification was sent)
│ total_sent            │     (How many devices received it)
│ created_by       ────┼───► Admin who created it
└───────────────────────┘
```

---

## 🎯 What Gets Sent to Your Mobile App

### Notification Object Structure:
```json
{
  "notification": {
    "title": "Booking Confirmed!",
    "body": "Your booking for Ayurvedic Massage on Dec 15 at 2:00 PM has been confirmed.",
    "imageUrl": "https://your-app.com/images/booking.png"
  },
  "data": {
    "type": "booking_confirmed",
    "booking_id": "123",
    "booking_reference": "ABC12345",
    "service_name": "Ayurvedic Massage",
    "therapist_name": "Dr. John Doe",
    "date": "2025-12-15",
    "time": "14:00:00",
    "status": "confirmed"
  },
  "android": {
    "priority": "high",
    "notification": {
      "channelId": "ceylon_ayurveda_notifications",
      "sound": "default",
      "color": "#9A563A"
    }
  },
  "apns": {
    "headers": {
      "apns-priority": "10"
    },
    "payload": {
      "aps": {
        "badge": 1,
        "sound": "default"
      }
    }
  }
}
```

---

## ✅ Implementation Status

| Component | Status | Location |
|-----------|--------|----------|
| **Backend** | ✅ READY | Laravel FCM Service configured |
| **Database** | ✅ READY | user_fcm_tokens table exists |
| **Event Listeners** | ✅ READY | BookingCreated, BookingCancelled |
| **Status Observer** | ✅ READY | BookingObserver monitors changes |
| **Admin Broadcast** | ✅ READY | Filament UI with "Send" button |
| **Mobile App** | ⏳ TODO | Follow MOBILE_APP_QUICK_START.md |

---

## 🚀 Next Steps for You

1. **Set up React Native app** (30 min)
   - Install @react-native-firebase packages
   - Add Firebase config files
   - Copy notificationService.js

2. **Test the flow** (15 min)
   - Login to app → Check token in backend
   - Create booking → Receive notification
   - Update status → Receive notification

3. **Deploy** 🎉
   - Your backend is ready!
   - Your mobile app will receive notifications!

---

## 📞 Support & Debugging

**Check Logs:**
```bash
# Laravel Backend
tail -f storage/logs/laravel.log | grep "notification"

# React Native
npx react-native log-ios     # For iOS
npx react-native log-android # For Android
```

**Test Commands:**
```bash
# Backend test
php artisan tinker
$user = \App\Models\User::first();
$tokens = $user->fcmTokens()->where('is_active', true)->get();
echo "User has " . $tokens->count() . " active tokens";
```

You're all set! 🎊 Your notification system is production-ready!
