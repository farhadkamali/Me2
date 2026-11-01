package ir.vamkamali.manager;

import android.app.*;
import android.os.*;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.view.*;
import android.widget.*;
import org.json.*;
import java.net.*;
import java.io.*;

public class MainActivity extends Activity {
 private static final String API="https://farhadkamalii.ir/api/"; private String token="";
 private LinearLayout root,list; private int editId=0; private EditText user,pass,title,bank,amount,price,term,rate,desc; private int blue=Color.rgb(25,92,210),ink=Color.rgb(25,39,66),pale=Color.rgb(242,247,255);
 GradientDrawable bg(int c){GradientDrawable d=new GradientDrawable();d.setColor(c);d.setCornerRadius(22);return d;}
 TextView label(String s,int z,int c){TextView t=new TextView(this);t.setText(s);t.setTextSize(z);t.setTextColor(c);t.setTypeface(null,Typeface.BOLD);t.setPadding(0,10,0,10);return t;}
 EditText field(String s){EditText e=new EditText(this);e.setHint(s);e.setSingleLine(false);e.setPadding(14,8,14,8);e.setBackground(bg(Color.WHITE));return e;}
 Button btn(String s,Runnable r){Button b=new Button(this);b.setText(s);b.setTextColor(-1);b.setAllCaps(false);b.setBackground(bg(blue));b.setOnClickListener(v->r.run());return b;}
 void base(){ScrollView sc=new ScrollView(this);root=new LinearLayout(this);root.setOrientation(1);root.setPadding(20,18,20,18);root.setBackgroundColor(pale);sc.addView(root);setContentView(sc);}
 public void onCreate(Bundle b){super.onCreate(b);getWindow().setStatusBarColor(blue);showLogin();}
 void showLogin(){base();root.addView(label("وام کمالی | پنل مدیریت",25,blue));user=field("نام کاربری مدیر");pass=field("رمز عبور");pass.setInputType(129);root.addView(user);root.addView(pass);root.addView(btn("ورود مدیر",()->login()));}
 interface Done{void ok(JSONObject o);}
 void req(String action,String method,JSONObject data,Done done){new AsyncTask<Void,Void,JSONObject>(){protected JSONObject doInBackground(Void...v){try{HttpURLConnection c=(HttpURLConnection)new URL(API+"?action="+action).openConnection();c.setRequestMethod(method);c.setConnectTimeout(15000);c.setReadTimeout(15000);if(!token.isEmpty())c.setRequestProperty("Authorization","Bearer "+token);if(data!=null){c.setDoOutput(true);c.setRequestProperty("Content-Type","application/json");c.getOutputStream().write(data.toString().getBytes("UTF-8"));}InputStream in=c.getResponseCode()<400?c.getInputStream():c.getErrorStream();ByteArrayOutputStream o=new ByteArrayOutputStream();byte[] b=new byte[4096];int n;while((n=in.read(b))>0)o.write(b,0,n);return new JSONObject(o.toString("UTF-8"));}catch(Exception e){return null;}}protected void onPostExecute(JSONObject o){if(o==null||!o.optBoolean("ok")){Toast.makeText(MainActivity.this,o==null?"خطا در اتصال":o.optString("error","خطای سرور"),Toast.LENGTH_LONG).show();return;}done.ok(o);}}.execute();}
 void login(){JSONObject d=new JSONObject();try{d.put("username",user.getText().toString().trim());d.put("password",pass.getText().toString());}catch(Exception e){}req("login","POST",d,o->{token=o.optString("token");req("admin-check","GET",null,x->{if(!x.optBoolean("admin")){token="";Toast.makeText(this,"این حساب دسترسی مدیر ندارد",Toast.LENGTH_LONG).show();showLogin();return;}showPanel();});});}
 void showPanel(){base();root.addView(label("مدیریت آگهی‌ها",25,blue));root.addView(label("ثبت یا ویرایش آگهی اطلاع‌رسانی",17,ink));title=field("عنوان آگهی");bank=field("نام بانک");amount=field("مبلغ (تومان)");price=field("قیمت اعلامی (تومان)");term=field("مدت بازپرداخت به ماه");rate=field("نرخ سود اعلامی");desc=field("توضیحات");for(EditText e:new EditText[]{title,bank,amount,price,term,rate,desc})root.addView(e);root.addView(btn("ثبت آگهی",()->save()));root.addView(label("فهرست آگهی‌ها",20,ink));list=new LinearLayout(this);list.setOrientation(1);root.addView(list);root.addView(btn("خروج",()->{token="";showLogin();}));load();}
 void save(){JSONObject d=new JSONObject();try{d.put("id",editId);d.put("title",title.getText().toString().trim());d.put("bank",bank.getText().toString().trim());d.put("loan_amount",Long.parseLong(amount.getText().toString().replaceAll("[^0-9]","")));d.put("price",Long.parseLong(price.getText().toString().replaceAll("[^0-9]","")));d.put("term_months",Integer.parseInt(term.getText().toString()));d.put("interest_rate",rate.getText().toString());d.put("description",desc.getText().toString());}catch(Exception e){Toast.makeText(this,"اطلاعات را کامل و عددی وارد کنید",Toast.LENGTH_LONG).show();return;}req("admin-ad-save","POST",d,o->{Toast.makeText(this,"آگهی ثبت شد",Toast.LENGTH_SHORT).show();for(EditText x:new EditText[]{title,bank,amount,price,term,rate,desc})x.setText("");editId=0;load();});}
 void load(){req("admin-ads","GET",null,o->{list.removeAllViews();JSONArray a=o.optJSONArray("ads");if(a==null)return;for(int i=0;i<a.length();i++){JSONObject x=a.optJSONObject(i);if(x==null)continue;final int id=x.optInt("id");TextView t=label(x.optString("bank")+" - "+x.optString("title")+" | "+(x.optInt("is_active")==1?"فعال":"غیرفعال"),16,ink);list.addView(t);list.addView(btn("ویرایش",()->{editId=id;title.setText(x.optString("title"));bank.setText(x.optString("bank"));amount.setText(String.valueOf(x.optLong("loan_amount")));price.setText(String.valueOf(x.optLong("price")));term.setText(String.valueOf(x.optInt("term_months")));rate.setText(x.optString("interest_rate"));desc.setText(x.optString("description"));Toast.makeText(this,"اطلاعات را اصلاح و دوباره ذخیره کنید؛ شناسه آگهی: "+id,Toast.LENGTH_LONG).show();}));list.addView(btn("غیرفعال‌کردن",()->{JSONObject d=new JSONObject();try{d.put("id",id);}catch(Exception e){}req("admin-ad-delete","POST",d,z->load());}));}});}
}