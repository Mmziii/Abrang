const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
(async () => {
 const browser = await chromium.launch();
 fs.mkdirSync('screenshots', {recursive:true});
 for (const width of [390,1440,360]) {
  const page = await browser.newPage({viewport:{width,height:900}});
  const response = await page.goto('http://localhost:8080/', {waitUntil:'networkidle'});
  assert.equal(response.status(),200);
  await page.evaluate(() => document.fonts.ready);
  if (width === 390 || width === 1440) {
   const fonts = await page.evaluate(() => {
    const firstFamily = el => getComputedStyle(el).fontFamily.split(',')[0].replace(/["']/g, '').trim();
    return {
     body: firstFamily(document.body),
     heading: firstFamily(document.querySelector('.abrang-hero h1')),
     button: firstFamily(document.querySelector('.abrang-hero .wp-element-button')),
     loaded: document.fonts.check('16px Vazirmatn'),
    };
   });
   for (const key of ['body','heading','button']) assert.equal(fonts[key], 'Vazirmatn', `${key} font at ${width}`);
   assert.ok(fonts.loaded, `Vazirmatn loaded at ${width}`);
  }
  await page.getByRole('heading',{name:'ایده‌هایت را به رنگ زندگی بنویس'}).waitFor();
  assert.equal(await page.locator('.abrang-tiles a').count(),5);
  assert.ok(await page.locator('.wp-block-woocommerce-product-search').count());
  assert.ok(await page.locator('.wp-block-woocommerce-mini-cart').count());
  assert.ok(await page.locator('.abrang-products .wc-block-product-template > li').count() >= 8);
  const prices = await page.locator('.abrang-products .wp-block-woocommerce-product-price').allTextContents();
  assert.ok(prices.length > 0);
  for (const price of prices) {
   assert.match(price, /[۰-۹]/, 'Persian price digits');
   assert.doesNotMatch(price, /[0-9]/, 'No Latin price digits');
   assert.ok(price.includes('٬'), 'Persian thousands separator');
  }
  // Prove enhancement ignores values and attributes even inside a price container.
  const numericInput = await page.evaluate(async () => {
   const box = document.createElement('div');
   box.className = 'wc-block-components-product-price';
   box.innerHTML = '<input type="number" value="12"><span data-value="123">123,000</span>';
   document.body.append(box);
   await new Promise(resolve => setTimeout(resolve, 100));
   const result = {value:box.querySelector('input').value, attribute:box.querySelector('span').dataset.value, text:box.querySelector('span').textContent};
   box.remove();
   return result;
  });
  assert.deepEqual(numericInput, {value:'12', attribute:'123', text:'۱۲۳٬۰۰۰'});
  const cardImages = page.locator('.abrang-products .abrang-product-image img');
  assert.ok(await cardImages.count() > 0, 'Product image wrappers rendered');
  for (const image of await cardImages.all()) {
   const box = await image.boundingBox();
   assert.ok(Math.abs(box.width-box.height) < 2, 'Square product image');
  }
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Overflow at ${width}`);
  const searchInput = page.locator('.wp-block-woocommerce-product-search input[type="search"], .wp-block-woocommerce-product-search input[name="s"]').first();
  assert.equal(await searchInput.getAttribute('placeholder'), 'جستجوی محصولات…');
  if (width < 782) {
   const boxes = await Promise.all(['.wp-block-navigation__responsive-container-open','.abrang-wordmark','.wp-block-woocommerce-customer-account','.wp-block-woocommerce-mini-cart'].map(s => page.locator(s).first().boundingBox()));
   assert.ok(boxes.every(b => b && Math.abs((b.y+b.height/2)-(boxes[0].y+boxes[0].height/2)) < 20), 'One mobile header row');
   assert.ok((await searchInput.boundingBox()).y > boxes[0].y + boxes[0].height, 'Search below top row');
  }
  for (const button of await page.locator('.abrang-products .wp-block-button__link').all()) {
   assert.ok(await button.evaluate(el => el.scrollWidth <= el.clientWidth + 1), 'Card button never overflows');
  }
  if(width !== 360) await page.screenshot({path:`screenshots/home-${width}.png`,fullPage:true});
  if(width === 390) {
   await page.locator('.wp-block-navigation__responsive-container-open').click();
   await page.locator('.wp-block-navigation__responsive-container.is-menu-open').waitFor();
   const overlay = page.locator('.wp-block-navigation__responsive-container.is-menu-open');
   assert.equal(await overlay.evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(251, 248, 243)');
   assert.ok(await page.locator('main').evaluate(el => el.inert), 'Hero is inert behind menu');
   assert.equal(await page.locator('html').evaluate(el => getComputedStyle(el).overflow), 'hidden');
   const heroObscured = await page.locator('.abrang-hero h1').evaluate(el => {
    const r = el.getBoundingClientRect();
    const y = Math.max(1, Math.min(innerHeight - 1, r.top + 5));
    return !!document.elementFromPoint(innerWidth / 2, y)?.closest('.is-menu-open');
   });
   assert.ok(heroObscured, 'Opaque overlay covers hero hit target');
   const submenu = overlay.locator('.wp-block-navigation__submenu-container').first();
   assert.equal(await submenu.isVisible(), false);
   const toggle = overlay.locator('.has-child > button').first();
   await toggle.click();
   assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
   assert.equal(await submenu.isVisible(), true);
   await toggle.click();
   assert.equal(await submenu.isVisible(), false);
   await page.screenshot({path:'screenshots/mobile-menu-open.png',fullPage:false});
   await page.keyboard.press('Escape');
  }
  await page.close();
 }
 await browser.close();
})().catch(e => {console.error(`::error::${String(e.stack || e).replace(/\n/g, '%0A')}`); process.exit(1)});
