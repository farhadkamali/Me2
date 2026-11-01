package ir.vamkamali.members;

import android.app.Activity;
import android.os.Bundle;
import android.graphics.Color;
import android.view.View;
import android.view.Window;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.TextView;

public class MainActivity extends Activity {
  private WebView web;

  private void enableImmersiveMode() {
    getWindow().getDecorView().setSystemUiVisibility(
      View.SYSTEM_UI_FLAG_FULLSCREEN
      | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
      | View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
      | View.SYSTEM_UI_FLAG_LAYOUT_STABLE
      | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN
      | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
    );
  }

  private void showStartupError(Throwable error) {
    TextView message = new TextView(this);
    message.setText("برنامه نتوانست اجرا شود. لطفاً گوشی را راه‌اندازی مجدد و دوباره امتحان کنید.\n\n" +
      error.getClass().getSimpleName() + ": " + String.valueOf(error.getMessage()));
    message.setTextColor(Color.DKGRAY);
    message.setTextSize(16);
    message.setGravity(android.view.Gravity.CENTER);
    message.setPadding(28, 28, 28, 28);
    message.setBackgroundColor(Color.rgb(243, 247, 253));
    setContentView(message);
  }

  @Override public void onWindowFocusChanged(boolean hasFocus) {
    super.onWindowFocusChanged(hasFocus);
    if (hasFocus) {
      try { enableImmersiveMode(); } catch (Throwable ignored) { }
    }
  }

  @Override public void onCreate(Bundle state) {
    super.onCreate(state);
    try {
      requestWindowFeature(Window.FEATURE_NO_TITLE);
      getWindow().setStatusBarColor(Color.TRANSPARENT);
      getWindow().setNavigationBarColor(Color.TRANSPARENT);
      enableImmersiveMode();

      web = new WebView(this);
      web.setBackgroundColor(Color.rgb(243, 247, 253));
      web.setOverScrollMode(View.OVER_SCROLL_NEVER);

      WebSettings settings = web.getSettings();
      settings.setJavaScriptEnabled(true);
      settings.setDomStorageEnabled(true);
      settings.setAllowFileAccess(true);
      settings.setAllowContentAccess(true);
      settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);

      web.setWebChromeClient(new WebChromeClient());
      web.setWebViewClient(new WebViewClient() {
        @Override public void onPageFinished(WebView view, String url) {
          super.onPageFinished(view, url);
          view.evaluateJavascript(
            "(()=>{const s=document.getElementById('vk-splash');if(s){s.classList.add('hide');setTimeout(()=>s.remove(),500)}})();",
            null
          );
        }
      });

      setContentView(web);
      web.loadUrl("file:///android_asset/index.html");
    } catch (Throwable error) {
      showStartupError(error);
    }
  }

  @Override public void onBackPressed() {
    if (web != null && web.canGoBack()) web.goBack();
    else super.onBackPressed();
  }

  @Override protected void onDestroy() {
    if (web != null) {
      try {
        web.stopLoading();
        web.loadUrl("about:blank");
        web.destroy();
      } catch (Throwable ignored) { }
      web = null;
    }
    super.onDestroy();
  }
}