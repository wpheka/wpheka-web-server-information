// Web Server Information as an administrator uses it: the four tabs of the 1.9.0
// page and what each must show (stats, summaries, search, Refresh, Help), the server location lookup, the admin-footer setting, the
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

  // dt/dd rows of the kit's key/value lists, by label.
  const kv = async (scope, label) => { const dt = page.locator(`${scope} .w-kv dt`, { hasText: new RegExp(`^${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }).first(); return (await dt.count()) ? (await dt.locator('xpath=following-sibling::dd[1]').innerText()).trim() : null; };

  st('clear-location');
  await check('Overview tab: every server row is shown with a value', async () => {
    const r = await page.goto(`${PAGE}&tab=webserver`, { waitUntil: 'load' });
    const html = await page.content();
    const rows = ['Server OS', 'Server software', 'Server IP', 'Server port', 'Server location', 'Hostname', 'Document root'];
    const missing = [];
    for (const label of rows) {
      const value = await kv('#server', label);
      if (value === null) missing.push(label + ' (row)'); else if (!value) missing.push(label + ' (empty)');
    }
    return [r.status() === 200 && !phpError(html) && missing.length === 0 && !html.includes('ERROR IP096T'), `status ${r.status()}, missing ${JSON.stringify(missing)}`];
  });
  await check('Overview tab: server location lookup returns an answer (no crash, nothing unserialized)', async () => {
    const value = await kv('#server', 'Server location');
    // A local machine resolves to a private or reserved address, which the API
    // reports as a failure; anything readable is fine, a PHP error is not.
    return [!!value && !/Fatal|Warning|Notice|O:\d+:/.test(value), `value: ${value}`];
  });
  await check('Overview tab: four stats cards with real values (PHP, database, web server, memory limit)', async () => {
    const labels = (await page.locator('.w-stat-label').allInnerTexts()).map(t => t.trim().toUpperCase());
    const values = (await page.locator('.w-stat-value').allInnerTexts()).map(t => t.trim());
    const v = (ids.mysql_version || '').split('-')[0];
    const ok = JSON.stringify(labels) === JSON.stringify(['PHP', 'DATABASE', 'WEB SERVER', 'MEMORY LIMIT'])
      && /^\d+\.\d+\.\d+/.test(values[0]) && values[1].includes(v) && values[2].length > 0 && /^(-1|\d+[KMG]?)$/i.test(values[3]);
    return [ok, `labels ${JSON.stringify(labels)}, values ${JSON.stringify(values)}`];
  });
  await check('Overview tab: PHP and Database summaries, and "64-bit PHP" (not "64Bit OS")', async () => {
    const html = await page.content();
    const php = await kv('#php-summary', 'PHP version'), build = await kv('#php-summary', 'PHP build'), mem = await kv('#php-summary', 'Memory limit');
    const dbv = await kv('#db-summary', 'Database version');
    return [/^\d+\.\d+/.test(php || '') && /^(32|64)-bit PHP$/.test(build || '') && !!mem && (dbv || '').length > 0 && !/Bit OS/.test(html), `php ${php}, build ${build}, mem ${mem}, db ${dbv}`];
  });
  await check('Overview tab: the left nav links to the four sections', async () => {
    const hrefs = await page.locator('.w-nav-link').evaluateAll(a => a.map(x => x.getAttribute('href')));
    const found = await page.evaluate(h => h.map(x => !!document.querySelector(x)), hrefs);
    return [JSON.stringify(hrefs) === JSON.stringify(['#server', '#php-summary', '#db-summary', '#settings']) && found.every(Boolean), JSON.stringify(hrefs)];
  });
  await check('menu: "Server Info" under WPHEKA, the page header says "Web Server Information"', async () => {
    const item = page.locator('#adminmenu a[href$="page=wpheka-information"]');
    const label = (await item.innerText()).trim();
    const h1 = (await page.locator('.w-hero h1').innerText()).trim();
    return [label === 'Server Info' && h1.startsWith('Web Server Information'), `menu "${label}", header "${h1}"`];
  });
  await check('PHP tab: shows the PHP version and loaded modules, grouped by module', async () => {
    const r = await page.goto(`${PAGE}&tab=phpinfo`, { waitUntil: 'load' });
    const html = await page.content();
    // The web server's PHP, not WP-CLI's: MAMP runs its own, so the version is
    // matched by shape rather than compared with the CLI's.
    const version = await kv('#wsi-php', 'PHP version');
    const titles = await page.locator('#wsi-php .w-section h2').allInnerTexts();
    return [r.status() === 200 && !phpError(html) && /^\d+\.\d+\.\d+/.test(version || '') && titles.includes('Core') && titles.some(t => /mysqli|pdo_mysql/i.test(t)), `status ${r.status()}, version ${version}, ${titles.length} groups`];
  });
  await check('PHP tab: no Environment, PHP Variables or Apache request sections', async () => {
    const titles = await page.locator('#wsi-php .w-section h2').allInnerTexts();
    const bad = titles.filter(t => /^(Environment|PHP Variables|Apache Environment|HTTP Headers Information)$/i.test(t.trim()));
    return [bad.length === 0, JSON.stringify(bad)];
  });
  await check('PHP tab: the search box narrows the rows, and says when nothing matches', async () => {
    const box = page.locator('[data-w-kvfind="wsi-php"]');
    await box.fill('memory_limit');
    const shown = await page.locator('#wsi-php .w-kv > div:not([hidden])').evaluateAll(r => r.map(x => x.querySelector('dt').textContent));
    const sections = await page.locator('#wsi-php .w-section:not([hidden])').count();
    await box.fill('zz-no-such-setting');
    const empty = await page.locator('#wsi-php [data-w-kv-empty]').isVisible();
    await box.fill('');
    const all = await page.locator('#wsi-php .w-kv > div:not([hidden])').count();
    return [shown.includes('memory_limit') && shown.length < 5 && sections >= 1 && empty && all > 100, `shown ${JSON.stringify(shown)}, sections ${sections}, empty ${empty}, all ${all}`];
  });
  await check('PHP tab: no cookie value is printed, the login cookie included', async () => {
    const secret = 'wsiSecret' + Date.now();
    await ctx.addCookies([{ name: 'zz_wsi_secret', value: secret, domain: 'localhost', path: '/' }]);
    const r = await page.goto(`${PAGE}&tab=phpinfo`, { waitUntil: 'load' });
    const html = await page.content();
    const leaked = [secret, ...ids.admin_cookies.map(c => c.value)].filter(v => html.includes(v) || html.includes(encodeURIComponent(v)));
    const rows = (html.match(/>\s*(HTTP_COOKIE|\$_SERVER\['HTTP_COOKIE'\]|\$_COOKIE\[[^<]*)\s*</g) || []);
    await ctx.clearCookies({ name: 'zz_wsi_secret' });
    return [r.status() === 200 && /PHP version/.test(html) && leaked.length === 0 && rows.length === 0, `status ${r.status()}, leaked ${leaked.length}, cookie rows ${JSON.stringify(rows).slice(0, 200)}`];
  });
  await check('Database tab: shows the database version and settings', async () => {
    const r = await page.goto(`${PAGE}&tab=dbinfo`, { waitUntil: 'load' });
    const html = await page.content();
    const v = (ids.mysql_version || '').split('-')[0];
    const version = await kv('#database', 'Database version'), conns = await kv('#database', 'Max connections');
    const vars = await page.locator('#wsi-db-vars .w-kv > div').count();
    return [r.status() === 200 && !phpError(html) && (version || '').length > 0 && v.startsWith(version) && /^\d/.test(conns || '') && vars > 50, `status ${r.status()}, version ${version} (db ${v}), connections ${conns}, variables ${vars}`];
  });
  await check('Database tab: the search box narrows the variables', async () => {
    const box = page.locator('[data-w-kvfind="wsi-db-vars"]');
    await box.fill('max_allowed_packet');
    const shown = await page.locator('#wsi-db-vars .w-kv > div:not([hidden]) dt').allInnerTexts();
    await box.fill('');
    return [shown.length >= 1 && shown.every(t => t.includes('max_allowed_packet')), JSON.stringify(shown)];
  });
  await check('Database tab: Refresh reads the cached values again and says so', async () => {
    st('set-transient', 'wpheka_web_server_db_max_connection', 'zz-stale');
    // Its own tab: see setFooter() below on headless Chromium after a POST.
    const rp = await ctx.newPage();
    rp.setDefaultTimeout(120000);
    await rp.goto(`${PAGE}&tab=dbinfo`, { waitUntil: 'load' });
    const val = async () => { const dt = rp.locator('#database .w-kv dt', { hasText: /^Max connections$/ }); return (await dt.locator('xpath=following-sibling::dd[1]').innerText()).trim(); };
    const before = await val();
    await Promise.all([rp.waitForNavigation({ waitUntil: 'load' }), rp.locator('#db-variables button[type=submit]', { hasText: 'Refresh' }).click()]);
    const after = await val();
    const notice = (await rp.locator('.w-notice--success[role=status]').innerText().catch(() => '')).trim();
    await rp.close();
    return [before === 'zz-stale' && /^\d/.test(after || '') && /read again/.test(notice), `before ${before}, after ${after}, notice "${notice}"`];
  });
  await check('other plugins\' notices stay off the plugin page, and show on the Dashboard', async () => {
    await page.goto(`${A}index.php`, { waitUntil: 'load' });
    const elsewhere = await page.locator('.zz-wsi-foreign').count();
    let here = 0, stray = false;
    for (const tab of ['webserver', 'phpinfo', 'dbinfo', 'help']) {
      await page.goto(`${PAGE}&tab=${tab}`, { waitUntil: 'load' });
      here += await page.locator('.zz-wsi-foreign').count();
      stray = stray || await page.locator('.zz-wsi-stray').isVisible();
    }
    return [elsewhere === 3 && here === 0 && !stray, `dashboard ${elsewhere} (want 3), plugin page ${here}, stray visible ${stray}`];
  });
  await check('Help tab: Documentation, Support forum and What\'s new, each opening in a new tab', async () => {
    const r = await page.goto(`${PAGE}&tab=help`, { waitUntil: 'load' });
    const cards = await page.locator('.w-flow a.w-flow-step').evaluateAll(a => a.map(x => [x.querySelector('h3').textContent, x.getAttribute('href'), x.getAttribute('target')]));
    const ok = cards.length === 3 && cards.every(c => c[2] === '_blank') && cards[0][1].startsWith('https://www.wpheka.com/') && cards[1][1].includes('/support/plugin/wpheka-web-server-information/');
    return [r.status() === 200 && ok, JSON.stringify(cards)];
  });
  await check('header: every review link is the same reviews form (no rate or filter)', async () => {
    const want = 'https://wordpress.org/support/plugin/wpheka-web-server-information/reviews/#new-post';
    await page.goto(`${PAGE}&tab=webserver`, { waitUntil: 'load' });
    const links = await page.locator('a[href*="/reviews"]').evaluateAll(a => a.map(x => x.getAttribute('href')));
    return [links.length >= 3 && links.every(h => h === want), JSON.stringify([...new Set(links)])];
  });
  await check('visits to the plugin pages are counted for the review prompt', async () => {
    const n = parseInt(st('opt', 'wpheka_web_server_info_view_count'), 10);
    return [n >= 3, `count ${n}`];
  });

  // Footer setting, on and off, through the Overview tab's form.
  const footer = async () => { await page.goto(`${A}index.php`, { waitUntil: 'load' }); return (await page.locator('#footer-upgrade').innerText().catch(() => '')).trim(); };
  const SWITCH = 'input[role="switch"][name="wpheka_wsi_footer_info"]';
  // Each save gets a fresh tab: after any POST and redirect (WordPress core's own
  // Settings save included) headless Chromium stops advancing CSS transitions in
  // that tab, so the save bar would never slide in. Real browsers are unaffected.
  const setFooter = async on => {
    const fp = await ctx.newPage();
    fp.setDefaultTimeout(120000);
    fp.on('pageerror', e => errors.push(`${fp.url()}: ${e && (e.stack || e.message)}`));
    await fp.goto(`${PAGE}&tab=webserver`, { waitUntil: 'load' });
    await fp.waitForSelector(SWITCH, { state: 'attached' });
    // Set it, then save with the kit's save bar, as a user does.
    await fp.locator(SWITCH).evaluate((box, want) => { if (box.checked !== want) box.click(); }, on);
    const saved = fp.waitForResponse(r => r.url().includes('admin-post.php') && r.request().method() === 'POST');
    await Promise.all([fp.waitForNavigation({ waitUntil: 'load' }), fp.locator('.w-savebar button[type=submit]').click()]);
    // The kit removes w-saved from the address bar once the toast shows, so read the redirect.
    const location = (await saved).headers().location || '';
    await fp.close();
    return location;
  };
  await check('footer setting off: the admin footer shows the normal WordPress text', async () => {
    const url = await setFooter(false);
    const text = await footer();
    return [url.includes('w-saved=1') && !text.includes('You are running WordPress'), `after save ${url}, footer "${text}"`];
  });
  await check('footer setting: the page says "Settings saved" and the switch shows the stored value', async () => {
    await page.goto(`${PAGE}&tab=webserver&w-saved=1`, { waitUntil: 'load' });
    const toast = await page.locator('.w-toast.is-shown[role=status]', { hasText: 'Settings saved' }).count();
    const shown = await page.locator(SWITCH).isChecked();
    const stored = st('opt', 'wpheka_web_server_info_settings');
    return [toast === 1 && shown === false && stored && !stored.footer_info, `toast ${toast}, switch ${shown}, stored ${JSON.stringify(stored)}`];
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
    return [!/PHP version/i.test(html) && !html.includes(ids.php_version + '</'), `status ${r.status()}`];
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
    const r = await sp.request.post(`${A}admin-post.php`, { form: { action: 'wpheka_wsi_save', _wpnonce: 'bogus', wpheka_wsi_footer_info: 'no' } });
    const after = JSON.stringify(st('opt', 'wpheka_web_server_info_settings'));
    return [before === after, `status ${r.status()}, ${before} -> ${after}`];
  });
  await sctx.close();

  const real = errors.filter(e => !/Transition was skipped/.test(e));
  record(real.length === 0, 'no JavaScript errors on the plugin screens', JSON.stringify(real).slice(0, 400));
  await browser.close();
  record(true, 'admin.js: ran to the end');
})();
