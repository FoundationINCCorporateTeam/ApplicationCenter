/* PolarisONE site JS
   - small interactions for dropdowns
   - subtle VFX triggers
   - scroll reveal
*/

(() => {
  const featuresToggle = document.querySelectorAll('.group-toggle');
  featuresToggle.forEach(btn => {
    btn.addEventListener('click', (e) => {
      // toggles the next sibling dropdown if present
      const dd = btn.nextElementSibling;
      if (!dd) return;
      const open = dd.style.opacity === '1' || dd.classList.contains('open');
      if (open) {
        dd.style.opacity = '0'; dd.style.transform = 'translateY(-8px) scale(.98)'; dd.style.pointerEvents = 'none'; dd.classList.remove('open');
      } else {
        dd.style.opacity = '1'; dd.style.transform = 'translateY(0) scale(1)'; dd.style.pointerEvents = 'auto'; dd.classList.add('open');
      }
    });
  });

  // Glitch hover micro-interactions
  document.querySelectorAll('.glitch, .gloss, .visual-card, .story').forEach(el => {
    el.addEventListener('mouseenter', () => {
      el.classList.add('glitch-animate');
    });
    el.addEventListener('mouseleave', () => {
      el.classList.remove('glitch-animate');
    });
  });

  // parallax for visual-card on mouse
  const visual = document.querySelector('.visual-card');
  if (visual) {
    visual.addEventListener('mousemove', (ev) => {
      const rect = visual.getBoundingClientRect();
      const x = (ev.clientX - rect.left) / rect.width - 0.5;
      const y = (ev.clientY - rect.top) / rect.height - 0.5;
      visual.style.transform = `perspective(1000px) rotateX(${(-y*6)}deg) rotateY(${(x*6)}deg) translateZ(0)`;
    });
    visual.addEventListener('mouseleave', () => {
      visual.style.transform = '';
    });
  }

  // Reveal on scroll using intersection observer
  const revealEls = document.querySelectorAll('.story, .card, .stat-card, .prose-cta');
  const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        entry.target.classList.add('in-view');
        observer.unobserve(entry.target);
      }
    }
  }, { threshold: 0.12 });

  revealEls.forEach(x => {
    x.style.opacity = '0';
    x.style.transform = 'translateY(16px)';
    observer.observe(x);
  });

  // Mark the active nav item based on pathname
  const path = window.location.pathname;
  document.querySelectorAll('.main-nav a').forEach(a => {
    try { if (a.getAttribute('href') === path) a.classList.add('active'); } catch(e) {}
  });

  // make ticker gently scroll
  document.querySelectorAll('.ticker .list').forEach(list => {
    let y = 0;
    setInterval(()=>{
      y -= 18; // step
      if (Math.abs(y) > list.scrollHeight) y = 0;
      list.style.transform = `translateY(${y}px)`;
    }, 1800);
  });

  // Subtle 'paper-type' shimmer on hero
  const hero = document.querySelector('.hero');
  if (hero) {
    let pos = 0;
    setInterval(() => {
      pos = (pos + 1) % 360;
      hero.style.setProperty('--paper-rot', pos + 'deg');
    }, 2000);
  }

  // Subscribe form simple handler (no backend)
  document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = f.querySelector('input[type="email"]');
      if(!email) return alert('Thanks — we would sign you up (demo).');
      const val = (email.value || '').trim();
      if (!val || !val.includes('@')) {
        email.classList.add('error');
        email.focus();
        return;
      }
      email.value = '';
      const tag = document.createElement('div');
      tag.className = 'tiny-toast';
      tag.textContent = "Thanks — you're on the list!";
      document.body.appendChild(tag);
      setTimeout(()=>tag.classList.add('show'),20);
      setTimeout(()=>{tag.classList.remove('show'); setTimeout(()=>tag.remove(),400)},3000);
    });
  });

  // Tiny toast styles injected at runtime so page stays self-contained
  const style = document.createElement('style');
  style.innerHTML = `.tiny-toast{position:fixed;right:22px;bottom:22px;background:linear-gradient(90deg,#0b1220,#0b2b3a);color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,0.6);transform:translateY(8px);opacity:0;transition:all .36s ease;z-index:9999}.tiny-toast.show{transform:translateY(0);opacity:1}`;
  document.head.appendChild(style);

  // progressive enhancement: attach keyboard handler for dropdown list
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.features-dropdown.open').forEach(dd => {
        dd.style.opacity = '0'; dd.style.pointerEvents = 'none'; dd.classList.remove('open');
      });
    }
  });

  // small debug overlay toggle (press 'd')
  let debugOverlay = null;
  document.addEventListener('keydown', (e) => {
    if (e.key.toLowerCase() === 'd' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      if (!debugOverlay) {
        debugOverlay = document.createElement('div');
        debugOverlay.style.position = 'fixed';
        debugOverlay.style.left = '10px';
        debugOverlay.style.top = '10px';
        debugOverlay.style.background = 'rgba(0,0,0,0.6)';
        debugOverlay.style.color = '#fff';
        debugOverlay.style.padding = '8px 12px';
        debugOverlay.style.zIndex = 99999;
        debugOverlay.style.borderRadius = '8px';
        debugOverlay.textContent = 'Perf: — ms';
        document.body.appendChild(debugOverlay);
        let last = performance.now();
        const id = setInterval(()=>{
          const now = performance.now();
          debugOverlay.textContent = `Perf: ${(now-last).toFixed(1)} ms`;
          last = now;
        }, 500);
        debugOverlay.dataset._interval = id;
      } else {
        clearInterval(debugOverlay.dataset._interval);
        debugOverlay.remove(); debugOverlay = null;
      }
    }
  });
})();
