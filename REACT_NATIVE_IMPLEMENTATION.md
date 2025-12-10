# React Native Push Notifications Implementation Guide

## Overview
This guide shows you how to implement push notifications in your React Native mobile app to work with the Laravel backend that's already configured with Firebase Cloud Messaging (FCM).

---

## 📋 Prerequisites

Your Laravel backend is already set up and ready! Now let's configure the mobile app.

---

## 🚀 Step 1: Install Required Packages

### For React Native (with Firebase)

```bash
# Install React Native Firebase
npm install @react-native-firebase/app @react-native-firebase/messaging

# OR with yarn
yarn add @react-native-firebase/app @react-native-firebase/messaging

# For iOS, install pods
cd ios && pod install && cd ..
```

### For Expo (if using Expo)

```bash
# Install Expo notifications
npx expo install expo-notifications expo-device expo-constants

# Install Firebase if needed
npx expo install @react-native-firebase/app @react-native-firebase/messaging
```

---

## 🔧 Step 2: Configure Firebase in Your App

### 2.1 For iOS (React Native)

1. Download `GoogleService-Info.plist` from Firebase Console
2. Place it in `ios/YourAppName/` directory
3. Open Xcode and add it to your project

**Update `ios/YourAppName/AppDelegate.mm`:**
```objc
#import <Firebase.h>

- (BOOL)application:(UIApplication *)application didFinishLaunchingWithOptions:(NSDictionary *)launchOptions
{
  [FIRApp configure]; // Add this line

  // ... rest of your code
  return YES;
}
```

### 2.2 For Android (React Native)

1. Download `google-services.json` from Firebase Console
2. Place it in `android/app/` directory

**Update `android/build.gradle`:**
```gradle
buildscript {
  dependencies {
    // Add this line
    classpath 'com.google.gms:google-services:4.4.0'
  }
}
```

**Update `android/app/build.gradle`:**
```gradle
apply plugin: 'com.android.application'
apply plugin: 'com.google.gms.google-services' // Add this line

dependencies {
  implementation 'com.google.firebase:firebase-messaging:23.4.0'
}
```

**Update `android/app/src/main/AndroidManifest.xml`:**
```xml
<manifest>
  <application>
    <!-- Add notification channel -->
    <meta-data
      android:name="com.google.firebase.messaging.default_notification_channel_id"
      android:value="ceylon_ayurveda_notifications" />

    <!-- Add notification icon -->
    <meta-data
      android:name="com.google.firebase.messaging.default_notification_icon"
      android:resource="@drawable/ic_notification" />

    <!-- Add notification color -->
    <meta-data
      android:name="com.google.firebase.messaging.default_notification_color"
      android:resource="@color/notification_color" />
  </application>
</manifest>
```

---

## 📱 Step 3: Request Notification Permissions

Create a notification service file:

