#!/usr/bin/env bash
#
# Full functional suite for Web Server Information. Run before every release:
#
#   bash tests/functional/run.sh
#
# Drives the plugin's screens in headless Chromium as an administrator and as a
# subscriber: the Overview, PHP and Database tabs, the server location lookup,
# the admin-footer setting, the review prompt, and access control. Fails on any
# case that misbehaves or on new PHP errors from this plugin in debug.log. The
# site's settings, counter and location cache are restored afterwards, even after
# a failure, and the plugin is left active or inactive as it was found.
#
# Needs: WP-CLI and Node with Playwright (PW_NODE_PATH, or a node_modules below).
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PATH="${WP_PATH:-/Applications/MAMP/htdocs/wpheka-plugins}"
STATE="$(mktemp -d)"
export WSI_STATE="$STATE" WP_PATH

for candidate in "${PW_NODE_PATH:-}" \
    "$WP_PATH/wp-content/plugins/wc-moneris-payment-gateway/node_modules" \
    "$WP_PATH/wp-content/plugins/wc-moneris-payment-gateway-pro/node_modules"; do
  if [ -n "$candidate" ] && [ -d "$candidate/playwright" ]; then export NODE_PATH="$candidate"; break; fi
done
[ -n "${NODE_PATH:-}" ] || { echo "Playwright not found; set PW_NODE_PATH"; exit 2; }

wpe() { wp --path="$WP_PATH" eval-file "$@" 2>&1 | grep -v -e 'Deprecated' -e '^$' -e 'Undefined array key 1'; }
cleanup() {
  wpe "$HERE/state.php" cleanup | sed 's/^/  /'
  rm -rf "$STATE"
}
# Clean up on every way out: normal exit, Ctrl-C, kill, a closed terminal.
CLEANED=0
on_exit() { [ "$CLEANED" = 1 ] && return; CLEANED=1; cleanup; }
trap on_exit EXIT
trap 'on_exit; exit 130' INT TERM HUP

LOG="$WP_PATH/wp-content/debug.log"
OFFSET=$(wc -c < "$LOG" 2>/dev/null || echo 0)

echo "== setup";   wpe "$HERE/state.php" setup | sed 's/^/  /'
[ -f "$STATE/ids.json" ] || { echo "setup failed"; exit 2; }
echo "== screens"; node "$HERE/admin.js" || true

NEW=$(tail -c +$((OFFSET + 1)) "$LOG" 2>/dev/null | grep -v 'PHP Deprecated' | grep 'wpheka-web-server-information' | grep -v '_load_textdomain_just_in_time' || true)
if [ -n "$NEW" ]; then echo "$NEW" | cut -c1-300; echo "FAIL|debug.log clean -- new errors above" >> "$STATE/results"; else echo "PASS|debug.log clean" >> "$STATE/results"; fi

echo; echo "== results"
PASS=$(grep -c '^PASS|' "$STATE/results"); FAIL=$(grep -c '^FAIL|' "$STATE/results")
sed 's/^\([A-Z]*\)|/\1  /' "$STATE/results"
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
