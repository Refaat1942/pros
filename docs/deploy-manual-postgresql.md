# دليل النشر النهائي — Smart Prosthetics ERP
## Windows · Laragon · PostgreSQL · شبكة محلية (أوفلاين)

> **لمن هذا الدليل؟**  
> للفني أو المنفّذ الذي يركّب النظام على سيرفر العميل — **بدون خبرة عميقة في السيرفرات**.  
> اتبع الخطوات **بالترتيب**. انسخ الأوامر كما هي.

---

## ماذا ستبني؟

```
أجهزة الموظفين (Chrome فقط)
        ↓
   شبكة LAN (داخل المركز)
        ↓
جهاز Windows واحد = السيرفر
   ├── Laragon (Apache + PHP)
   ├── PostgreSQL (قاعدة البيانات)
   └── Laravel (التطبيق)
        ↓
نسخة احتياطية يومية (pg_dump)
```

- **الإنترنت الخارجي:** غير مطلوب للعمل اليومي  
- **Static IP:** للوصول داخل الشبكة (مثل `192.168.1.50`)  
- **PostgreSQL:** قاعدة البيانات الرسمية للنشر المحلي

---

# القسم 1 — قبل البدء (اسأل العميل)

| # | مطلوب | ✓ |
|---|--------|---|
| 1 | جهاز Windows **مخصص** للسيرفر (لا يُستخدم كاستقبال يومياً) | ☐ |
| 2 | الجهاز يظل **شغّالاً** في ساعات العمل (يفضّل 24/7) | ☐ |
| 3 | **UPS** (بطارية) موصلة بالسيرفر | ☐ |
| 4 | **IP ثابت** محجوز في الراوتر (DHCP Reservation) | ☐ |
| 5 | شبكة **LAN** بين أجهزة المركز | ☐ |
| 6 | شخص IT أو مسؤول اتصالات (للجدار الناري إن لزم) | ☐ |

**اكتب هنا قبل الزيارة:**

| البند | القيمة |
|-------|--------|
| IP السيرفر | `________________` |
| رابط الموظفين | `http://________________` |
| اسم العميل | `________________` |
| تاريخ النشر | `________________` |

---

# القسم 2 — تثبيت البرامج على السيرفر

## الخطوة 1 — تحديث Windows

1. Settings → Windows Update  
2. ثبّت كل التحديثات  
3. أعد التشغيل إن طُلب

## الخطوة 2 — Laragon

1. حمّل **Laragon Full**: https://laragon.org/download/  
2. ثبّت في `C:\laragon`  
3. افتح Laragon → **Start All** (يجب أن يظهر Apache أخضر)

## الخطوة 3 — PostgreSQL (مهم)

1. Laragon → **Menu → PostgreSQL → Version → Install** (أو Add-on)  
2. انتظر حتى ينتهي التثبيت  
3. **Start All** مرة أخرى  
4. PostgreSQL يعمل على المنفذ **5432**

## الخطوة 4 — Git

1. حمّل: https://git-scm.com/download/win  
2. ثبّت بالإعدادات الافتراضية

## الخطوة 5 — تحقق من PHP + PostgreSQL

افتح **Command Prompt** واكتب:

```cmd
php -v
php -m | findstr pgsql
```

**يجب** أن ترى: `pgsql` و `pdo_pgsql`  
إن لم تظهر → Laragon → Menu → PHP → php.ini → فعّل `extension=pdo_pgsql` و `extension=pgsql` → أعد Start All

---

# القسم 3 — PostgreSQL: قاعدة بيانات صحية وآمنة

## الخطوة 6 — افتح psql

**طريقة 1 (Laragon):** Menu → PostgreSQL → psql  
**طريقة 2 (CMD):**

```cmd
cd C:\laragon\bin\postgresql\postgresql-*\bin
psql -U postgres
```

(كلمة مرور postgres — التي عيّنتها عند التثبيت)

## الخطوة 7 — أنشئ القاعدة والمستخدم

