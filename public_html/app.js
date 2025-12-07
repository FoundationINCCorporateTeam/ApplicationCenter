// app.js - client side routing & UI interactions (improved)
// - Added 404 route
// - Improved mobile drawer accessibility & focus trap
// - Better active nav detection
// - Minor performance tweaks

const templates = {
  '/': document.getElementById('tmpl-home').content,
  '/features/ai-application-centers': document.getElementById('tmpl-feature-ai-application-centers').content,
  '/features/rank-centers': document.getElementById('tmpl-feature-rank-centers').content,
  '/features/ai-training-centers': document.getElementById('tmpl-feature-ai-training-centers').content,
  '/features/ai-ingame-analytics': document.getElementById('tmpl-feature-coming-soon').content,
  '/features/ai-moderation': document.getElementById('tmpl-feature-coming-soon').content,
  '/features/ai-customer-support': document.getElementById('tmpl-feature-coming-soon').content,
  '/solutions': document.getElementById('tmpl-solutions').content,
  '/pricing': document.getElementById('tmpl-pricing').content,
  '/enterprise': document.getElementById('tmpl-enterprise').content,
  '/faq': document.getElementById('tmpl-faq') ? document.getElementById('tmpl-faq').content : null
};

const app = document.getElementById('app');
const mobileDrawer = document.getElementById('mobileDrawer');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const closeDrawerBtn = document.getElementById('closeDrawer');
const homeLogo = document.getElementById('homeLogo');

let lastFocusedElementBeforeDrawer = null;

function renderContent(path, replace = false){
  const content = templates[path] || (templates['/404'] = document.getElementById('tmpl-404').content);
  app.innerHTML = '';
  const clone = document.importNode(content, true);
  app.appendChild(clone);
  if(!replace){ history.pushState({path}, '', path); }
  document.body.setAttribute('data-route', path);
  // nice reveal animation
  requestAnimationFrame(()=> {
    app.querySelectorAll('.news-article, .hero, .service-card, .panel, .price-card').forEach((el,i)=>{
      el.style.opacity = 0;
      el.style.transform = 'translateY(8px)';
      setTimeout(()=>{ el.style.transition = 'all .45s cubic-bezier(.2,.9,.2,1)'; el.style.opacity=1; el.style.transform='translateY(0)'; }, 80*i);
    });
  });
  // update active nav
  updateNavActive(path);
  // ensure focus lands in content for screen readers
  const firstHeading = app.querySelector('h2, h1, main, section');
  if(firstHeading) firstHeading.setAttribute('tabindex','-1') && firstHeading.focus();
  window.scrollTo({top:0, behavior:'smooth'});
}

function navigate(path, replace=false){
  // Normalize trailing slash
  if(path.endsWith('/') && path !== '/') path = path.replace(/\/+$/, '');
  renderContent(path, replace);
}

// link interception
document.addEventListener('click', (e)=>{
  const a = e.target.closest('a[data-link], [data-link]');
  if(a){
    e.preventDefault();
    const href = a.getAttribute('href') || a.dataset.href || '/';
    // ignore external links (full url)
    if(href.startsWith('http') && !href.startsWith(window.location.origin)) {
      window.open(href, '_blank');
      return;
    }
    navigate(href);
    if(mobileDrawer.classList.contains('open')) toggleDrawer(false);
  }
});

// handle popstate
window.addEventListener('popstate', (ev)=>{
  const path = (ev.state && ev.state.path) || location.pathname || '/';
  navigate(path, true);
});

// Drawer ARIA + focus management
function toggleDrawer(open){
  const btn = mobileMenuBtn;
  if(open){
    lastFocusedElementBeforeDrawer = document.activeElement;
    mobileDrawer.classList.add('open');
    mobileDrawer.setAttribute('aria-hidden','false');
    btn.setAttribute('aria-expanded','true');
    // set focus to close button
    setTimeout(()=> closeDrawerBtn?.focus(), 160);
    trapFocus(mobileDrawer);
  } else {
    mobileDrawer.classList.remove('open');
    mobileDrawer.setAttribute('aria-hidden','true');
    btn.setAttribute('aria-expanded','false');
    restoreFocus();
  }
}

function trapFocus(container){
  const focusable = container.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])');
  if(!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length -1];
  container.addEventListener('keydown', function trap(e){
    if(e.key === 'Tab'){
      if(e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
      else if(!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
    }
    if(e.key === 'Escape'){ toggleDrawer(false); }
  });
}

function restoreFocus(){
  if(lastFocusedElementBeforeDrawer && typeof lastFocusedElementBeforeDrawer.focus === 'function'){
    lastFocusedElementBeforeDrawer.focus();
    lastFocusedElementBeforeDrawer = null;
  }
}

// wire drawer controls
mobileMenuBtn?.addEventListener('click', ()=> toggleDrawer(true));
closeDrawerBtn?.addEventListener('click', ()=> toggleDrawer(false));
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') toggleDrawer(false); });

// initial route (use pathname or /)
navigate(location.pathname || '/');

// dark mode quick toggle via double click on logo (persist lightly in localStorage)
homeLogo?.addEventListener('dblclick', ()=>{
  const current = document.documentElement.getAttribute('data-theme');
  if(current === 'dark'){ document.documentElement.removeAttribute('data-theme'); localStorage.removeItem('polaris-theme'); }
  else { document.documentElement.setAttribute('data-theme','dark'); localStorage.setItem('polaris-theme','dark'); }
});
if(localStorage.getItem('polaris-theme') === 'dark') document.documentElement.setAttribute('data-theme','dark');

// update nav active states with smoother logic
function updateNavActive(currentPath){
  document.querySelectorAll('.nav-link, .panel-link').forEach(el=>{
    el.classList.remove('active');
    const href = el.getAttribute('href');
    if(!href) return;
    // match root and paths
    if(href === currentPath || (href !== '/' && currentPath.startsWith(href))) el.classList.add('active');
  });
}
// run once to set initial
updateNavActive(location.pathname || '/');

// Fancy little interactions (live feed rotation)
setInterval(()=>{
  const feeds = document.querySelectorAll('.feed');
  feeds.forEach(feed=>{
    const first = feed.children[0];
    if(first){
      // move first to end for a rotated effect
      feed.appendChild(first.cloneNode(true));
      first.remove();
    }
  });
}, 4000);

// Small preloader hook
document.addEventListener('readystatechange', ()=>{
  if(document.readyState==='complete'){
    document.body.style.transition='background .6s ease';
  }
});

// small UX: simple highlight for nav active via polling (keeps it aligned to location)
setInterval(()=> updateNavActive(location.pathname || '/'), 800);
