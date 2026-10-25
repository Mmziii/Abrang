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
