/**
 * PolarisONE - Main JavaScript
 * Handles interactions, animations, and dynamic behavior
 * 
 * Features:
 * - Intersection Observer for scroll animations
 * - Modal handling
 * - Mobile navigation toggle
 * - Mode toggle (Vintage/Sleek)
 * - Counter animations
 * - Typewriter effect
 */

(function() {
  'use strict';

  // ============================================
  // CONFIGURATION
  // ============================================
  const CONFIG = {
    animationThreshold: 0.15,
    counterDuration: 2000,
    typewriterSpeed: 50,
    storageKeys: {
      mode: 'polarisone-mode'
    }
  };

  // ============================================
  // DOM READY
  // ============================================
  document.addEventListener('DOMContentLoaded', function() {
    initMobileNav();
    initModeToggle();
    initScrollAnimations();
    initModals();
    initCounterAnimations();
    initTypewriter();
    initLazyImages();
    initParallax();
    initKeyboardNav();
    initSmoothScroll();
  });

  // ============================================
  // MOBILE NAVIGATION
  // ============================================
  function initMobileNav() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.mobile-nav');
    
    if (!toggle || !nav) return;

    toggle.addEventListener('click', function() {
      const isOpen = nav.classList.contains('is-open');
      
      nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', !isOpen);
      
      // Update icon
      const icon = toggle.querySelector('svg use');
      if (icon) {
        icon.setAttribute('href', isOpen ? '#icon-menu' : '#icon-close');
      }
      
      // Trap focus in mobile nav when open
      if (!isOpen) {
        document.body.style.overflow = 'hidden';
        nav.querySelector('a')?.focus();
      } else {
        document.body.style.overflow = '';
      }
    });

    // Close on escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        toggle.focus();
      }
    });

    // Close when clicking a link
    nav.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function() {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
  }

  // ============================================
  // MODE TOGGLE (VINTAGE / SLEEK)
  // ============================================
  function initModeToggle() {
  const toggle = document.querySelector('.mode-toggle');
  if (!toggle) return;

  // Load saved preference
  const savedMode = localStorage.getItem(CONFIG.storageKeys.mode);

  // Default to 'sleek' (Dark) if nothing saved
  const mode = savedMode || 'sleek';
  if (mode === 'sleek') {
    document.body.classList.add('sleek-mode');
  } else {
    document.body.classList.add('vintage-mode');
  }
  updateModeToggleUI(toggle, mode);

  toggle.addEventListener('click', function() {
    const isSleek = document.body.classList.toggle('sleek-mode');
    
    // Ensure the other class is removed
    if (isSleek) {
      document.body.classList.remove('vintage-mode');
    } else {
      document.body.classList.add('vintage-mode');
      document.body.classList.remove('sleek-mode');
    }

    const mode = isSleek ? 'sleek' : 'vintage';
    localStorage.setItem(CONFIG.storageKeys.mode, mode);
    updateModeToggleUI(toggle, mode);

    // Announce to screen readers
    announceToScreenReader(`Switched to ${mode} mode`);
  });
}

