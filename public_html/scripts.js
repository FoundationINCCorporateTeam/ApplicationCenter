// scripts.js - small interactive behaviors (mobile drawer, accordions, theme toggle)
(() => {
  const mobileToggle = document.getElementById('mobileToggle');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const closeDrawer = document.getElementById('closeDrawer');

  function openDrawer() {
    mobileDrawer.classList.add('open');
    mobileDrawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // focus first link
    const first = mobileDrawer.querySelector('a, button, input');
    if (first) first.focus();
  }
  function closeDrawerFn() {
    mobileDrawer.classList.remove('open');
    mobileDrawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    mobileToggle?.focus();
  }
  mobileToggle?.addEventListener('click', openDrawer);
  closeDrawer?.addEventListener('click', closeDrawerFn);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDrawerFn();
  });

  // accordions (details)
  document.querySelectorAll('details').forEach(d => {
    d.addEventListener('toggle', () => {
      if (d.open) {
        // close siblings
        const parent = d.parentElement;
        if (parent) parent.querySelectorAll('details').forEach(sib => { if (sib !== d) sib.removeAttribute('open'); });
      }
    });
  });

  // feed rotation for any .feed container
  setInterval(() => {
    document.querySelectorAll('.feed').forEach(feed => {
      const first = feed.children[0];
      if (first) {
        feed.appendChild(first.cloneNode(true));
        first.remove();
      }
    });
  }, 3500);

  // theme toggle on double-click logo
  document.querySelector('.logo')?.addEventListener('dblclick', () => {
    const has = document.documentElement.hasAttribute('data-theme');
    if (has) document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', 'dark');
  });

})();