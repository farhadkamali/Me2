package ir.vamkamali.members;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import androidx.core.app.NotificationCompat;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

public class MemberMessagingService extends FirebaseMessagingService {
    private static final String CHANNEL_ID = "vam_kamali_updates";
    @Override public void onNewToken(String token) {
        super.onNewToken(token);
        getSharedPreferences("vamkamali_push", MODE_PRIVATE).edit().putString("fcm_token", token).apply();
    }
    @Override public void onMessageReceived(RemoteMessage message) {
        super.onMessageReceived(message);
        String title = "وام کمالی", body = "پیام جدید دریافت کردید.";
        if (message.getNotification() != null) {
            if (message.getNotification().getTitle() != null) title = message.getNotification().getTitle();
            if (message.getNotification().getBody() != null) body = message.getNotification().getBody();
        }
        showNotification(title, body);
    }
    private void showNotification(String title, String body) {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) return;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, "اعلان‌های وام کمالی", NotificationManager.IMPORTANCE_HIGH);
            channel.setDescription("پیام‌های پشتیبانی و اطلاعیه‌ها");
            manager.createNotificationChannel(channel);
        }
        Intent intent = new Intent(this, MainActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pending = PendingIntent.getActivity(this, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= 23 ? PendingIntent.FLAG_IMMUTABLE : 0));
        NotificationCompat.Builder note = new NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(getApplicationInfo().icon).setContentTitle(title).setContentText(body)
            .setStyle(new NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH).setAutoCancel(true).setContentIntent(pending);
        manager.notify((int) System.currentTimeMillis(), note.build());
    }
}