**`src/services/notificationService.js`:**
```javascript
import messaging from '@react-native-firebase/messaging';
import { Platform, PermissionsAndroid, Alert } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';

const API_BASE_URL = 'https://your-backend-url.com/api'; // Change this!

class NotificationService {
  constructor() {
    this.token = null;
  }

  /**
   * Request notification permissions
   */
  async requestPermission() {
    try {
      if (Platform.OS === 'ios') {
        const authStatus = await messaging().requestPermission();
        const enabled =
          authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
          authStatus === messaging.AuthorizationStatus.PROVISIONAL;

        if (!enabled) {
          Alert.alert(
            'Permission Required',
            'Please enable notifications in Settings to receive booking updates.'
          );
          return false;
        }
        return true;
      } else {
        // Android 13+ requires runtime permission
        if (Platform.Version >= 33) {
          const granted = await PermissionsAndroid.request(
            PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS
          );
          return granted === PermissionsAndroid.RESULTS.GRANTED;
        }
        return true;
      }
    } catch (error) {
      console.error('Permission request error:', error);
      return false;
    }
  }

  /**
   * Get FCM token
   */
  async getToken() {
    try {
      const hasPermission = await this.requestPermission();
      if (!hasPermission) {
        console.log('Notification permission not granted');
        return null;
      }

      const token = await messaging().getToken();
      console.log('FCM Token:', token);
      this.token = token;
      return token;
    } catch (error) {
      console.error('Error getting FCM token:', error);
      return null;
    }
  }

  /**
   * Register token with backend
   */
  async registerTokenWithBackend(authToken) {
    try {
      const fcmToken = await this.getToken();
      if (!fcmToken) {
        console.log('No FCM token available');
        return false;
      }

      // Get device info
      const deviceInfo = await this.getDeviceInfo();

      const response = await axios.post(
        `${API_BASE_URL}/fcm-token`,
        {
          fcm_token: fcmToken,
          device_type: Platform.OS, // 'ios' or 'android'
          device_id: deviceInfo.deviceId,
        },
        {
          headers: {
            Authorization: `Bearer ${authToken}`,
            'Content-Type': 'application/json',
          },
        }
      );

      console.log('Token registered with backend:', response.data);

      // Save token locally
      await AsyncStorage.setItem('fcm_token', fcmToken);

      return true;
    } catch (error) {
      console.error('Error registering token with backend:', error.response?.data || error.message);
      return false;
    }
  }

  /**
   * Get device info
   */
  async getDeviceInfo() {
    // You might want to use react-native-device-info for this
    const deviceId = await AsyncStorage.getItem('device_id');

    if (!deviceId) {
      // Generate a unique device ID
      const newDeviceId = `${Platform.OS}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
      await AsyncStorage.setItem('device_id', newDeviceId);
      return { deviceId: newDeviceId };
    }

    return { deviceId };
  }

  /**
   * Setup notification listeners
   */
  setupNotificationListeners(navigation) {
    // Foreground notification handler
    const unsubscribeForeground = messaging().onMessage(async (remoteMessage) => {
      console.log('Foreground notification received:', remoteMessage);

      // Display notification manually if needed
      this.displayLocalNotification(remoteMessage);

      // Handle the notification data
      this.handleNotificationData(remoteMessage.data, navigation);
    });

    // Background/Quit state notification handler
    messaging().onNotificationOpenedApp((remoteMessage) => {
      console.log('Notification opened app from background:', remoteMessage);
      this.handleNotificationData(remoteMessage.data, navigation);
    });

    // Check if app was opened from a notification (when app was completely quit)
    messaging()
      .getInitialNotification()
      .then((remoteMessage) => {
        if (remoteMessage) {
          console.log('Notification opened app from quit state:', remoteMessage);
          this.handleNotificationData(remoteMessage.data, navigation);
        }
      });

    // Token refresh listener
    const unsubscribeTokenRefresh = messaging().onTokenRefresh((token) => {
      console.log('FCM token refreshed:', token);
      this.token = token;
      // Re-register with backend if user is logged in
      this.reRegisterTokenIfNeeded();
    });

    // Return cleanup function
    return () => {
      unsubscribeForeground();
      unsubscribeTokenRefresh();
    };
  }

  /**
   * Handle notification data and navigation
   */
  handleNotificationData(data, navigation) {
    if (!data || !navigation) return;

    console.log('Handling notification data:', data);

    // Handle different notification types
    switch (data.type) {
      case 'booking_confirmed':
      case 'booking_status_changed':
      case 'booking_cancelled':
        // Navigate to booking details
        if (data.booking_id) {
          navigation.navigate('BookingDetails', {
            bookingId: data.booking_id,
          });
        }
        break;

      case 'promotional':
      case 'system':
        // Navigate to notifications screen
        navigation.navigate('Notifications');
        break;

      default:
        console.log('Unknown notification type:', data.type);
    }
  }

  /**
   * Display local notification (for foreground)
   */
  displayLocalNotification(remoteMessage) {
    // You can use a local notification library here
    // For now, just log it
    Alert.alert(
      remoteMessage.notification?.title || 'New Notification',
      remoteMessage.notification?.body || '',
      [
        {
          text: 'OK',
          onPress: () => console.log('Notification dismissed'),
        },
      ]
    );
  }

  /**
   * Re-register token if needed (after token refresh)
   */
  async reRegisterTokenIfNeeded() {
    try {
      const authToken = await AsyncStorage.getItem('auth_token');
      if (authToken) {
        await this.registerTokenWithBackend(authToken);
      }
    } catch (error) {
      console.error('Error re-registering token:', error);
    }
  }

  /**
   * Check notification permission status
   */
  async checkPermissionStatus() {
    const authStatus = await messaging().hasPermission();
    return (
      authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
      authStatus === messaging.AuthorizationStatus.PROVISIONAL
    );
  }
}

export default new NotificationService();
```

---

## 🎯 Step 4: Initialize Notifications in Your App

**`App.js` or `App.tsx`:**
```javascript
import React, { useEffect } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import notificationService from './src/services/notificationService';
import AsyncStorage from '@react-native-async-storage/async-storage';

function App() {
  const navigationRef = React.useRef();

  useEffect(() => {
    // Initialize notifications when app starts
    initializeNotifications();
  }, []);

  const initializeNotifications = async () => {
    try {
      // Request permission
      const hasPermission = await notificationService.requestPermission();

      if (hasPermission) {
        // Get FCM token
        const token = await notificationService.getToken();
        console.log('FCM Token obtained:', token);

        // Setup listeners
        const unsubscribe = notificationService.setupNotificationListeners(
          navigationRef.current
        );

        // Cleanup on unmount
        return unsubscribe;
      }
    } catch (error) {
      console.error('Error initializing notifications:', error);
    }
  };

  return (
    <NavigationContainer ref={navigationRef}>
      {/* Your app screens */}
    </NavigationContainer>
  );
}

export default App;
```

---

## 🔐 Step 5: Register Token After Login

**In your Login/Auth screen:**
```javascript
import notificationService from '../services/notificationService';