**انسخ والصق في psql** (غيّر كلمة المرور):

```sql
CREATE DATABASE prosthetics
  ENCODING 'UTF8'
  LC_COLLATE 'C'
  LC_CTYPE 'C'
  TEMPLATE template0;

CREATE USER prosthetics_user WITH PASSWORD 'StrongPassword2026!ChangeMe';

GRANT ALL PRIVILEGES ON DATABASE prosthetics TO prosthetics_user;

\c prosthetics

GRANT ALL ON SCHEMA public TO prosthetics_user;
GRANT CREATE ON SCHEMA public TO prosthetics_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO prosthetics_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO prosthetics_user;

\q
```

### لماذا PostgreSQL؟
- **UTF8** — يدعم العربية بالكامل  
- **مستخدم مخصص** — ليس superuser (أكثر أماناً)  
- **موثوق** — مناسب للبيانات الطبية والمالية والمخزون

**⚠️ ممنوع:** وضع `postgres` في ملف `.env` للتطبيق

---

# القسم 4 — تنزيل وتشغيل التطبيق

## الخطوة 8 — Clone المشروع

```cmd
cd C:\laragon\www
git clone https://github.com/Refaat1942/pros.git prosthetics
cd prosthetics
```

## الخطوة 9 — Composer

```cmd
composer install --no-dev --optimize-autoloader
```

## الخطوة 10 — ملف `.env`

```cmd
copy .env.example .env
php artisan key:generate
notepad .env
```

**عدّل هذه الأسطر** (ضع IP السيرفر الحقيقي):

```env
APP_NAME="Smart Prosthetics ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.50

LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=prosthetics
DB_USERNAME=prosthetics_user
DB_PASSWORD=StrongPassword2026!ChangeMe

SESSION_LIFETIME=5
SESSION_IDLE_TIMEOUT=5
SESSION_SECURE_COOKIE=false

TELEGRAM_ERROR_NOTIFY=false
FIREBASE_ENABLED=false
```

> `SESSION_SECURE_COOKIE=true` **فقط** إذا استخدمت HTTPS لاحقاً

## الخطوة 11 — إنشاء الجداول

```cmd
cd C:\laragon\www\prosthetics
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

إن ظهر خطأ اتصال → راجع `.env` وتأكد PostgreSQL شغّال (Laragon → Start All)

---

# القسم 5 — الشبكة والأمان

## الخطوة 12 — اعرف IP السيرفر

```cmd
ipconfig
```

ابحث عن **IPv4 Address** — مثال: `192.168.1.50`  
حدّث `APP_URL` في `.env` ثم:

```cmd
php artisan config:cache
```

## الخطوة 13 — اختبار

| من أين | الرابط | النتيجة المتوقعة |
|--------|--------|------------------|
| السيرفر نفسه | `http://127.0.0.1` | صفحة تسجيل الدخول |
| PC آخر في المكتب | `http://192.168.1.50` | صفحة تسجيل الدخول |

## الخطوة 14 — الجدار الناري (Firewall)

1. Windows Firewall → Allow **Apache** على **Private network**  
2. **Block** على Public network  
3. **لا** تفتح المنفذ 80 للإنترنت بدون IT + VPN/HTTPS

---

# القسم 6 — النسخ الاحتياطي (PostgreSQL)

## الخطوة 15 — اختبار يدوي

```cmd
cd C:\laragon\www\prosthetics
php artisan prosthetics:backup
```

- يستخدم **pg_dump** تلقائياً  
- الملفات: `C:\laragon\www\prosthetics\storage\backups`  
- يحتفظ بـ **7 أيام** تلقائياً

## الخطوة 16 — جدولة يومية (2:00 AM)

1. Task Scheduler → Create Basic Task  
2. Daily · 2:00 AM  
3. Action: Start a program  

**Program:**

```
C:\laragon\bin\php\php-8.x.x\php.exe
```

(استبدل `php-8.x.x` بمجلد PHP الفعلي في Laragon)

