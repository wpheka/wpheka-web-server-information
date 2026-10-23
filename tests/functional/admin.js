// Web Server Information as an administrator uses it: the three tabs and what
// each must show, the server location lookup, the admin-footer setting, the
// visit counter behind the review prompt, the prompt's X and "Don't ask again",
// and that a subscriber cannot open the pages or change the setting.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const STATE = process.env.WSI_STATE;
const ids = JSON.parse(fs.readFileSync(path.join(STATE, 'ids.json'), 'utf8'));
// WP_CLI overrides how WP-CLI is called (words split on spaces) on a machine
// where `wp` is a script node cannot start: WP_CLI="php /path/to/wp-cli.phar".
const WP_CLI = (process.env.WP_CLI || 'wp').split(' ');
const st = (...args) => JSON.parse(execFileSync(WP_CLI[0], [...WP_CLI.slice(1), '--path=' + process.env.WP_PATH, 'eval-file', path.join(__dirname, 'state.php'), ...args], { encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop() || 'null');
const record = (ok, name, detail) => { const line = `${ok ? 'PASS' : 'FAIL'}|${name}${ok ? '' : ' -- ' + detail}`; fs.appendFileSync(path.join(STATE, 'results'), line + '\n'); console.log('  ' + line); };
// A crash anywhere in this script is a FAIL, never a silent stop: run.sh also
// fails a non-zero exit and a missing "ran to the end" case.
const crashed = kind => e => {
  const lines = String((e && e.stack) || e).split('\n');
  try { record(false, `admin.js: ${kind}`, [lines[0], (lines.find(l => /^\s+at /.test(l)) || '').trim()].filter(Boolean).join(' ')); } catch (ignored) {}
  process.exit(1);
};
process.on('unhandledRejection', crashed('no unhandled promise rejection'));
process.on('uncaughtException', crashed('no uncaught exception'));
const A = ids.admin_url;
const PAGE = `${A}admin.php?page=wpheka-information`;
const errors = [];
async function check(name, fn) { try { const [ok, detail] = await fn(); record(ok, name, detail); } catch (e) { record(false, name, e.message.split('\n')[0]); } }
const phpError = html => /Fatal error|Warning<\/b>|Notice<\/b>|critical error|Parse error/i.test(html);

(async () => {
  // PW_CHANNEL=chrome runs the installed Google Chrome instead of Playwright's
  // bundled Chromium (for a machine without the matching browser download).
  const browser = await chromium.launch(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {});
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1600 } });
  await ctx.addCookies(ids.admin_cookies);
  const page = await ctx.newPage();
  page.setDefaultTimeout(120000);
  page.on('pageerror', e => errors.push(`${page.url()}: ${e && (e.stack || e.message)}`));

  st('clear-location');
  await check('Overview tab: every server row is shown with a value', async () => {
    const r = await page.goto(`${PAGE}&tab=webserver`, { waitUntil: 'load' });
    const html = await page.content();
    const rows = ['Server OS', 'Server Software', 'Server IP', 'Server Port', 'Server Location', 'Server Hostname', "Site's Document Root"];
    const missing = [];
    for (const label of rows) {
      const cell = page.locator('td.e', { hasText: label }).first();
      if (!(await cell.count())) { missing.push(label + ' (row)'); continue; }
      const value = (await cell.locator('xpath=following-sibling::td[1]').innerText()).trim();
      if (!value) missing.push(label + ' (empty)');
    }
    return [r.status() === 200 && !phpError(html) && missing.length === 0, `status ${r.status()}, missing ${JSON.stringify(missing)}`];
  });
  await check('Overview tab: server location lookup returns an answer (no crash, nothing unserialized)', async () => {
    const value = (await page.locator('td.e', { hasText: 'Server Location' }).locator('xpath=following-sibling::td[1]').innerText()).trim();
    // A local machine resolves to a private or reserved address, which the API
    // reports as a failure; anything readable is fine, a PHP error is not.
    return [value.length > 0 && !/Fatal|Warning|Notice|O:\d+:/.test(value), `value: ${value}`];
  });
  await check('PHP Information tab: shows the PHP version and loaded modules', async () => {
    const r = await page.goto(`${PAGE}&tab=phpinfo`, { waitUntil: 'load' });
    const html = await page.content();
    // The web server's PHP, not WP-CLI's: MAMP runs its own, so the version is
    // matched by shape rather than compared with the CLI's.
    const version = /PHP Version\s*<\/td>\s*<td[^>]*>\s*\d+\.\d+\.\d+|PHP Version \d+\.\d+\.\d+/i.test(html);
    return [r.status() === 200 && !phpError(html) && version && /mysqli|pdo_mysql/i.test(html), `status ${r.status()}, PHP version shown: ${version}`];
  });
  await check('PHP Information tab: no cookie value is printed, the login cookie included', async () => {
    const secret = 'wsiSecret' + Date.now();
    await ctx.addCookies([{ name: 'zz_wsi_secret', value: secret, domain: 'localhost', path: '/' }]);
    const r = await page.goto(`${PAGE}&tab=phpinfo`, { waitUntil: 'load' });
    const html = await page.content();
    const leaked = [secret, ...ids.admin_cookies.map(c => c.value)].filter(v => html.includes(v) || html.includes(encodeURIComponent(v)));
    const rows = (html.match(/>\s*(HTTP_COOKIE|\$_SERVER\['HTTP_COOKIE'\]|\$_COOKIE\[[^<]*)\s*</g) || []);
    await ctx.clearCookies({ name: 'zz_wsi_secret' });
    return [r.status() === 200 && /PHP Version/.test(html) && leaked.length === 0 && rows.length === 0, `status ${r.status()}, leaked ${leaked.length}, cookie rows ${JSON.stringify(rows).slice(0, 200)}`];
  });
  await check('Database Information tab: shows the database version and settings', async () => {
    const r = await page.goto(`${PAGE}&tab=dbinfo`, { waitUntil: 'load' });
    const html = await page.content();
    const v = (ids.mysql_version || '').split('-')[0];
    return [r.status() === 200 && !phpError(html) && html.includes('Database Version') && html.includes(v) && html.includes('Maximum No. of Connections'), `status ${r.status()}, has version ${v}: ${html.includes(v)}`];
  });
  await check('visits to the plugin pages are counted for the review prompt', async () => {
    const n = parseInt(st('opt', 'wpheka_web_server_info_view_count'), 10);
    return [n >= 3, `count ${n}`];
  });

  // Footer setting, on and off, through the Overview tab's form.
  const footer = async () => { await page.goto(`${A}index.php`, { waitUntil: 'load' }); return (await page.locator('#footer-upgrade').innerText().catch(() => '')).trim(); };
  const setFooter = async on => {
    await page.goto(`${PAGE}&tab=webserver`, { waitUntil: 'load' });
    await page.waitForSelector('#wpheka_wsi_footer_info', { state: 'attached' });
    // Set and submit in the page: a notice above the form can cover the box.
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }),
      page.evaluate(want => { const box = document.getElementById('wpheka_wsi_footer_info'); box.checked = want; box.form.requestSubmit(); }, on),
    ]);
    return page.url();
  };
  await check('footer setting off: the admin footer shows the normal WordPress text', async () => {
    const url = await setFooter(false);
    const text = await footer();
    return [url.includes('wpheka-updated=1') && !text.includes('You are running WordPress'), `after save ${url}, footer "${text}"`];
  });
  await check('footer setting on: the admin footer shows WordPress, PHP, server and MySQL versions', async () => {
    await setFooter(true);
    const text = await footer();
    // The web server's PHP is shown, which is not WP-CLI's under MAMP.
    return [/You are running WordPress \S+ \| PHP \d+\.\d+/.test(text) && text.includes('MySQL'), `footer "${text}"`];
  });

  // Review prompt.
  st('user-clear', 'wpheka_wsi_review_snoozed_until'); st('user-clear', 'wpheka_wsi_review_dismissed');
  await check('review prompt: shows on the Dashboard after three visits; X keeps it closed for 14 days', async () => {
    await page.goto(`${A}index.php`, { waitUntil: 'load' });
    const shown = await page.locator('#wpheka-wsi-review-notice').count();
    const resp = page.waitForResponse(r => r.url().includes('admin-ajax.php') && (r.request().postData() || '').includes('wpheka_wsi_snooze_review'));
    resp.catch(() => {}); // a failed click must not leave this wait to crash the run
    if (!shown) return [false, 'prompt not shown on the Dashboard'];
    // The Dashboard's widgets keep moving the layout while they load, so a
    // pointer click never finds the X "stable"; a DOM click runs the same handler.
    await page.evaluate(() => document.querySelector('#wpheka-wsi-review-notice .notice-dismiss').click());
    const r = await resp;
    await page.goto(`${A}index.php`, { waitUntil: 'load' });
    const days = (parseInt(st('user-get', 'wpheka_wsi_review_snoozed_until'), 10) - Date.now() / 1000) / 86400;
    return [shown === 1 && r.status() === 200 && (await page.locator('#wpheka-wsi-review-notice').count()) === 0 && Math.abs(days - 14) < 0.05, `shown ${shown}, ajax ${r.status()}, snooze ${days.toFixed(2)}d`];
  });
  st('user-clear', 'wpheka_wsi_review_snoozed_until');
  await check('review prompt: "Don\'t ask again" hides it for good', async () => {
    await page.goto(`${A}index.php`, { waitUntil: 'load' });
    const href = await page.locator('#wpheka-wsi-review-notice a', { hasText: "Don't ask again" }).getAttribute('href');
    await page.goto(href, { waitUntil: 'load' });
    await page.goto(`${A}index.php`, { waitUntil: 'load' });
    return [(await page.locator('#wpheka-wsi-review-notice').count()) === 0 && String(st('user-get', 'wpheka_wsi_review_dismissed')) === '1', 'still shown'];
  });

  // A subscriber gets nothing.
  const sctx = await browser.newContext(); await sctx.addCookies(ids.sub_cookies);
  const sp = await sctx.newPage(); sp.setDefaultTimeout(120000);
  await check('security: a subscriber cannot open the information pages', async () => {
    const r = await sp.goto(`${PAGE}&tab=phpinfo`, { waitUntil: 'load' });
    const html = await sp.content();
    return [!html.includes('PHP Version') && !html.includes(ids.php_version + '</'), `status ${r.status()}`];
  });
  // An author reaches wp-admin (WooCommerce sends subscribers to My Account instead)
  // but cannot open the information page, so must not see the server versions.
  await check("security: an author sees WordPress' own admin footer, not the server versions", async () => {
    const actx = await browser.newContext(); await actx.addCookies(ids.author_cookies);
    const ap = await actx.newPage(); ap.setDefaultTimeout(120000);
    const r = await ap.goto(`${A}profile.php`, { waitUntil: 'load' });
    // A missing footer must fail, not read as an empty, "clean" footer.
    const found = await ap.locator('#footer-upgrade').count();
    const text = found ? (await ap.locator('#footer-upgrade').textContent()).trim() : '';
    await actx.close();
    return [r.status() === 200 && found === 1 && !text.includes('You are running WordPress') && !text.includes('MySQL'), `status ${r.status()}, footer element ${found}, text "${text}"`];
  });
  await check('security: a subscriber cannot change the footer setting', async () => {
    const before = JSON.stringify(st('opt', 'wpheka_web_server_info_settings'));
    const r = await sp.request.post(`${A}admin.php?page=wpheka-information`, { form: { wpheka_wsi_nonce: 'bogus', wpheka_wsi_footer_info: '' } });
    const after = JSON.stringify(st('opt', 'wpheka_web_server_info_settings'));
    return [before === after, `status ${r.status()}, ${before} -> ${after}`];
  });
  await sctx.close();

  const real = errors.filter(e => !/Transition was skipped/.test(e));
  record(real.length === 0, 'no JavaScript errors on the plugin screens', JSON.stringify(real).slice(0, 400));
  await browser.close();
  record(true, 'admin.js: ran to the end');
})();
