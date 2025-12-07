// assets/js/site.vfx.js
// Lightweight VFX & UI glue for the newsroom site.
// - small particle sweep on hero load
// - subtle parallax following mouse for "vfx-canvas"
// - small headline shimmer via CSS class toggles
// - modal focus trap helper

(function () {
  if (window.__polaris_vfx_loaded) return;
  window.__polaris_vfx_loaded = true;

  // canvas background for subtle particles / sweep
  const canvasEl = document.getElementById('vfx-canvas');
  let canvas, ctx, w, h, particles = [];

  function initCanvas(){
    if (!canvasEl) return;
    canvas = document.createElement('canvas');
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvasEl.appendChild(canvas);
    ctx = canvas.getContext('2d');
    resize();
    createParticles(28);
    animate();
    window.addEventListener('resize', resize);
  }

  function resize(){
    if (!canvas) return;
    w = canvas.width = Math.floor(window.innerWidth * devicePixelRatio);
    h = canvas.height = Math.floor(window.innerHeight * devicePixelRatio);
    canvas.style.width = window.innerWidth + 'px';
    canvas.style.height = window.innerHeight + 'px';
    ctx.scale(devicePixelRatio, devicePixelRatio);
  }

  function createParticles(n){
    particles = [];
    for (let i=0;i<n;i++){
      particles.push({
        x: Math.random()*window.innerWidth,
        y: Math.random()*window.innerHeight,
        vx: (Math.random()-0.5)*0.2,
        vy: (Math.random()-0.5)*0.2,
        r: 1 + Math.random()*3,
        alpha: 0.08 + Math.random()*0.14
      });
    }
  }

  function animate(){
    if (!ctx) return;
    ctx.clearRect(0,0,window.innerWidth,window.innerHeight);
    particles.forEach(p=>{
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < -30) p.x = window.innerWidth + 30;
      if (p.x > window.innerWidth + 30) p.x = -30;
      if (p.y < -30) p.y = window.innerHeight + 30;
      if (p.y > window.innerHeight + 30) p.y = -30;
      ctx.beginPath();
      ctx.fillStyle = `rgba(96,166,255,${p.alpha})`;
      ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
      ctx.fill();
    });
    requestAnimationFrame(animate);
  }

  // mouse parallax for canvas (subtle)
  function setupParallax(){
    if (!canvasEl) return;
    document.addEventListener('mousemove', (e)=>{
      const cx = e.clientX / window.innerWidth - 0.5;
      const cy = e.clientY / window.innerHeight - 0.5;
      canvasEl.style.transform = `translate3d(${cx*8}px, ${cy*8}px, 0) rotate(${cx*0.6}deg)`;
      canvasEl.style.transition = 'transform 240ms linear';
    });
  }

  // Headline shimmer effect
  function microShimmer(){
    document.querySelectorAll('.hero-title, .feature-hero h1').forEach(el=>{
      el.addEventListener('mouseenter', ()=>{ el.style.backgroundImage = 'linear-gradient(90deg, rgba(255,255,255,0.2), rgba(255,255,255,0.8), rgba(255,255,255,0.2))'; el.style.backgroundClip='text'; el.style.webkitBackgroundClip='text'; el.style.color='transparent'; setTimeout(()=>{ el.style.backgroundImage=''; el.style.color=''; },700); });
    });
  }

  // Modal focus trap helper
  function trapModalFocus(modal){
    const focusable = modal.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length-1];
    function check(e){
      if (e.key !== 'Tab') return;
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    modal.addEventListener('keydown', check);
    modal._removeTrap = ()=>modal.removeEventListener('keydown', check);
  }

  // Ensure new modals get focus trap
  function watchModals(){
    const observer = new MutationObserver((mutations)=>{
      mutations.forEach(m=>{
        m.addedNodes.forEach(node=>{
          if (node.nodeType===1 && node.classList.contains('modal-overlay')){
            const modal = node.querySelector('.modal');
            setTimeout(()=>{ const first = modal.querySelector('input,textarea,select,button'); first?.focus(); trapModalFocus(modal); },30);
            node.addEventListener('remove', ()=>{ if (modal._removeTrap) modal._removeTrap(); });
          }
        });
      });
    });
    observer.observe(document.body, {childList:true, subtree:true});
  }

  // Init all VFX
  function init(){
    try{
      initCanvas();
      setupParallax();
      microShimmer();
      watchModals();
    }catch(e){ console.warn('VFX init failed', e); }
  }

  document.addEventListener('DOMContentLoaded', init);
})();