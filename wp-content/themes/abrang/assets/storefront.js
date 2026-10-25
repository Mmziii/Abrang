/* Small progressive enhancements; native WordPress owns focus trap and Escape. */
(() => {
 const mobile = matchMedia('(max-width: 781px)');
 const nav = document.querySelector('.abrang-header nav');
 if (!nav) return;
 const overlay = nav.querySelector('.wp-block-navigation__responsive-container');
 const parents = [...nav.querySelectorAll('.has-child')];
 parents.forEach((item, index) => {
  const toggle = item.querySelector(':scope > button');
  const submenu = item.querySelector(':scope > .wp-block-navigation__submenu-container');
  if (!toggle || !submenu) return;
  submenu.id = `abrang-submenu-${index}`;
  toggle.setAttribute('aria-controls', submenu.id);
  toggle.setAttribute('aria-expanded', 'false');
  toggle.setAttribute('aria-label', `باز کردن زیرمجموعه ${item.querySelector('.wp-block-navigation-item__label')?.textContent || ''}`);
 });
 nav.addEventListener('click', event => {
  if (!mobile.matches) return;
  const trigger = event.target.closest('button, a');
  const parent = trigger?.closest('.has-child');
  if (!parent || !parents.includes(parent) || trigger.parentElement !== parent) return;
  const toggle = parent.querySelector(':scope > button');
  if (!toggle) return;
  event.preventDefault();
  event.stopImmediatePropagation();
  const expanded = parent.dataset.abrangExpanded !== 'true';
  parent.dataset.abrangExpanded = String(expanded);
  toggle.setAttribute('aria-expanded', String(expanded));
 }, true);
 const syncMenu = () => {
  const open = overlay?.classList.contains('is-menu-open') && mobile.matches;
  document.documentElement.classList.toggle('abrang-menu-open', !!open);
  document.querySelectorAll('main, footer').forEach(el => { el.inert = !!open; });
 };
 if (overlay) new MutationObserver(syncMenu).observe(overlay, {attributes:true, attributeFilter:['class']});
 mobile.addEventListener('change', syncMenu);
 syncMenu();
})();

/* Preserve the search label for assistive technology, use an explicit magnifier. */
document.querySelectorAll('.wp-block-woocommerce-product-search').forEach(search => {
 const input = search.querySelector('input[type="search"], input[name="s"]');
 if (input) input.placeholder = 'جستجوی محصولات…';
 search.querySelectorAll('label').forEach(label => label.classList.add('screen-reader-text'));
 search.querySelectorAll('button[type="submit"], button.wp-block-search__button').forEach(button => {
  button.setAttribute('aria-label', 'جستجوی محصولات');
  button.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>';
 });
});

/* Woo blocks can re-render prices/counts client-side. Only replace text nodes in
 * display-only price, badge, quantity-summary and date elements. Never touch
 * attributes, input.value, JSON/Store API data, contenteditable or form controls. */
(() => {
 const selectors = '.woocommerce-Price-amount, .wc-block-formatted-money-amount, .wc-block-components-product-price, .wc-block-mini-cart__badge, .wc-block-mini-cart__quantity-badge, .wc-block-components-order-summary-item__quantity, .product-quantity, .wp-block-post-date, time';
 const digits = text => text.replace(/([0-9۰-۹]),(?=[0-9۰-۹])/g, '$1٬').replace(/[0-9]/g, n => '۰۱۲۳۴۵۶۷۸۹'[n]);
 const update = () => {
  document.querySelectorAll(selectors).forEach(element => {
   if (element.closest('input, textarea, select, [contenteditable="true"], .wp-admin')) return;
   const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
   let node;
   while ((node = walker.nextNode())) {
    if (node.parentElement.closest('script, style, input, textarea, select, [contenteditable="true"]')) continue;
    const converted = digits(node.nodeValue);
    if (converted !== node.nodeValue) node.nodeValue = converted;
   }
  });
 };
 let scheduled = false;
 new MutationObserver(records => {
  if (scheduled || !records.some(r => r.type === 'childList' || r.target.parentElement?.closest(selectors))) return;
  scheduled = true;
  requestAnimationFrame(() => { scheduled = false; update(); });
 }).observe(document.body, {subtree:true, childList:true, characterData:true});
 update();
})();