**Arguments:**

```
C:\laragon\www\prosthetics\artisan prosthetics:backup
```

**Start in:**

```
C:\laragon\www\prosthetics
```

## الخطوة 17 — نسخ خارجي (أسبوعياً)

انسخ مجلد `storage\backups` إلى قرص USB أو جهاز آخر — **مرة أسبوعياً**

---

# القسم 7 — تسليم للعميل

## الخطوة 18 — حسابات المستخدمين

1. سجّل دخول Admin  
2. أنشئ حساباً لكل قسم (استقبال، طبيب، مخزن…)  
3. **كل موظف = login خاص** — لا مشاركة كلمات المرور

## الخطوة 19 — أجهزة الموظفين (سهل)

على **كل PC**:

1. Chrome → `http://192.168.1.50`  
2. Bookmark (Ctrl+D)  
3. اختصار سطح المكتب:  
   - يمين → جديد → اختصار  
   - الموقع: `http://192.168.1.50`  
   - الاسم: **نظام الأطراف الصناعية**

**لا يُثبَّت شيء** على PCs الموظفين.

---

# القسم 8 — التحديث (بعد push من GitHub)

**على السيرفر فقط — بعد ساعات العمل:**

```cmd
cd C:\laragon\www\prosthetics
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

أخبر الموظفين: **Ctrl + F5** في المتصفح

---

# القسم 9 — حل المشاكل (PostgreSQL)

| المشكلة | الحل |
|---------|------|
| `could not connect to server` | Laragon → Start All · PostgreSQL غير شغّال |
| `password authentication failed` | راجع `DB_PASSWORD` في `.env` |
| `could not find driver` | فعّل `pdo_pgsql` في php.ini |
| `pg_dump failed` | أضف `C:\laragon\bin\postgresql\...\bin` إلى PATH |
| `relation does not exist` | شغّل `php artisan migrate --force` |
| الصفحة لا تفتح من PC آخر | Firewall · IP خاطئ · السيرفر مطفأ |
| بطء | أعد Start All · تحقق من مساحة القرص |

---

# قائمة تحقق نهائية ✓

**السيرفر**
- [ ] Laragon + PostgreSQL + Git
- [ ] `pdo_pgsql` يعمل
- [ ] قاعدة `prosthetics` + مستخدم `prosthetics_user`
- [ ] `.env` — `pgsql` · `APP_DEBUG=false`
- [ ] `migrate --force` نجح
- [ ] Login يفتح من السيرفر وPC آخر

**الأمان**
- [ ] كلمات مرور قوية
- [ ] Firewall — Private فقط
- [ ] Telegram/Firebase معطّلان
- [ ] Idle logout 5 دقائق

**النسخ الاحتياطي**
- [ ] `prosthetics:backup` يدوياً نجح
- [ ] Task Scheduler يومي
- [ ] نسخ خارجي أسبوعي

**التسليم**
- [ ] حسابات الأقسام
- [ ] اختصارات الموظفين
- [ ] تسليم ورقة IT للعميل (الملف المطبوع)

---

# ورقة IT للعميل (ملخص)

| البند | القيمة |
|-------|--------|
| رابط النظام | `http://_______________` |
| جهاز السيرفر | _______________ |
| قاعدة البيانات | PostgreSQL · `prosthetics` |
| النسخ الاحتياطي | يومياً 2:00 AM · `storage\backups` |
| مسؤول النظام | _______________ · هاتف: _______________ |
| الدعم الفني | _______________ · هاتف: _______________ |

**قواعد:**
- لا تُطفأ جهاز السيرفر في ساعات العمل  
- لا تُغيَّر IP بدون تحديث الإعدادات  
- الإنترنت الخارجي غير مطلوب للعمل اليومي  
- عند انقطاع الكهرباء: شغّل السيرفر → Laragon → Start All  

---

*Smart Prosthetics ERP · Deploy Manual v2.0 · PostgreSQL · github.com/Refaat1942/pros*
