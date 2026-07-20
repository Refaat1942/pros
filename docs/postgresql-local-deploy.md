# PostgreSQL — نشر محلي

> **الدليل الكامل:** راجع [`deploy-manual-postgresql.md`](deploy-manual-postgresql.md)  
> هذا الملف ملخص سريع فقط.

## PostgreSQL على Laragon

1. Laragon → Menu → PostgreSQL → Install  
2. Start All  

## إنشاء القاعدة

```sql
CREATE DATABASE prosthetics ENCODING 'UTF8';
CREATE USER prosthetics_user WITH PASSWORD 'YourPassword';
GRANT ALL PRIVILEGES ON DATABASE prosthetics TO prosthetics_user;
\c prosthetics
GRANT ALL ON SCHEMA public TO prosthetics_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO prosthetics_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO prosthetics_user;
```

## `.env`

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=prosthetics
DB_USERNAME=prosthetics_user
DB_PASSWORD=YourPassword
```

## أوامر التشغيل

```cmd
php artisan migrate --force
php artisan prosthetics:backup
```

## PDF

افتح `deployment-checklist-print.html` → Ctrl+P → Save as PDF
