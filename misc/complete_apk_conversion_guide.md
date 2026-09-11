# 📱 Master Guide: Converting PHP/MySQL HRIS to Direct-Launch Android APK

This guide provides an end-to-end, zero-error reference for packaging the **Raquel Pawnshop HRIS** web system into an instant, direct-launch native Android APK for testing the **Employee Evaluation Portal**.

> [!IMPORTANT]
> Follow every step in sequence. Pay special attention to matching your **Package Name** and **Theme Name** across all files to avoid build errors.

---

## 🛠️ 1. Essential Tools & Requirements

| Tool | Purpose | Download / Setup |
|---|---|---|
| **XAMPP** | Local Web Server (Apache + MySQL) | `apachefriends.org` (Runs PHP 7.4+ & MySQL) |
| **ngrok** | Secure HTTPS Tunnel | `ngrok.com` (Exposes localhost to the internet) |
| **Android Studio** | Android IDE & Compiler | `developer.android.com/studio` |
| **Java Development Kit (JDK)** | Java Compiler | Bundled with Android Studio |
| **Android SDK** | Build Tools & Android APIs | Managed via Android Studio SDK Manager (API 24+) |

---

## 🌐 Phase 1: Server & Network Tunneling

### 1.1 Start XAMPP
1. Open **XAMPP Control Panel**.
2. Click **Start** for both **Apache** and **MySQL**.
3. Verify your site works locally in your PC browser:
   ```
   http://localhost/FINAL_RAQUEL_PAWNSHOP_HRD/
   ```

### 1.2 Start ngrok Tunnel
1. Open PowerShell or Command Prompt.
2. Authenticate ngrok (first-time only):
   ```powershell
   ngrok config add-authtoken YOUR_AUTHTOKEN_HERE
   ```
3. Launch the tunnel on port 80:
   ```powershell
   ngrok http 80
   ```
4. Copy the **HTTPS** URL generated under `Forwarding` (e.g. `https://pull-numerator-unfounded.ngrok-free.dev`).

### 1.3 Test Tunnel in Mobile Browser
Open the following URL on your phone's browser to ensure your local server is publicly reachable:
```
https://YOUR-SUBDOMAIN.ngrok-free.dev/FINAL_RAQUEL_PAWNSHOP_HRD/employee/index.php
```

---

## 🏗️ Phase 2: Create the Android Studio Project

1. Launch **Android Studio**.
2. Click **New Project** (or **File → New → New Project**).
3. Select **`Empty Views Activity`** (⚠️ **Do NOT choose "Empty Activity"** which uses Jetpack Compose).
4. Configure the project settings:
   - **Name**: `Capstone_app` *(or `RaquelPawnshopHRIS`)*
   - **Package name**: `com.example.capstone_app`
   - **Save location**: `D:\Capstone_app`
   - **Language**: **`Java`**
   - **Minimum SDK**: **`API 24 (Android 7.0)`**
5. Click **Finish** and wait for the Gradle sync bar at the bottom right to complete.

---

## 💻 Phase 3: Android App Architecture & Source Code

Below is the complete set of source files required for an error-free build.

Project Directory Structure:
```
D:\Capstone_app\app\src\main\
├── AndroidManifest.xml
├── java/com/example/capstone_app/
│   └── MainActivity.java
└── res/
    ├── layout/
    │   └── activity_main.xml
    └── values/
        ├── colors.xml
        ├── strings.xml
        └── themes.xml
```

---

### File 1: `AndroidManifest.xml`
> Path: `app/src/main/AndroidManifest.xml`

Declares network permissions, cleartext HTTP support, and sets `MainActivity` as the primary launcher activity.

```xml
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:tools="http://schemas.android.com/tools">

    <!-- Network & Hardware Permissions (CRITICAL FOR WEBVIEW) -->
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
    <uses-permission android:name="android.permission.CAMERA" />
    <uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" android:maxSdkVersion="32" />
    <uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />

    <application
        android:allowBackup="true"
        android:dataExtractionRules="@xml/data_extraction_rules"
        android:fullBackupContent="@xml/backup_rules"
        android:icon="@mipmap/ic_launcher"
        android:label="@string/app_name"
        android:roundIcon="@mipmap/ic_launcher_round"
        android:supportsRtl="true"
        android:theme="@style/Theme.Capstone_app"
        android:usesCleartextTraffic="true"
        android:hardwareAccelerated="true">

        <!-- MainActivity is the DIRECT launcher activity (Instant load into Employee Portal) -->
        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:screenOrientation="portrait"
            android:configChanges="orientation|screenSize|keyboardHidden"
            android:windowSoftInputMode="adjustResize">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>

    </application>
</manifest>
```

---