function updateModeToggleUI(toggle, mode) {
  const options = toggle.querySelectorAll('.mode-toggle-option');
  options.forEach(function(option) {
    const optionMode = option.dataset.mode;
    option.classList.toggle('active', optionMode === mode);
  });
}


  // ============================================
  // SCROLL ANIMATIONS (Intersection Observer)
  // ============================================
  function initScrollAnimations() {
    // Check for reduced motion preference
    if (prefersReducedMotion()) return;

    const animatedElements = document.querySelectorAll(
      '.fade-in, .fade-in-left, .fade-in-right, .stagger-children, .line-draw, .ink-reveal'
    );

    if (!animatedElements.length) return;

    const observer = new IntersectionObserver(
      function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            
            // Optionally unobserve after animation
            // observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: CONFIG.animationThreshold,
        rootMargin: '0px 0px -50px 0px'
      }
    );

    animatedElements.forEach(function(el) {
      observer.observe(el);
    });
  }

  // ============================================
  // MODALS
  // ============================================
  function initModals() {
    const modalTriggers = document.querySelectorAll('[data-modal-trigger]');
    const modals = document.querySelectorAll('.modal');
    const backdrops = document.querySelectorAll('.modal-backdrop');

    modalTriggers.forEach(function(trigger) {
      trigger.addEventListener('click', function(e) {
        e.preventDefault();
        const modalId = trigger.dataset.modalTrigger;
        openModal(modalId);
      });
    });

    // Close buttons
    document.querySelectorAll('.modal-close').forEach(function(btn) {
      btn.addEventListener('click', function() {
        closeAllModals();
      });
    });

    // Close on backdrop click
    backdrops.forEach(function(backdrop) {
      backdrop.addEventListener('click', function() {
        closeAllModals();
      });
    });

    // Close on escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeAllModals();
      }
    });
  }

  function openModal(modalId) {
    const modal = document.getElementById(modalId);
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (!modal || !backdrop) return;

    backdrop.classList.add('is-open');
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';

    // Focus management
    const firstFocusable = modal.querySelector(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (firstFocusable) {
      firstFocusable.focus();
    }

    // Trap focus
    trapFocus(modal);
  }

  function closeAllModals() {
    const modals = document.querySelectorAll('.modal.is-open');
    const backdrops = document.querySelectorAll('.modal-backdrop.is-open');

    modals.forEach(function(modal) {
      modal.classList.remove('is-open');
    });

    backdrops.forEach(function(backdrop) {
      backdrop.classList.remove('is-open');
    });

    document.body.style.overflow = '';

    // Return focus to trigger
    const lastTrigger = document.querySelector('[data-modal-trigger]:focus');
    if (lastTrigger) {
      lastTrigger.focus();
    }
  }

  function trapFocus(element) {
    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    element.addEventListener('keydown', function(e) {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          lastElement.focus();
          e.preventDefault();
        }
      } else {
        if (document.activeElement === lastElement) {
          firstElement.focus();
          e.preventDefault();
        }
      }
    });
  }

  // ============================================
  // COUNTER ANIMATIONS
  // ============================================
  function initCounterAnimations() {
    if (prefersReducedMotion()) return;

    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;

    const observer = new IntersectionObserver(
      function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.5 }
    );

    counters.forEach(function(counter) {
      observer.observe(counter);
    });
  }

  function animateCounter(element) {
    const target = parseInt(element.dataset.counter, 10);
    const suffix = element.dataset.counterSuffix || '';
    const duration = CONFIG.counterDuration;
    const start = 0;
    const startTime = performance.now();

    function updateCounter(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function (ease-out)
      const easeOut = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(start + (target - start) * easeOut);
      
      element.textContent = formatNumber(current) + suffix;

      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      }
    }

    requestAnimationFrame(updateCounter);
  }

  function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  // ============================================
  // TYPEWRITER EFFECT
  // ============================================
  function initTypewriter() {
    if (prefersReducedMotion()) return;

    const typewriters = document.querySelectorAll('[data-typewriter]');
    
    typewriters.forEach(function(element) {
      const text = element.dataset.typewriter;
      const speed = parseInt(element.dataset.typewriterSpeed, 10) || CONFIG.typewriterSpeed;
      
      element.textContent = '';
      element.style.borderRight = '3px solid var(--color-accent)';
      
      let i = 0;
      function type() {
        if (i < text.length) {
          element.textContent += text.charAt(i);
          i++;
          setTimeout(type, speed);
        } else {
          // Blink cursor for a while, then remove
          setTimeout(function() {
            element.style.borderRight = 'none';
          }, 3000);
        }
      }
      
      // Start typing when visible
      const observer = new IntersectionObserver(
        function(entries) {
          if (entries[0].isIntersecting) {
            type();
            observer.disconnect();
          }
        },
        { threshold: 0.5 }
      );
      
      observer.observe(element);
    });
  }

  // ============================================
  // LAZY IMAGES
  // ============================================
  function initLazyImages() {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    
    lazyImages.forEach(function(img) {
      if (img.complete) {
        img.classList.add('loaded');
      } else {
        img.addEventListener('load', function() {
          img.classList.add('loaded');
        });
      }
    });
  }

  // ============================================
  // PARALLAX EFFECT
  // ============================================
  function initParallax() {
    if (prefersReducedMotion()) return;

    const parallaxElements = document.querySelectorAll('[data-parallax]');
    if (!parallaxElements.length) return;

    let ticking = false;

    function updateParallax() {
      const scrollY = window.pageYOffset;

      parallaxElements.forEach(function(element) {
        const speed = parseFloat(element.dataset.parallax) || 0.5;
        const offset = scrollY * speed;
        element.style.transform = `translateY(${offset}px)`;
      });

      ticking = false;
    }

    window.addEventListener('scroll', function() {
      if (!ticking) {
        requestAnimationFrame(updateParallax);
        ticking = true;
      }
    }, { passive: true });
  }

  // ============================================
  // KEYBOARD NAVIGATION
  // ============================================
  function initKeyboardNav() {
    // Add keyboard navigation for dropdown menus if present
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(function(dropdown) {
      const trigger = dropdown.querySelector('.dropdown-trigger');
      const menu = dropdown.querySelector('.dropdown-menu');
      
      if (!trigger || !menu) return;

      trigger.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
          e.preventDefault();
          menu.classList.add('is-open');
          menu.querySelector('a')?.focus();
        }
      });

      menu.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          menu.classList.remove('is-open');
          trigger.focus();
        }
      });
    });
  }

  // ============================================
  // SMOOTH SCROLL
  // ============================================
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
      anchor.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const target = document.querySelector(targetId);
        if (!target) return;

        e.preventDefault();
        
        target.scrollIntoView({
          behavior: prefersReducedMotion() ? 'auto' : 'smooth',
          block: 'start'
        });

        // Update URL
        history.pushState(null, null, targetId);

        // Focus management for accessibility
        target.setAttribute('tabindex', '-1');
        target.focus();
      });
    });
  }

  // ============================================
  // UTILITY FUNCTIONS
  // ============================================
  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('role', 'status');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    
    document.body.appendChild(announcement);
    
    setTimeout(function() {
      announcement.remove();
    }, 1000);
  }

  // ============================================
  // EXPOSE PUBLIC API
  // ============================================
  window.PolarisONE = {
    openModal: openModal,
    closeAllModals: closeAllModals,
    prefersReducedMotion: prefersReducedMotion
  };

})();