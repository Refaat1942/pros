#!/usr/bin/env bash
# يبني باكدج أوفلاين واحدة (ZIP) فيها الكود + مكتبات vendor جاهزة — مفيش نت ولا git ولا composer على جهاز العميل.
#   bash scripts/offline-package/build.sh            → dist/prosthetics-offline-<تاريخ>-<commit>.zip
# جوه الـ ZIP:
#   UPDATE.bat  — تحديث سيرفر قائم (نسخة احتياطية + نسخ الملفات + migrate + الصلاحيات + الكاش)
#   INSTALL.bat — تسطيب أول مرة على جهاز عليه Laragon
#   files\      — ملفات البرنامج
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

SHA="$(git rev-parse --short HEAD)"
STAMP="$(date -u +%Y%m%d-%H%M)"
NAME="prosthetics-offline-${STAMP}-${SHA}"
STAGE="$(mktemp -d)"
PKG="$STAGE/$NAME"
mkdir -p "$PKG/files" "$ROOT/dist"

echo "==> تصدير الكود (الملفات المتتبَّعة في git فقط)"
git archive HEAD | tar -x -C "$PKG/files"

echo "==> حذف ما لا يلزم جهاز العميل"
( cd "$PKG/files"
  rm -rf tests docs .github .cursor scripts node_modules deploy.sh update-offline.bat phpunit.xml \
         package.json package-lock.json vite.config.js tailwind.config.js .editorconfig .gitattributes \
         'seedRoleDefaults()' 'toArray())' $'\xf0\x9f\x93\xa6'
  rm -f bootstrap/cache/*.php public/hot
  rm -rf public/storage )

echo "==> تثبيت مكتبات PHP للإنتاج (بدون أدوات التطوير)"
( cd "$PKG/files" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --no-progress )
# لو composer نزّل من المصدر (git) بدل الـ dist — نشيل تاريخ git عشان الحجم.
find "$PKG/files/vendor" -name .git -type d -prune -exec rm -rf {} +

echo "==> سكريبتات ويندوز + رقم الإصدار"
for f in UPDATE.bat INSTALL.bat; do
  # ملفات bat لازم CRLF.
  sed 's/\r$//; s/$/\r/' "$ROOT/scripts/offline-package/windows/$f" > "$PKG/$f"
done
mkdir -p "$PKG/tools"
cp "$ROOT/scripts/offline-package/windows/create-database.php" "$PKG/tools/"
sed 's/\r$//; s/$/\r/' "$ROOT/scripts/offline-package/windows/README-AR.txt" > "$PKG/README-AR.txt"
printf 'version=%s\ncommit=%s\nbuilt_utc=%s\n' "$STAMP" "$(git rev-parse HEAD)" "$(date -u +%FT%TZ)" > "$PKG/files/VERSION.txt"
cp "$PKG/files/VERSION.txt" "$PKG/VERSION.txt"

echo "==> ضغط"
( cd "$STAGE" && rm -f "$ROOT/dist/$NAME.zip" && zip -qr -X "$ROOT/dist/$NAME.zip" "$NAME" )
rm -rf "$STAGE"
echo "✅ $ROOT/dist/$NAME.zip ($(du -h "$ROOT/dist/$NAME.zip" | cut -f1))"
