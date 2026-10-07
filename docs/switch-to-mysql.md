# نقل السيرفر الأونلاين إلى MySQL — نفس قاعدة سيرفر العميل

سيرفر العميل (Laragon أوفلاين) يعمل على **MySQL**. حتى يكون كل ما يُجرَّب أونلاين هو نفسه ما سيحدث عند العميل،
السيرفر الأونلاين يعمل على MySQL أيضاً. هذا الدليل ينقله من PostgreSQL إلى MySQL **مع كل البيانات**
(المستخدمون، الأصناف، الإعدادات، الحالات…)، والقاعدة القديمة تبقى كما هي للرجوع إليها.

> الأوامر تُنفَّذ على الـ VPS من مجلد البرنامج (مثلاً `/var/www/prosthetics`) كمستخدم root أو sudo.

---

## 0) تأكد من القاعدة الحالية

```bash
cd /var/www/prosthetics
grep '^DB_' .env
```

- لو `DB_CONNECTION=mysql` ← السيرفر بالفعل على MySQL، لا تفعل شيئاً.
- لو `DB_CONNECTION=pgsql` ← أكمل. **احتفظ بقيم `DB_DATABASE` و `DB_USERNAME` و `DB_PASSWORD` القديمة** — ستحتاجها في الخطوة 5.

## 1) حدّث البرنامج وخذ نسخة احتياطية (وهو ما زال على PostgreSQL)

```bash
bash deploy.sh                    # يسحب آخر نسخة + نسخة احتياطية + migrate على القاعدة القديمة
php artisan prosthetics:backup    # نسخة إضافية — تُحفظ في storage/backups
```

## 2) ثبّت MySQL وإضافة PHP الخاصة به

```bash
apt-get update
apt-get install -y mysql-server
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
apt-get install -y php${PHPV}-mysql
systemctl enable --now mysql
```

## 3) أنشئ القاعدة والمستخدم

اختر كلمة سر قوية بدل `CHANGE_ME`:

```bash
mysql -uroot <<'SQL'
CREATE DATABASE prosthetics CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'prosthetics_user'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME';
CREATE USER 'prosthetics_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON prosthetics.* TO 'prosthetics_user'@'127.0.0.1';
GRANT ALL PRIVILEGES ON prosthetics.* TO 'prosthetics_user'@'localhost';
FLUSH PRIVILEGES;
SQL
```

MySQL على Ubuntu يستمع على `127.0.0.1` فقط افتراضياً — لا تفتح المنفذ 3306 في الجدار الناري.

## 4) وجّه البرنامج للقاعدة الجديدة وأنشئ الجداول

```bash
php artisan down
cp .env .env.pgsql-backup         # للرجوع إن لزم
```

عدّل في `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prosthetics
DB_USERNAME=prosthetics_user
DB_PASSWORD=CHANGE_ME
```

```bash
php artisan config:clear
php artisan migrate --force
```

## 5) انقل البيانات من PostgreSQL

ضع اسم القاعدة والمستخدم القديمين (من الخطوة 0) — كلمة السر تُطلب بدون أن تظهر:

```bash
php artisan prosthetics:copy-database --driver=pgsql --database=OLD_DB_NAME --username=OLD_DB_USER
```

يُنسخ كل جدول كما هو (نفس الأرقام والعلاقات)، ثم يُعرض جدول بعدد الصفوف في القديمة والجديدة.
يجب أن ينتهي بـ **«تم النقل وكل الجداول متطابقة»**. لو ظهر ✗ أمام أي جدول لا تكمل — ارجع (الخطوة 8).

## 6) شغّل البرنامج

```bash
php artisan prosthetics:sync-permissions
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
systemctl reload php${PHPV}-fpm
php artisan up
```

## 7) تحقق

```bash
php artisan migrate:status | tail -3                 # كلها Ran
php artisan prosthetics:demo-journeys --dry-run      # 5 من 5 — تجربة فقط لا يُحفظ شيء
```

ثم ادخل من المتصفح وتأكد من الأصناف والمستخدمين.
من الآن `deploy.sh` يأخذ النسخة الاحتياطية بـ mysqldump تلقائياً.

## 8) الرجوع لـ PostgreSQL (إن لزم)

```bash
cp .env.pgsql-backup .env
php artisan optimize:clear && php artisan config:cache
systemctl reload php${PHPV}-fpm
```

القاعدة القديمة لم يُكتب فيها شيء. بعد أسبوع من العمل بدون مشاكل يمكن إيقاف PostgreSQL.

---

## بديل: بداية نظيفة بدون نقل البيانات

لو البيانات الأونلاين تجريبية ولا تحتاجها، بدل الخطوة 5:

```bash
SEED_SUPER_ADMIN_PASSWORD='كلمة-سر-قوية' php artisan db:seed --class=RolesAndAdminSeeder --force
```

ثم ارفع شيت الأصناف من صفحة الأصناف.

---

## سيرفر العميل (Laragon — أوفلاين)

نفس الإعدادات في `.env` مع بيانات MySQL الخاصة بـ Laragon. التحديث بعد أي نسخة جديدة:

```
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan prosthetics:sync-permissions
php artisan optimize:clear
```

ملاحظات أمان: ضع كلمة سر لمستخدم `root` في MySQL (من Laragon ← MySQL ← Change root password)
وحدّث `DB_PASSWORD` في `.env`، ولا تفتح المنفذ 3306 على الشبكة. واضبط تاريخ وساعة Windows تلقائياً —
أرقام الحالات، دور العيادة اليومي، والتقارير كلها تعتمد على تاريخ السيرفر.
