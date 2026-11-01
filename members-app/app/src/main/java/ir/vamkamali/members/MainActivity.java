package ir.vamkamali.members;

import android.app.*;
import android.os.*;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.view.*;
import android.view.inputmethod.InputMethodManager;
import android.content.*;
import android.widget.*;
import org.json.*;
import java.net.*;
import java.io.*;

public class MainActivity extends Activity {
  private static final String API="https://farhadkamalii.ir/api/";
  private String token="";
  private LinearLayout root, content;
  private EditText username,password,search;
  private JSONArray ads=new JSONArray();
  private int blue=Color.rgb(25,92,210), ink=Color.rgb(25,39,66), pale=Color.rgb(242,247,255);
  public void onCreate(Bundle b){super.onCreate(b);getWindow().setStatusBarColor(blue);getWindow().setNavigationBarColor(Color.WHITE);showAuth();}
  GradientDrawable bg(int color,int radius){GradientDrawable d=new GradientDrawable();d.setColor(color);d.setCornerRadius(radius);return d;}
  TextView text(String s,int size,int color,boolean bold){TextView t=new TextView(this);t.setText(s);t.setTextSize(size);t.setTextColor(color);if(bold)t.setTypeface(null,Typeface.BOLD);t.setPadding(0,8,0,8);return t;}
  Button button(String s,Runnable r){Button b=new Button(this);b.setText(s);b.setTextColor(Color.WHITE);b.setAllCaps(false);b.setBackground(bg(blue,24));b.setOnClickListener(v->r.run());return b;}
  EditText field(String hint, boolean secret){EditText e=new EditText(this);e.setSingleLine(true);e.setHint(hint);e.setTextSize(15);e.setPadding(18,10,18,10);e.setBackground(bg(Color.WHITE,18));if(secret)e.setInputType(129);return e;}
  void base(){root=new LinearLayout(this);root.setOrientation(1);root.setPadding(20,18,20,16);root.setBackgroundColor(pale);ScrollView sc=new ScrollView(this);sc.addView(root);setContentView(sc);}
  void showAuth(){base();root.addView(text("وام کمالی",30,blue,true));root.addView(text("سامانه اطلاع‌رسانی آگهی‌های تسهیلات",15,ink,false));View gap=new View(this);root.addView(gap,new LinearLayout.LayoutParams(1,35));root.addView(text("ورود اعضا",23,ink,true));username=field("نام کاربری",false);password=field("رمز عبور",true);root.addView(username);root.addView(password);root.addView(button("ورود",()->auth("login")));root.addView(button("ایجاد حساب",()->auth("register")));}
  void auth(String action){String u=username.getText().toString().trim(),p=password.getText().toString();if(u.isEmpty()||p.isEmpty()){toast("نام کاربری و رمز عبور را وارد کنید");return;}JSONObject d=new JSONObject();try{d.put("username",u);d.put("password",p);}catch(Exception ignored){}request(action,"POST",d,false,res->{token=res.optString("token");if(token.isEmpty()){toast("توکن ورود دریافت نشد");return;}loadAds();});}
  interface Done{void ok(JSONObject o);}
  void request(String action,String method,JSONObject data,boolean auth,Done done){new AsyncTask<Void,Void,JSONObject>(){String error="";protected JSONObject doInBackground(Void...v){try{HttpURLConnection c=(HttpURLConnection)new URL(API+"?action="+action).openConnection();c.setRequestMethod(method);c.setConnectTimeout(15000);c.setReadTimeout(15000);if(auth&&!token.isEmpty())c.setRequestProperty("Authorization","Bearer "+token);if(data!=null){c.setDoOutput(true);c.setRequestProperty("Content-Type","application/json; charset=utf-8");c.getOutputStream().write(data.toString().getBytes("UTF-8"));}InputStream in=(c.getResponseCode()<400)?c.getInputStream():c.getErrorStream();ByteArrayOutputStream out=new ByteArrayOutputStream();byte[] buf=new byte[4096];int n;while((n=in.read(buf))>0)out.write(buf,0,n);return new JSONObject(out.toString("UTF-8"));}catch(Exception e){error=e.getMessage();return null;}}protected void onPostExecute(JSONObject o){if(o==null||!o.optBoolean("ok")){toast(o==null?"خطا در اتصال: "+error:o.optString("message",o.optString("error","خطای سرور")));return;}done.ok(o);}}.execute();}
  void loadAds(){request("ads","GET",null,false,o->{ads=o.optJSONArray("ads");if(ads==null)ads=new JSONArray();showHome();});}
  void showHome(){base();LinearLayout head=new LinearLayout(this);head.setOrientation(1);head.setPadding(18,18,18,18);head.setBackground(bg(blue,24));TextView h=text("وام کمالی",25,Color.WHITE,true);head.addView(h);head.addView(text("آگهی‌های اطلاع‌رسانی تسهیلات",14,Color.WHITE,false));root.addView(head);root.addView(text("جست‌وجو در آگهی‌ها",17,ink,true));search=field("نام بانک یا عنوان آگهی",false);root.addView(search);Button go=button("جست‌وجو",()->renderAds());root.addView(go);content=new LinearLayout(this);content.setOrientation(1);root.addView(content);renderAds();root.addView(button("خروج از حساب",()->{token="";showAuth();}));}
  void renderAds(){if(content==null)return;content.removeAllViews();String q=search.getText().toString().toLowerCase();for(int i=0;i<ads.length();i++){JSONObject a=ads.optJSONObject(i);if(a==null)continue;String title=a.optString("title","آگهی تسهیلات"),bank=a.optString("bank","بانک");if(!(title+" "+bank).toLowerCase().contains(q))continue;LinearLayout card=new LinearLayout(this);card.setOrientation(1);card.setPadding(16,12,16,12);card.setBackground(bg(Color.WHITE,20));LinearLayout.LayoutParams lp=new LinearLayout.LayoutParams(-1,-2);lp.setMargins(0,8,0,8);content.addView(card,lp);card.addView(text(bank+"  •  "+title,18,ink,true));card.addView(text("مبلغ: "+a.optString("loan_amount","—")+" تومان",16,blue,true));card.addView(text("بازپرداخت: "+a.optString("term_months","—")+" ماه   |   سود اعلامی: "+a.optString("interest_rate","—"),14,ink,false));Button detail=button("جزئیات",()->detail(a));card.addView(detail);}}
  void detail(JSONObject a){new AlertDialog.Builder(this).setTitle(a.optString("title")).setMessage("بانک: "+a.optString("bank")+"\nمبلغ: "+a.optString("loan_amount")+" تومان\nقیمت اعلامی: "+a.optString("price")+" تومان\nمدت: "+a.optString("term_months")+" ماه\nنرخ سود: "+a.optString("interest_rate")+"\n\n"+a.optString("description")+"\n\nاین برنامه صرفاً اطلاع‌رسانی است؛ اطلاعات را پیش از هر تصمیمی مستقلاً بررسی کنید.").setPositiveButton("بستن",null).show();}
  void toast(String s){Toast.makeText(this,s,Toast.LENGTH_LONG).show();}
}