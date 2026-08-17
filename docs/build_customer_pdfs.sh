#!/usr/bin/env bash
# Generate printable PDFs from docs HTML sources.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOCS="$ROOT/docs"
OUT="$DOCS/pdf"
mkdir -p "$OUT"

CHROME="${CHROME:-google-chrome}"
if ! command -v "$CHROME" >/dev/null 2>&1; then
  CHROME="chromium"
fi

print_pdf() {
  local html="$1"
  local pdf="$2"
  local name
  name="$(basename "$html")"
  echo "→ $name → $(basename "$pdf")"
  local profile
  profile="$(mktemp -d)"
  timeout 30 "$CHROME" \
    --headless=new \
    --disable-gpu \
    --no-sandbox \
    --no-pdf-header-footer \
    --user-data-dir="$profile" \
    --print-to-pdf="$pdf" \
    "file://$html" 2>/dev/null || true
  rm -rf "$profile"
  [ -f "$pdf" ] || { echo "Failed: $pdf" >&2; exit 1; }
}

print_pdf "$DOCS/customer-system-blueprint-en.html" "$OUT/Smart-Prosthetics-ERP-System-Blueprint-EN.pdf"
print_pdf "$DOCS/customer-system-architecture-en.html" "$OUT/Smart-Prosthetics-ERP-System-Architecture-EN.pdf"
print_pdf "$DOCS/deploy-simple-print.html" "$OUT/Smart-Prosthetics-ERP-Deploy-Simple-EN.pdf"
print_pdf "$DOCS/deployment-checklist-print.html" "$OUT/Smart-Prosthetics-ERP-Deployment-Checklist-EN.pdf"

echo "Done. PDFs in $OUT"
