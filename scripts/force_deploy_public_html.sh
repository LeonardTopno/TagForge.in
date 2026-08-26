#!/bin/bash
# Run this in cPanel Terminal from the Git deployment checkout, e.g.:
#   bash scripts/force_deploy_public_html.sh
# Or with an explicit destination:
#   DEST=/home4/tagforge/public_html bash scripts/force_deploy_public_html.sh

set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="${DEST:-/home4/tagforge/public_html}"

echo "ROOT=$ROOT"
echo "DEST=$DEST"

if [[ ! -f "$ROOT/api.php" ]]; then
  echo "ERROR: api.php not found at $ROOT/api.php — cd into the Git repo first." >&2
  exit 1
fi
if [[ ! -d "$DEST" ]]; then
  echo "ERROR: DEST does not exist: $DEST" >&2
  echo "Find public_html with: ls -d /home/*/public_html /home4/*/public_html 2>/dev/null" >&2
  exit 1
fi

mkdir -p "$DEST/includes" "$DEST/assets/img" "$DEST/assets/css" "$DEST/assets/js" "$DEST/scripts"

cp -f "$ROOT/api.php" "$DEST/api.php"
cp -f "$ROOT/admin.php" "$DEST/admin.php"
cp -f "$ROOT/app.php" "$DEST/app.php"
cp -f "$ROOT/index.php" "$DEST/index.php"
cp -f "$ROOT/includes/init.php" "$DEST/includes/init.php"
cp -f "$ROOT/includes/version.php" "$DEST/includes/version.php"
cp -f "$ROOT/includes/helpers.php" "$DEST/includes/helpers.php"
cp -f "$ROOT/includes/db.php" "$DEST/includes/db.php"
cp -f "$ROOT/includes/hosts.php" "$DEST/includes/hosts.php"
cp -f "$ROOT/includes/schema.php" "$DEST/includes/schema.php"
cp -f "$ROOT/includes/mail.php" "$DEST/includes/mail.php"
cp -f "$ROOT/includes/razorpay.php" "$DEST/includes/razorpay.php"
cp -f "$ROOT/includes/platform.php" "$DEST/includes/platform.php"
cp -f "$ROOT/includes/auth_guards.php" "$DEST/includes/auth_guards.php"
cp -f "$ROOT/includes/ops_api.php" "$DEST/includes/ops_api.php"
cp -f "$ROOT/includes/admin_api.php" "$DEST/includes/admin_api.php"
[[ -f "$ROOT/includes/seo.php" ]] && cp -f "$ROOT/includes/seo.php" "$DEST/includes/seo.php"
[[ -f "$ROOT/includes/layout.php" ]] && cp -f "$ROOT/includes/layout.php" "$DEST/includes/layout.php"
[[ -f "$ROOT/robots.php" ]] && cp -f "$ROOT/robots.php" "$DEST/robots.php"
[[ -f "$ROOT/sitemap.php" ]] && cp -f "$ROOT/sitemap.php" "$DEST/sitemap.php"
[[ -f "$ROOT/manifest.webmanifest" ]] && cp -f "$ROOT/manifest.webmanifest" "$DEST/manifest.webmanifest"
cp -RF "$ROOT/assets/." "$DEST/assets/" || true
cp -RF "$ROOT/scripts/." "$DEST/scripts/" || true
[[ -f "$ROOT/.htaccess" ]] && cp -f "$ROOT/.htaccess" "$DEST/.htaccess"
touch "$DEST/api.php" "$DEST/includes/auth_guards.php" "$DEST/includes/platform.php" "$DEST/includes/helpers.php" "$DEST/includes/schema.php"

echo "--- verification ---"
grep -n "schema-column-check\|cpanel-force\|auth-guards\|login-platform" "$DEST/api.php" | head
test -f "$DEST/includes/platform.php" && echo "platform.php: OK" || echo "platform.php: MISSING"
test -f "$DEST/includes/auth_guards.php" && echo "auth_guards.php: OK" || echo "auth_guards.php: MISSING"
test -f "$DEST/includes/schema.php" && echo "schema.php: OK" || echo "schema.php: MISSING"
grep -q "information_schema.COLUMNS" "$DEST/includes/schema.php" && echo "column-check fix: OK" || echo "column-check fix: MISSING"
grep -q "function assert_shop_not_suspended" "$DEST/includes/auth_guards.php" && echo "assert polyfill: OK" || echo "assert polyfill: MISSING"
echo "Done. Open https://tagforge.in/api.php?r=version — expect deploy=2026-08-27-schema-column-check"