### File 2: `MainActivity.java`
> Path: `app/src/main/java/com/example/capstone_app/MainActivity.java`

> [!CAUTION]
> **Check Line 1**: Line 1 MUST match your actual package name (e.g. `package com.example.capstone_app;`). If you named your project differently in Android Studio, update line 1 accordingly!

```java
package com.example.capstone_app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient.FileChooserParams;
import android.webkit.JsResult;
import android.webkit.SslErrorHandler;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

public class MainActivity extends AppCompatActivity {

    // ─── CONFIGURE TUNNEL URL HERE ──────────────────────────────────────────
    private static final String BASE_URL  = "https://pull-numerator-unfounded.ngrok-free.dev/FINAL_RAQUEL_PAWNSHOP_HRD";
    // Direct launch into the Employee Portal login screen
    private static final String START_URL = BASE_URL + "/employee/index.php";
    // ────────────────────────────────────────────────────────────────────────

    private WebView webView;
    private ProgressBar progressBar;
    private SwipeRefreshLayout swipeRefreshLayout;
    private ValueCallback<Uri[]> fileUploadCallback;

    // File chooser launcher for profile picture uploads
    private final ActivityResultLauncher<Intent> fileChooserLauncher =
            registerForActivityResult(new ActivityResultContracts.StartActivityForResult(), result -> {
                if (fileUploadCallback == null) return;
                Uri[] results = null;
                if (result.getResultCode() == Activity.RESULT_OK && result.getData() != null) {
                    results = new Uri[]{ result.getData().getData() };
                }
                fileUploadCallback.onReceiveValue(results);
                fileUploadCallback = null;
            });

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView            = findViewById(R.id.webView);
        progressBar        = findViewById(R.id.progressBar);
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);

        setupWebView();
        setupSwipeRefresh();

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        } else {
            webView.loadUrl(START_URL);
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings s = webView.getSettings();

        // 1. Core JS & Storage Settings
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        s.setAllowFileAccess(true);
        s.setAllowContentAccess(true);

        // 2. Viewport & Scaling
        s.setSupportZoom(false);
        s.setBuiltInZoomControls(false);
        s.setDisplayZoomControls(false);
        s.setUseWideViewPort(true);
        s.setLoadWithOverviewMode(true);

        // 3. Custom User-Agent (bypasses ngrok warning on CSS, JS & form POSTs)
        s.setUserAgentString("RaquelHRISNativeApp/1.0 Mobile");

        // 4. Allow Mixed Content (HTTP/HTTPS assets)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            s.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            s.setMediaPlaybackRequiresUserGesture(false);
        }

        // 5. Session Cookie Management
        CookieManager cm = CookieManager.getInstance();
        cm.setAcceptCookie(true);
        cm.setAcceptThirdPartyCookies(webView, true);

        // 6. Navigation Client
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView v, String url, Bitmap f) {
                super.onPageStarted(v, url, f);
                progressBar.setVisibility(View.VISIBLE);
                swipeRefreshLayout.setRefreshing(false);
            }

            @Override
            public void onPageFinished(WebView v, String url) {
                super.onPageFinished(v, url);
                progressBar.setVisibility(View.GONE);
                CookieManager.getInstance().flush(); // Persist PHP session cookies
            }

            @Override
            public void onReceivedError(WebView v, int c, String desc, String u) {
                progressBar.setVisibility(View.GONE);
                String html = "<html><body style='font-family:sans-serif;text-align:center;padding:40px;background:#F4F6F8;'>"
                        + "<h2 style='color:#074B02;'>Connection Error</h2><p>" + desc + "</p>"
                        + "<button onclick='location.reload()' style='padding:12px 28px;background:#074B02;"
                        + "color:white;border:none;border-radius:8px;font-size:16px;'>Retry</button>"
                        + "</body></html>";
                v.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null);
            }

            @Override
            public void onReceivedSslError(WebView v, SslErrorHandler h, SslError e) {
                h.proceed(); // Ignore SSL warning during development
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest req) {
                String url = req.getUrl().toString();
                // Keep all internal app URLs (ngrok, localhost, local IP, or relative paths) inside WebView
                if (url.startsWith("http://") || url.startsWith("https://")) {
                    Uri uri = req.getUrl();
                    String host = uri.getHost();
                    if (host != null && (
                        host.contains("ngrok") ||
                        host.contains("localhost") ||
                        host.startsWith("192.168.") ||
                        host.startsWith("10.") ||
                        host.startsWith("127.")
                    )) {
                        return false; // Load inside WebView
                    }
                }
                // External links (e.g. maps, external PDFs) open in browser
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                } catch (Exception e) {}
                return true;
            }
        });

        // 7. Chrome Client (Progress Bar, JS Alerts, File Choice)
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView v, int p) {
                progressBar.setProgress(p);
                if (p == 100) progressBar.setVisibility(View.GONE);
            }

            @Override
            public boolean onShowFileChooser(WebView wv, ValueCallback<Uri[]> cb, FileChooserParams p) {
                if (fileUploadCallback != null) fileUploadCallback.onReceiveValue(null);
                fileUploadCallback = cb;
                if (ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.CAMERA)
                        != PackageManager.PERMISSION_GRANTED) {
                    ActivityCompat.requestPermissions(MainActivity.this, new String[]{ Manifest.permission.CAMERA }, 100);
                }
                try { fileChooserLauncher.launch(p.createIntent()); }
                catch (Exception e) { fileUploadCallback = null; return false; }
                return true;
            }

            @Override
            public boolean onJsAlert(WebView v, String url, String msg, JsResult r) {
                new AlertDialog.Builder(MainActivity.this).setMessage(msg)
                        .setPositiveButton("OK", (d, w) -> r.confirm()).setCancelable(false).show();
                return true;
            }

            @Override
            public boolean onJsConfirm(WebView v, String url, String msg, JsResult r) {
                new AlertDialog.Builder(MainActivity.this).setMessage(msg)
                        .setPositiveButton("Yes", (d, w) -> r.confirm())
                        .setNegativeButton("No",  (d, w) -> r.cancel()).setCancelable(false).show();
                return true;
            }
        });
    }

    private void setupSwipeRefresh() {
        swipeRefreshLayout.setColorSchemeColors(ContextCompat.getColor(this, R.color.primary_green));
        swipeRefreshLayout.setOnRefreshListener(() -> {
            webView.reload();
            swipeRefreshLayout.setRefreshing(false);
        });
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
            return;
        }
        new AlertDialog.Builder(this)
                .setTitle("Exit App")
                .setMessage("Are you sure you want to exit?")
                .setPositiveButton("Exit",   (d, w) -> finish())
                .setNegativeButton("Cancel", null)
                .show();
    }

    @Override protected void onSaveInstanceState(@NonNull Bundle out) { super.onSaveInstanceState(out); webView.saveState(out); }
    @Override protected void onRestoreInstanceState(@NonNull Bundle s) { super.onRestoreInstanceState(s); webView.restoreState(s); }
    @Override protected void onPause()   { super.onPause();   webView.onPause();  CookieManager.getInstance().flush(); }
    @Override protected void onResume()  { super.onResume();  webView.onResume(); }
    @Override protected void onDestroy() { if (webView != null) { webView.stopLoading(); webView.destroy(); } super.onDestroy(); }
}
```

