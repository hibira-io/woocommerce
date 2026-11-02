const { chromium } = require('playwright');
// Run: WP_DIR=/path/to/wordpress node tests/e2e/live-visitors.cjs [screenshot dir]
// Needs WordPress + WooCommerce on 127.0.0.1:8090 with wp-cli.phar in WP_DIR, a product with ID 10
// and slug hibira-mug, and this plugin pointed at a Hibira server with subscription counting on.
const { execSync } = require('child_process');
const WP = process.env.WP_DIR;
const URL = 'http://127.0.0.1:8090/?product=hibira-mug';
const OUT = process.argv[2] || '.';
const wp = (php) => execSync(`php wp-cli.phar --allow-root eval '${php}'`, { cwd: WP }).toString();
(async () => {
  const browser = await chromium.launch();
  const pages = [];
  for (let i = 0; i < 3; i++) {
    const p = await (await browser.newContext({ viewport: { width: 1100, height: 800 } })).newPage();
    p.on('pageerror', (e) => console.log('pageerror', e.message));
    await p.goto(URL);
    pages.push(p);
  }
  const a = pages[0];
  await a.waitForFunction(() => /3 people are viewing/.test(document.querySelector('[data-hibira-viewers]')?.textContent || '') && !document.querySelector('[data-hibira-viewers]').hidden, null, { timeout: 20000 });
  console.log('badge:', (await a.textContent('[data-hibira-viewers]')).trim());
  await pages[2].close();
  await a.waitForFunction(() => /2 people/.test(document.querySelector('[data-hibira-viewers]')?.textContent || ''), null, { timeout: 20000 });
  console.log('after one leaves:', (await a.textContent('[data-hibira-viewers]')).trim());
  wp('wc_update_product_stock(10, 1, "set");');
  await a.waitForFunction(() => /1 in stock/.test(document.querySelector('p.stock')?.textContent || ''), null, { timeout: 20000 });
  console.log('stock:', (await a.textContent('p.stock')).trim());
  wp('$o = wc_create_order(); $o->add_product(wc_get_product(10), 1); $o->calculate_totals(); $o->update_status("processing"); echo $o->get_id();');
  await a.waitForSelector('.hibira-lv-toast', { timeout: 20000 });
  console.log('popup:', (await a.textContent('.hibira-lv-toast')).replace(/\s+/g, ' ').trim());
  await a.waitForFunction(() => /Out of stock/i.test(document.querySelector('p.stock')?.textContent || ''), null, { timeout: 20000 });
  console.log('stock after order:', (await a.textContent('p.stock')).trim(), 'button disabled:', await a.$eval('form.cart button[type=submit]', (b) => b.disabled).catch(() => 'no form'));
  await a.screenshot({ path: OUT + '/woo-product.png' });
  // A later visitor sees the recent purchase popup (on another page).
  const late = await (await browser.newContext()).newPage();
  await late.goto('http://127.0.0.1:8090/?post_type=product');
  await late.waitForSelector('.hibira-lv-toast', { timeout: 20000 });
  console.log('late visitor popup:', (await late.textContent('.hibira-lv-toast')).replace(/\s+/g, ' ').trim());
  await browser.close();
})().catch((e) => { console.error('FAIL', e.message); process.exit(1); });
