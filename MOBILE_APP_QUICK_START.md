# Mobile App Push Notifications - Quick Start Checklist

## ✅ Quick Setup Checklist (30 minutes)

### 1. Install Packages (5 min)
```bash
npm install @react-native-firebase/app @react-native-firebase/messaging
cd ios && pod install && cd ..
```

### 2. Add Firebase Config Files (5 min)
- [ ] Download `google-services.json` from Firebase Console
- [ ] Place in `android/app/` directory
- [ ] Download `GoogleService-Info.plist` from Firebase Console
- [ ] Place in `ios/YourAppName/` directory

### 3. Update Android Files (5 min)

**`android/build.gradle`:**
```gradle
dependencies {
    classpath 'com.google.gms:google-services:4.4.0'  // Add this
}
```

**`android/app/build.gradle`:**
```gradle
apply plugin: 'com.google.gms.google-services'  // Add this at bottom
```

### 4. Update iOS Files (5 min)

**`ios/YourAppName/AppDelegate.mm`:**
```objc
#import <Firebase.h>  // Add at top

- (BOOL)application:(UIApplication *)application didFinishLaunchingWithOptions:(NSDictionary *)launchOptions
{
  [FIRApp configure];  // Add this line
  // ... rest
}
```

### 5. Copy Notification Service File (5 min)
Copy the `notificationService.js` code from `REACT_NATIVE_IMPLEMENTATION.md` to your project:
- Create: `src/services/notificationService.js`
- Update `API_BASE_URL` with your backend URL

### 6. Initialize in App.js (3 min)
```javascript
import notificationService from './src/services/notificationService';

useEffect(() => {
  notificationService.requestPermission();
  notificationService.getToken();
  notificationService.setupNotificationListeners(navigationRef.current);
}, []);
```

### 7. Register Token After Login (2 min)
```javascript
// In your login function
await notificationService.registerTokenWithBackend(authToken);
```

### 8. Test It! (5 min)
1. Run app: `npx react-native run-ios` or `npx react-native run-android`
2. Login to your app
3. Check console for FCM token
4. Create a test booking
5. You should receive a notification!

---

## 🎯 Essential Code Snippets

### Minimal notificationService.js
```javascript
import messaging from '@react-native-firebase/messaging';
import axios from 'axios';
import { Platform } from 'react-native';

const API_URL = 'YOUR_BACKEND_URL/api'; // CHANGE THIS!

class NotificationService {
  async requestPermission() {
    const authStatus = await messaging().requestPermission();
    return authStatus === messaging.AuthorizationStatus.AUTHORIZED;
  }

  async getToken() {
    return await messaging().getToken();
  }

  async registerTokenWithBackend(authToken) {
    const fcmToken = await this.getToken();
    await axios.post(`${API_URL}/fcm-token`, {
      fcm_token: fcmToken,
      device_type: Platform.OS,
      device_id: 'device-' + Date.now()
    }, {
      headers: { Authorization: `Bearer ${authToken}` }
    });
  }

  setupNotificationListeners(navigation) {
    // Foreground
    messaging().onMessage(async (message) => {
      console.log('Notification:', message);
      alert(message.notification.body);
    });

    // Background tap
    messaging().onNotificationOpenedApp((message) => {
      if (message.data.booking_id) {
        navigation.navigate('BookingDetails', {
          bookingId: message.data.booking_id
        });
      }
    });

    // App was closed
    messaging().getInitialNotification().then((message) => {
      if (message?.data.booking_id) {
        navigation.navigate('BookingDetails', {
          bookingId: message.data.booking_id
        });
      }
    });
  }
}

export default new NotificationService();
```

### In App.js
```javascript
import React, { useEffect, useRef } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import notificationService from './src/services/notificationService';

function App() {
  const navigationRef = useRef();

  useEffect(() => {
    notificationService.requestPermission();
    notificationService.getToken().then(token => {
      console.log('FCM Token:', token);
    });
    notificationService.setupNotificationListeners(navigationRef.current);
  }, []);

  return (
    <NavigationContainer ref={navigationRef}>
      {/* Your screens */}
    </NavigationContainer>
  );
}

export default App;
```

### In Login Screen
```javascript
const handleLogin = async () => {
  const response = await login(email, password);
  const token = response.data.token;

  await AsyncStorage.setItem('auth_token', token);
  await notificationService.registerTokenWithBackend(token);

  navigation.navigate('Home');
};
```

---

## 🧪 Quick Test

### Test 1: Check Token Generated
```javascript
// After app launches, check console
// You should see: "FCM Token: xxxxxx"
```

### Test 2: Check Token Registered with Backend
```bash
# In Laravel backend
php artisan tinker

# Check if token exists
\App\Models\UserFcmToken::where('user_id', 1)->get();
# Should show your FCM token
```

### Test 3: Send Test Notification from Backend
```bash
php artisan tinker

$booking = \App\Models\Booking::where('user_id', 1)->first();
$booking->load(['service', 'therapist']);
event(new \App\Events\BookingCreated($booking));

# Check your mobile device - you should receive notification!
```

---

## 🐛 Common Issues

### "No token generated"
**Solution:**
```bash
# Make sure Firebase is configured
# Check if google-services.json and GoogleService-Info.plist are in place
# Rebuild the app completely
npx react-native run-android --reset-cache
```

### "Permission denied"
**Solution:**
```javascript
// For Android 13+, add to AndroidManifest.xml:
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
```

### "Token not sent to backend"
**Solution:**
```javascript
// Check your API URL
const API_URL = 'https://your-backend.com/api'; // Must be correct!

// Check auth token exists
const token = await AsyncStorage.getItem('auth_token');
console.log('Auth token:', token);
```

---

## 📱 Notification Types You'll Receive

### 1. Booking Confirmed
```
Title: "Booking Confirmed!"
Body: "Your booking for Ayurvedic Massage on Dec 15 at 2:00 PM has been confirmed."
Action: Tap → Opens booking details
```

### 2. Status Changed
```
Title: "Booking Status Updated"
Body: "Your booking for Ayurvedic Massage has been completed. Thank you!"
Action: Tap → Opens booking details
```

### 3. Booking Cancelled
```
Title: "Booking Cancelled"
Body: "Your booking for Ayurvedic Massage on Dec 15 has been cancelled."
Action: Tap → Opens booking details
```

### 4. Admin Broadcast
```
Title: "Special Offer!"
Body: "Get 20% off on all services this week!"
Action: Tap → Opens notifications screen
```

---

## 🎉 Success Checklist

After setup, verify:
- [ ] App requests notification permission on launch
- [ ] FCM token is logged in console
- [ ] Token is sent to backend after login
- [ ] You can see token in backend database
- [ ] Test notification appears on device
- [ ] Tapping notification opens correct screen
- [ ] Foreground notifications show alert
- [ ] Background notifications appear in tray

---

## 📚 Full Documentation

For detailed implementation, see:
- **[REACT_NATIVE_IMPLEMENTATION.md](REACT_NATIVE_IMPLEMENTATION.md)** - Complete guide with all features
- **[PUSH_NOTIFICATIONS_IMPLEMENTATION.md](PUSH_NOTIFICATIONS_IMPLEMENTATION.md)** - Backend documentation
- **[TESTING_PUSH_NOTIFICATIONS.md](TESTING_PUSH_NOTIFICATIONS.md)** - Testing guide

---

## 🚀 Ready to Go!

Once you complete this checklist, your mobile app will automatically receive push notifications from your backend!

**Need help?**
- Check React Native console for errors
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Test with the Laravel Tinker commands above

Good luck! 🎊