---

### File 3: `activity_main.xml`
> Path: `app/src/main/res/layout/activity_main.xml`

```xml
<?xml version="1.0" encoding="utf-8"?>
<RelativeLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:background="#F4F6F8">

    <ProgressBar
        android:id="@+id/progressBar"
        style="?android:attr/progressBarStyleHorizontal"
        android:layout_width="match_parent"
        android:layout_height="4dp"
        android:layout_alignParentTop="true"
        android:max="100"
        android:progressTint="#074B02"
        android:visibility="gone" />

    <androidx.swiperefreshlayout.widget.SwipeRefreshLayout
        android:id="@+id/swipeRefreshLayout"
        android:layout_width="match_parent"
        android:layout_height="match_parent"
        android:layout_below="@id/progressBar">

        <WebView
            android:id="@+id/webView"
            android:layout_width="match_parent"
            android:layout_height="match_parent"
            android:scrollbars="none"
            android:overScrollMode="never" />

    </androidx.swiperefreshlayout.widget.SwipeRefreshLayout>
</RelativeLayout>
```

---

### File 4: Resource Values (`colors.xml` & `themes.xml`)

> **`app/src/main/res/values/colors.xml`**:
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="primary_green">#074B02</color>
    <color name="primary_gold">#CBA135</color>
    <color name="white">#FFFFFF</color>
    <color name="background">#F4F6F8</color>
</resources>
```

> **`app/src/main/res/values/themes.xml`**:
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <style name="Theme.Capstone_app" parent="Theme.MaterialComponents.DayNight.NoActionBar">
        <item name="colorPrimary">@color/primary_green</item>
        <item name="colorAccent">@color/primary_gold</item>
        <item name="android:statusBarColor">@color/primary_green</item>
    </style>
</resources>
```

> **`app/src/main/res/values-night/themes.xml`**:
```xml
<?xml version="1.0" encoding="utf-8"?>
<resources xmlns:tools="http://schemas.android.com/tools">
    <style name="Theme.Capstone_app" parent="Theme.MaterialComponents.DayNight.NoActionBar">
        <item name="colorPrimary">@color/primary_green</item>
        <item name="colorAccent">@color/primary_gold</item>
        <item name="android:statusBarColor">@color/primary_green</item>
    </style>
</resources>
```

