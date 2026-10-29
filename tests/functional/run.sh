#!/usr/bin/env bash
#
# Full functional suite for Web Server Information. Run before every release:
#
#   bash tests/functional/run.sh
#
# Drives the plugin's screens in headless Chromium as an administrator and as a
# subscriber: the Overview, PHP and Database tabs, the server location lookup,
# the admin-footer setting, the review prompt, that other plugins' notices stay
# off the plugin page, and access control. Fails on any
# case that misbehaves or on new PHP errors from this plugin in debug.log. The
# site's settings, counter and location cache are restored afterwards, even after
# a failure, and the plugin is left active or inactive as it was found.
#
# Needs: WP-CLI and Node with Playwright (PW_NODE_PATH, or a node_modules below).
# Optional: WP_CLI="php /path/to/wp-cli.phar" when node cannot start `wp`, and
# PW_CHANNEL=chrome to use the installed Google Chrome.
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

# Runs one browser script. Never "|| true" a test step: a non-zero exit is a
# FAIL, and so is a script that stopped before its final "ran to the end" case
# (an unsettled promise lets node exit 0 halfway through).
run_node() {
  local script="$1"; shift
  node "$HERE/$script" "$@"
  local code=$?
  if [ "$code" -ne 0 ]; then
    echo "  FAIL|$script exited with code $code"
    echo "FAIL|$script exited with code $code" >> "$STATE/results"
  fi
  if ! grep -qxF "PASS|$script: ran to the end" "$STATE/results" 2>/dev/null; then
    echo "  FAIL|$script: ran to the end -- the script stopped before its last case"
    echo "FAIL|$script: ran to the end -- the script stopped before its last case" >> "$STATE/results"
  fi
}

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
echo "== screens"; run_node admin.js
echo "== php checks"
wpe "$HERE/security.php" | grep -E '^(PASS|FAIL)\|' | tee -a "$STATE/results" | sed 's/^/  /'
grep -qxF "PASS|security.php: ran to the end" "$STATE/results" || echo "FAIL|security.php: ran to the end -- it stopped before its last check" >> "$STATE/results"
# The network-wide setting needs a multisite network with the plugin network-activated.
if [ "${WSI_NETWORK:-}" = 1 ]; then
  wpe "$HERE/security.php" network | grep -E '^(PASS|FAIL)\|' | tee -a "$STATE/results" | sed 's/^/  /'
  grep -qxF "PASS|security.php network: ran to the end" "$STATE/results" || echo "FAIL|security.php network: ran to the end -- it stopped before its last check" >> "$STATE/results"
fi

# grep finds nothing in a clean log and exits 1; "|| true" only reads the log here.
NEW=$(tail -c +$((OFFSET + 1)) "$LOG" 2>/dev/null | grep -v 'PHP Deprecated' | grep 'wpheka-web-server-information' | grep -v '_load_textdomain_just_in_time' || true)
if [ -n "$NEW" ]; then echo "$NEW" | cut -c1-300; echo "FAIL|debug.log clean -- new errors above" >> "$STATE/results"; else echo "PASS|debug.log clean" >> "$STATE/results"; fi

echo; echo "== results"
PASS=$(grep -c '^PASS|' "$STATE/results"); FAIL=$(grep -c '^FAIL|' "$STATE/results")
sed 's/^\([A-Z]*\)|/\1  /' "$STATE/results"
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
