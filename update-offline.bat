@echo off
chcp 65001 >nul
REM ============================================================
REM  تحديث السيرفر الأوفلاين بضغطة واحدة (Laragon)
REM  دبل كليك على الملف ده — يعمل نسخة احتياطية، يسحب آخر كود،
REM  يحدّث قاعدة البيانات والصلاحيات، ويمسح الكاش.
REM ============================================================
cd /d "%~dp0"

echo [1/6] نسخة احتياطية قبل التحديث...
php artisan prosthetics:backup
if errorlevel 1 echo    (تنبيه: النسخة الاحتياطية لم تكتمل — نكمل التحديث)

echo [2/6] سحب آخر نسخة من الكود...
git pull origin master
if errorlevel 1 goto fail

echo [3/6] مكتبات PHP (لو اتغيّرت)...
call composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
if errorlevel 1 echo    (تنبيه: composer لم يكتمل — لو ظهر خطأ في البرنامج ابعته)

echo [4/6] تحديث قاعدة البيانات...
php artisan migrate --force
if errorlevel 1 goto fail

echo [5/6] مزامنة الصلاحيات الجديدة...
php artisan prosthetics:sync-permissions
if errorlevel 1 goto fail

echo [6/6] مسح الكاش...
php artisan optimize:clear

echo.
echo ✅ تم التحديث بنجاح — اطلب من الموظفين تسجيل خروج ثم دخول.
pause
exit /b 0

:fail
echo.
echo ❌ التحديث توقف — صوّر الشاشة دي وابعتها.
pause
exit /b 1