const handleLogin = async (email, password) => {
  try {
    // Your login logic
    const response = await loginAPI(email, password);
    const authToken = response.data.token;

    // Save auth token
    await AsyncStorage.setItem('auth_token', authToken);

    // Register FCM token with backend
    await notificationService.registerTokenWithBackend(authToken);

    // Navigate to home
    navigation.navigate('Home');
  } catch (error) {
    console.error('Login error:', error);
  }
};
```

---

## 📊 Step 6: Handle Notifications in Specific Screens

**`screens/BookingDetailsScreen.js`:**
```javascript
import React, { useEffect } from 'react';
import { View, Text } from 'react-native';

const BookingDetailsScreen = ({ route, navigation }) => {
  const { bookingId } = route.params;

  useEffect(() => {
    // Fetch booking details when screen loads
    fetchBookingDetails(bookingId);
  }, [bookingId]);

  const fetchBookingDetails = async (id) => {
    // Your API call to get booking details
    try {
      const response = await axios.get(`/api/bookings/${id}`);
      console.log('Booking details:', response.data);
    } catch (error) {
      console.error('Error fetching booking:', error);
    }
  };

  return (
    <View>
      <Text>Booking Details</Text>
      {/* Your UI */}
    </View>
  );
};

export default BookingDetailsScreen;
```

---

## 🧪 Step 7: Test Your Implementation

### Testing Checklist:

1. **✅ App Launch**
   - App requests notification permission
   - FCM token is generated
   - Token is logged in console

2. **✅ After Login**
   - Token is sent to backend
   - Check backend logs for successful registration

3. **✅ Foreground Notifications**
   - Create a test booking in your app
   - You should see an alert with the notification

4. **✅ Background Notifications**
   - Put app in background
   - Create a test booking
   - Notification should appear in notification tray
   - Tap notification → app opens to booking details

5. **✅ Admin Broadcast**
   - Admin creates notification in Laravel Filament
   - All logged-in users receive notification

---

## 🐛 Troubleshooting

### No Token Generated
```javascript
// Check Firebase configuration
console.log('Firebase configured:', messaging().isDeviceRegisteredForRemoteMessages);

// Re-initialize Firebase
await messaging().registerDeviceForRemoteMessages();
```

### Token Not Sent to Backend
```javascript
// Check auth token
const authToken = await AsyncStorage.getItem('auth_token');
console.log('Auth token exists:', !!authToken);

// Check API endpoint
console.log('API URL:', API_BASE_URL);
```

### Notifications Not Appearing
```javascript
// Check permission status
const hasPermission = await notificationService.checkPermissionStatus();
console.log('Has notification permission:', hasPermission);

// Check if token is active in backend
// Run this in Laravel Tinker:
// \App\Models\UserFcmToken::where('user_id', 1)->get();
```

---

## 📝 Backend API Endpoints (Already Working)

Your Laravel backend already has these endpoints ready:

### Register FCM Token
```http
POST /api/fcm-token
Authorization: Bearer {token}
Content-Type: application/json

{
  "fcm_token": "fcm_token_here",
  "device_type": "ios",
  "device_id": "unique-device-id"
}
```

### Get Notifications List
```http
GET /api/notifications
Authorization: Bearer {token}
```

### Get Notification Details
```http
GET /api/notifications/{id}
Authorization: Bearer {token}
```

---

## 🎨 Notification Payload Structure

Your backend sends notifications with this structure:

```json
{
  "notification": {
    "title": "Booking Confirmed!",
    "body": "Your booking for Ayurvedic Massage on Dec 15 at 2:00 PM has been confirmed."
  },
  "data": {
    "type": "booking_confirmed",
    "booking_id": "123",
    "booking_reference": "ABC12345",
    "service_name": "Ayurvedic Massage",
    "therapist_name": "John Doe",
    "date": "2025-12-15",
    "time": "14:00:00",
    "status": "confirmed"
  }
}
```

---

## ✨ Optional Enhancements

### 1. Badge Count (iOS)
```javascript
import notificationService from '@react-native-firebase/messaging';

// Set badge count
await notificationService().setApplicationIconBadgeNumber(5);

// Clear badge
await notificationService().setApplicationIconBadgeNumber(0);
```

### 2. Notification Settings Screen
```javascript
const NotificationSettingsScreen = () => {
  const [enabled, setEnabled] = useState(true);

  const toggleNotifications = async (value) => {
    setEnabled(value);
    await AsyncStorage.setItem('notifications_enabled', value.toString());

    if (!value) {
      // Unregister token from backend
      await axios.delete('/api/fcm-token');
    } else {
      // Re-register token
      await notificationService.registerTokenWithBackend(authToken);
    }
  };

  return (
    <Switch value={enabled} onValueChange={toggleNotifications} />
  );
};
```

### 3. Custom Notification Sound (Android)
Place `notification.mp3` in `android/app/src/main/res/raw/`

---

## 🚀 You're All Set!

Your React Native app is now ready to receive push notifications from your Laravel backend!

**What happens now:**
1. ✅ User logs in → FCM token registered
2. ✅ User books service → Receives confirmation notification
3. ✅ Admin updates booking → User receives status update
4. ✅ User cancels booking → Receives cancellation notification
5. ✅ Admin broadcasts message → All users receive it

Need help? Check the Laravel logs and React Native console for debugging info!
