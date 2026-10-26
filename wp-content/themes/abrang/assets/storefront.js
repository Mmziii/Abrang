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

/* Filter drawer label — ensure the mobile overlay button is Persian. */
(() => {
 const translate = () => {
  document.querySelectorAll('.wc-block-product-filters__open-overlay').forEach(btn => {
   if (btn.textContent.trim() === 'Filter products' || btn.textContent.includes('Filter')) {
    const icon = btn.querySelector('svg');
    btn.textContent = 'فیلترها';
    if (icon) btn.prepend(icon);
   }
  });
  document.querySelectorAll('.wc-block-product-filters__apply').forEach(btn => {
   if (btn.textContent.trim() === 'Apply') btn.textContent = 'اعمال';
  });
 };
 new MutationObserver(translate).observe(document.body, {subtree:true, childList:true});
 translate();
})();

/* Woo blocks can re-render prices/counts client-side. Only replace text nodes in
 * display-only price, badge, quantity-summary and date elements. Never touch
 * attributes, input.value, JSON/Store API data, contenteditable or form controls. */
(() => {
 const selectors = '.woocommerce-Price-amount, .wc-block-formatted-money-amount, .wc-block-components-product-price, .wc-block-mini-cart__badge, .wc-block-mini-cart__quantity-badge, .wc-block-components-order-summary-item__quantity, .product-quantity, .wp-block-post-date, time, .woocommerce-order-overview__date, .woocommerce-orders-table__cell-order-date, .woocommerce-breadcrumb, .wc-block-breadcrumbs, .wc-block-components-product-results-count, .woocommerce-result-count, .wc-block-components-pagination, .woocommerce-pagination, .abrang-pagination, .abrang-chips, .wc-block-product-filter-price-slider, .wc-block-product-filter-checkbox-list';
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

/* Single product sticky bar — shows price + add-to-cart after the main button scrolls out. */
(() => {
 const bar = document.querySelector('.abrang-sticky-bar');
 if (!bar) return;
 const mainSummary = document.querySelector('.abrang-single-summary');
 const mainButton = mainSummary?.querySelector('.single_add_to_cart_button, .wc-block-components-product-add-to-cart-button, button[name=\"add-to-cart\"]');
 const mainPrice = mainSummary?.querySelector('.woocommerce-product-price, .wc-block-components-product-price, .price');
 const footer = document.querySelector('footer');
 if (!mainButton || !mainPrice) return;
 const barPrice = bar.querySelector('.abrang-sticky-price');
 const barButton = bar.querySelector('.abrang-sticky-button');
 const sync = () => {
  if (barPrice) barPrice.innerHTML = mainPrice.innerHTML;
  if (barButton) {
   const existing = barButton.querySelector('button');
   if (!existing) {
    const clone = mainButton.cloneNode(true);
    clone.addEventListener('click', () => mainButton.click());
    // Preserve disabled state
    const updateDisabled = () => { clone.disabled = mainButton.disabled; clone.setAttribute('aria-disabled', String(mainButton.disabled)); };
    new MutationObserver(updateDisabled).observe(mainButton, {attributes:true, attributeFilter:['disabled','class']});
    updateDisabled();
    barButton.appendChild(clone);
   } else {
    existing.disabled = mainButton.disabled;
   }
  }
 };
 // Keep price in sync when variations change the price
 new MutationObserver(sync).observe(mainPrice, {subtree:true, childList:true, characterData:true});
 sync();
 let buttonVisible = true;
 let footerVisible = false;
 const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
   if (entry.target === mainButton) buttonVisible = entry.isIntersecting;
   if (entry.target === footer) footerVisible = entry.isIntersecting;
   const show = !buttonVisible && !footerVisible && window.innerWidth <= 781;
   // Only on mobile per spec — desktop has normal flow, but we also support small desktop?
   // Spec says mobile: sticky bottom bar. So enable only on mobile.
   bar.classList.toggle('is-visible', show && window.matchMedia('(max-width: 781px)').matches);
   bar.setAttribute('aria-hidden', String(!show));
  });
 }, {threshold:0});
 observer.observe(mainButton);
 if (footer) observer.observe(footer);
 // Re-evaluate on resize
 matchMedia('(max-width: 781px)').addEventListener('change', () => {
  const show = !buttonVisible && !footerVisible;
  bar.classList.toggle('is-visible', show && window.matchMedia('(max-width: 781px)').matches);
 });
})();