---

### File 5: App Gradle Dependencies (`app/build.gradle.kts`)
> Path: `app/build.gradle.kts`

```kotlin
plugins {
    alias(libs.plugins.android.application)
}

android {
    namespace = "com.example.capstone_app"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.example.capstone_app"
        minSdk = 24
        targetSdk = 35
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
}

dependencies {
    implementation(libs.appcompat)
    implementation(libs.material)
    implementation(libs.activity)
    implementation(libs.constraintlayout)
    implementation(libs.swiperefreshlayout)
    testImplementation(libs.junit)
    androidTestImplementation(libs.ext.junit)
    androidTestImplementation(libs.espresso.core)
}
```

---

### 🎨 Phase 3.5: Customizing App Icon & App Name

#### 1. Change App Name (App Title under launcher icon)
1. Open `app/src/main/res/values/strings.xml`.
2. Change the `app_name` string:
   ```xml
   <resources>
       <string name="app_name">Raquel HRIS</string>
   </resources>
   ```

#### 2. Create Custom App Icon using Android Studio Image Asset Studio (Recommended)
1. Right-click the **`app`** folder (or `res` folder) in the left Android Studio project menu.
2. Go to **New → Image Asset**.
3. In the **Asset Studio** window:
   - **Icon Type**: Select **`Launcher Icons (Adaptive and Legacy)`**.
   - **Name**: Leave as **`ic_launcher`** (this automatically overwrites standard defaults).
   - **Foreground Layer**:
     - Select **`Image`** under Asset Type.
     - Click the folder icon next to **Path** and browse to your logo image file (`.png`, `.jpg`, or `.svg`).
     - Adjust the **Resize** slider so your logo fits nicely inside the green circle guide.
   - **Background Layer**:
     - Select **`Color`** under Asset Type and set your brand color (e.g. `#074B02` green), OR select **`Image`** for a background texture.
4. Click **Next**, then click **Finish**.
5. Android Studio will automatically generate all icon densities (`hdpi`, `xhdpi`, `xxhdpi`, `xxxhdpi`, and adaptive XML icons) inside `res/mipmap/`.

---

## ⚡ Phase 4: Build & Install APK

1. In Android Studio, click **File → Sync Project with Gradle Files** (or click the 🐘 Elephant icon in top-right bar).
2. Click **Build → Generate App Bundles or APKs → Build APK(s)**.
3. When compilation finishes, click **`locate`** in the bottom-right notification popup.
4. Transfer `app-debug.apk` to your Android device via USB/Drive and install!

---

## 🎓 Phase 5: Troubleshooting & Zero-Error Checksheet

If you encounter any issue during setup, check this list:

### 1. `Package name does not match declared package`
- **Cause**: Line 1 of `MainActivity.java` does not match the actual folder directory or Gradle namespace.
- **Solution**: Ensure line 1 is `package com.example.capstone_app;` (or matches your exact project package name).

### 2. `AAPT: error: resource style/Theme.Capstone_app not found`
- **Cause**: The theme name in `AndroidManifest.xml` (`android:theme="@style/Theme.Capstone_app"`) doesn't match the `<style name="...">` in `themes.xml`.
- **Solution**: Make sure both files specify the exact same style name (`Theme.Capstone_app`).

### 3. Connection Error / `net::ERR_NAME_NOT_RESOLVED` inside APK
- **Cause**: Missing `<uses-permission android:name="android.permission.INTERNET" />` in `AndroidManifest.xml` OR ngrok tunnel was closed/restarted.
- **Solution**: Ensure the permission tag is present in `AndroidManifest.xml`, check that ngrok is running, and update `BASE_URL` in `MainActivity.java`.

### 4. Broken CSS/Formatting or ngrok Interstitial Warning Page
- **Cause**: ngrok serves an HTML warning page for asset requests if no custom User-Agent is sent.
- **Solution**: Handled automatically in `MainActivity.java` via:
  ```java
  s.setUserAgentString("RaquelHRISNativeApp/1.0 Mobile");
  ```

### 5. `illegal character: '\ufeff'` Compiler Error
- **Cause**: Java file was saved with UTF-8 BOM encoding.
- **Solution**: Save Java files in UTF-8 Without BOM.

### 6. AAR Metadata Error: `Dependency requires compileSdk 36 or later`
- **Cause**: AndroidX dependencies (`androidx.activity:1.13.0`, `core-ktx:1.18.0`, etc.) require compilation against Android API 36+.
- **Solution**: Set `compileSdk = 36` in `app/build.gradle.kts`. You can safely leave `targetSdk = 35` for runtime behavior stability.


