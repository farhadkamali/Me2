package ir.vamkamali.manager;

import android.Manifest;
import android.app.Activity;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.webkit.JavascriptInterface;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.graphics.Color;

import androidx.core.app.ActivityCompat;

import com.google.firebase.messaging.FirebaseMessaging;

import org.json.JSONObject;

public class MainActivity extends Activity {
  private WebView web;

  @Override public void onCreate(Bundle state) {
    super.onCreate(state);
    getWindow().setStatusBarColor(Color.rgb(23,105,210));
    getWindow().setNavigationBarColor(Color.WHITE);

    web = new WebView(this);
    web.setBackgroundColor(Color.rgb(243,247,253));
    WebSettings s = web.getSettings();
    s.setJavaScriptEnabled(true);
    s.setDomStorageEnabled(true);
    s.setAllowFileAccess(true);
    s.setAllowContentAccess(true);
    s.setAllowFileAccessFromFileURLs(true);
    s.setAllowUniversalAccessFromFileURLs(true);
    s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
    web.setWebViewClient(new WebViewClient());
    web.setWebChromeClient(new WebChromeClient());
    web.addJavascriptInterface(new PushBridge(), "AndroidPush");
    setContentView(web);
    requestNotificationPermission();
    web.loadUrl("file:///android_asset/index.html");
  }

  private void requestNotificationPermission() {
    if (Build.VERSION.SDK_INT >= 33 &&
        checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
      ActivityCompat.requestPermissions(this,
          new String[]{Manifest.permission.POST_NOTIFICATIONS}, 701);
    }
  }

  private class PushBridge {
    @JavascriptInterface
    public void requestToken() {
      FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
        if (!task.isSuccessful() || web == null) return;
        String token = task.getResult();
        SharedPreferences prefs = getSharedPreferences("manager_push", MODE_PRIVATE);
        prefs.edit().putString("fcm_token", token).apply();
        String js = "window.onManagerFcmToken(" + JSONObject.quote(token) + ")";
        web.post(() -> {
          if (web != null) web.evaluateJavascript(js, null);
        });
      });
    }
  }

  @Override public void onBackPressed() {
    if (web != null && web.canGoBack()) web.goBack();
    else super.onBackPressed();
  }

  @Override protected void onDestroy() {
    if (web != null) {
      web.loadUrl("about:blank");
      web.stopLoading();
      web.destroy();
      web = null;
    }
    super.onDestroy();
  }
}
