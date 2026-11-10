const { chromium } = require('playwright');
// Retakes the WordPress.org screenshots in .wordpress-org/ from a real store.
// Run: WP_DIR=/path/to/wordpress node tests/e2e/wporg-screenshots.cjs <out dir>
// Needs WordPress + WooCommerce on 127.0.0.1:8090 (pretty permalinks, the WooCommerce sample
// products, Sunglasses = ID 17 with stock managed, a page /live/ with [hibira_viewers scope="store" min="1"]
// and [products]) and this plugin pointed at a local Hibira server (127.0.0.1:6001, app lv/lvkey)
// with subscription counting on. The PNGs are 2x; resize to 1600 px wide before committing.
const { execSync } = require('child_process');
const WP = process.env.WP_DIR, OUT = process.argv[2], B = 'http://127.0.0.1:8090';
const wp = (php) => execSync(`php wp-cli.phar --allow-root eval '${php}'`, { cwd: WP }).toString();
const vp = { width: 1280, height: 860 };
(async () => {
  const browser = await chromium.launch();
  const ctx = () => browser.newContext({ viewport: vp, deviceScaleFactor: 2 });
  wp('$p=wc_get_product(17); $p->set_stock_quantity(5); $p->save(); delete_option("hibira_lv_recent");');
  const pages = [];
  for (let i = 0; i < 4; i++) { const p = await (await ctx()).newPage(); await p.goto(B + '/product/sunglasses/'); pages.push(p); }
  const a = pages[0];
  await a.waitForFunction(() => /4 people/.test(document.querySelector('[data-hibira-viewers]')?.textContent || ''), null, { timeout: 20000 });
  wp("wc_update_product_stock(17, 3, \"set\");");
  await a.waitForFunction(() => /3 in stock/.test(document.querySelector('p.stock')?.textContent || ''), null, { timeout: 20000 });
  wp('$o = wc_create_order(); $o->add_product(wc_get_product(17), 1); $o->calculate_totals(); $o->update_status("processing");');
  await a.waitForSelector('.hibira-lv-toast-wrap.hibira-lv-in', { timeout: 20000 });
  await a.waitForTimeout(600);
  await a.screenshot({ path: OUT + '/1-product.png' });
  // shop page: later visitor sees recent order popup + store-wide count via shortcode page
  const shop = await (await ctx()).newPage();
  await shop.goto(B + '/live/');
  await shop.waitForFunction(() => [...document.querySelectorAll('[data-hibira-viewers]')].some(e => !e.hidden && /\d/.test(e.textContent)), null, { timeout: 20000 });
  await shop.waitForSelector('.hibira-lv-toast-wrap.hibira-lv-in', { timeout: 30000 });
  await shop.waitForTimeout(600);
  await shop.screenshot({ path: OUT + '/2-store.png' });
  // sold out
  wp("wc_update_product_stock(17, 0, \"set\");");
  await a.waitForFunction(() => /Out of stock/i.test(document.querySelector('p.stock')?.textContent || ''), null, { timeout: 20000 });
  await a.waitForTimeout(3000);
  await a.screenshot({ path: OUT + '/3-soldout.png' });
  // mobile
  const m = await (await browser.newContext({ ...require('playwright').devices['iPhone 13'] })).newPage();
  wp('$p=wc_get_product(17); $p->set_stock_quantity(2); $p->set_stock_status("instock"); $p->save();');
  await m.goto(B + '/product/sunglasses/');
  await m.waitForFunction(() => { const e = document.querySelector('[data-hibira-viewers]'); return e && !e.hidden && /people/.test(e.textContent); }, null, { timeout: 20000 });
  await m.evaluate(() => document.querySelector('[data-hibira-viewers]').scrollIntoView({ block: 'center' }));
  await m.waitForTimeout(500);
  await m.screenshot({ path: OUT + '/4-mobile.png' });
  // settings
  const ad = await (await ctx()).newPage();
  await ad.goto(B + '/wp-login.php');
  await ad.fill('#user_login', 'admin'); await ad.fill('#user_pass', 'admin'); await ad.click('#wp-submit');
  await ad.waitForLoadState('networkidle');
  wp('update_option("hibira_lv_app_id","2048173"); update_option("hibira_lv_key","7c41e0b9d2a35f86c1e4"); update_option("hibira_lv_host","ws.hibira.io");');
  await ad.goto(B + '/wp-admin/admin.php?page=wc-settings&tab=hibira');
  await ad.addStyleTag({ content: '#wp-admin-bar-sqlite-db-integration{display:none!important}' });
  await ad.evaluate(() => document.querySelectorAll('code').forEach(c => { if (c.textContent.includes('127.0.0.1')) c.textContent = 'https://northwind.example'; }));
  await ad.waitForTimeout(1500);
  await ad.screenshot({ path: OUT + '/5-settings.png', fullPage: true });
  wp('update_option("hibira_lv_app_id","lv"); update_option("hibira_lv_key","lvkey"); update_option("hibira_lv_host","127.0.0.1:6001");');
  await browser.close();
})().catch((e) => { console.error('FAIL', e.message); process.exit(1); });
