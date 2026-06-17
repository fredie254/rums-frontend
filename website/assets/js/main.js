/* ═══════════════════════════════════════════════════════════════════════════
   RUMS Landing Page – main.js  v1.0
   ═══════════════════════════════════════════════════════════════════════════

   TABLE OF CONTENTS
   ─────────────────
   1.  Announcement bar (localStorage dismiss)
   2.  Navbar scroll behaviour + announce-gone class
   3.  Smooth scroll for anchor links
   4.  AOS-style scroll animations
   5.  Role tabs
   6.  FAQ accordion
   7.  Mini Chart.js charts (hero + spotlight)
   8.  Demo form submit (validation + success message)
   9.  Navbar active link highlight on scroll
   10. Mobile menu – close on outside click
   ═══════════════════════════════════════════════════════════════════════════ */

'use strict';

/* ── 1. Announcement Bar ────────────────────────────────────────────────── */
(function initAnnouncementBar() {
  const bar   = document.getElementById('announcement-bar');
  const close = document.getElementById('announcement-close');
  const navbar = document.getElementById('navbar');
  if (!bar || !close) return;

  const LS_KEY = 'rums_announce_dismissed_v1';

  function dismiss() {
    bar.classList.add('dismissed');
    if (navbar) navbar.classList.add('announce-gone');
    try { localStorage.setItem(LS_KEY, '1'); } catch (_) {}
  }

  // Apply on load if already dismissed
  if (localStorage.getItem(LS_KEY) === '1') {
    // Instantly hide without transition to avoid flash
    bar.style.transition = 'none';
    bar.style.height = '0';
    bar.style.opacity = '0';
    bar.style.overflow = 'hidden';
    bar.style.pointerEvents = 'none';
    if (navbar) {
      navbar.style.top = '0';
      navbar.classList.add('announce-gone');
    }
  }

  close.addEventListener('click', dismiss);

  // Also allow clicking the "See what's new" link to dismiss
  const link = bar.querySelector('.announcement-link');
  if (link) link.addEventListener('click', () => setTimeout(dismiss, 400));
})();

/* ── 2. Navbar Scroll Behaviour ─────────────────────────────────────────── */
(function initNavbar() {
  const navbar = document.getElementById('navbar');
  if (!navbar) return;

  function onScroll() {
    if (window.scrollY > 60) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // run once on load
})();

/* ── 3. Smooth Scroll ───────────────────────────────────────────────────── */
(function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const href = a.getAttribute('href');
      if (!href || href === '#') return;
      const target = document.querySelector(href);
      if (!target) return;
      e.preventDefault();

      // Offset: navbar height (~64px) + small gap
      const offset = 72;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });

      // Close mobile menu if open
      const collapse = document.getElementById('navMenu');
      if (collapse && collapse.classList.contains('show')) {
        const toggler = document.querySelector('.navbar-toggler');
        if (toggler) toggler.click();
      }
    });
  });
})();

/* ── 4. AOS-Style Scroll Animations ────────────────────────────────────── */
(function initAOS() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el    = entry.target;
      const delay = parseInt(el.dataset.aosDelay || '0', 10);
      setTimeout(() => el.classList.add('aos-animate'), delay);
      observer.unobserve(el);
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('[data-aos]').forEach(el => observer.observe(el));
})();

/* ── 5. Role Tabs ───────────────────────────────────────────────────────── */
(function initRoleTabs() {
  const tabs = document.querySelectorAll('.role-tab');
  if (!tabs.length) return;

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      // Deactivate all
      tabs.forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      document.querySelectorAll('.role-panel').forEach(p => p.classList.remove('active'));

      // Activate clicked
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      const panel = document.getElementById('role-' + tab.dataset.role);
      if (panel) panel.classList.add('active');
    });
  });
})();

/* ── 6. FAQ Accordion ───────────────────────────────────────────────────── */
(function initFAQ() {
  document.querySelectorAll('.faq-question').forEach(q => {
    q.addEventListener('click', () => {
      const item   = q.closest('.faq-item');
      const isOpen = item.classList.contains('open');

      // Close all
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));

      // Open clicked if it was closed
      if (!isOpen) item.classList.add('open');
    });
  });
})();

/* ── 7. Chart.js Charts ─────────────────────────────────────────────────── */
(function initCharts() {
  // Shared colour palette
  const blueGrad = ['rgba(13,110,253,.8)', 'rgba(10,88,202,.8)'];

  /* ── Hero revenue bar chart ── */
  const heroRevenueCtx = document.getElementById('revenueChart');
  if (heroRevenueCtx) {
    new Chart(heroRevenueCtx, {
      type: 'bar',
      data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
          data: [840, 920, 880, 1050, 970, 1120],
          backgroundColor: 'rgba(13,110,253,.75)',
          borderRadius: 4,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: { x: { display: false }, y: { display: false } }
      }
    });
  }

  /* ── Hero occupancy doughnut ── */
  const heroOccCtx = document.getElementById('occupancyChart');
  if (heroOccCtx) {
    new Chart(heroOccCtx, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [89, 8, 3],
          backgroundColor: ['#198754','#ffc107','#dc3545'],
          borderWidth: 0,
          hoverOffset: 4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  }

  /* ── Spotlight C bar chart ── */
  const spotBarCtx = document.getElementById('spotlightBarChart');
  if (spotBarCtx) {
    new Chart(spotBarCtx, {
      type: 'bar',
      data: {
        labels: ['Jan','Feb','Mar','Apr','May','Jun'],
        datasets: [{
          data: [1120, 980, 1240, 1350, 1180, 1490],
          backgroundColor: 'rgba(13,110,253,.7)',
          borderRadius: 5,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
          x: {
            display: true,
            ticks: { color: '#94a3b8', font: { size: 10 } },
            grid: { display: false },
            border: { display: false }
          },
          y: {
            display: true,
            ticks: { color: '#94a3b8', font: { size: 10 }, maxTicksLimit: 4 },
            grid: { color: 'rgba(226,232,240,.5)' },
            border: { display: false }
          }
        }
      }
    });
  }

  /* ── Spotlight C doughnut ── */
  const spotDoughCtx = document.getElementById('spotlightDoughnutChart');
  if (spotDoughCtx) {
    new Chart(spotDoughCtx, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [89, 11],
          backgroundColor: ['#0d6efd','#e2e8f0'],
          borderWidth: 0,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '72%',
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  }
})();

/* ── 8. Demo Form – Bootstrap Validation + Success Message ─────────────── */
(function initDemoForm() {
  const form    = document.getElementById('demo-form');
  const success = document.getElementById('demo-success');
  if (!form || !success) return;

  form.addEventListener('submit', e => {
    e.preventDefault();
    e.stopPropagation();

    // Bootstrap's native validation check
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    // Simulate async submit
    const btn = form.querySelector('.btn-demo-submit');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending…';
    }

    setTimeout(() => {
      form.style.display = 'none';
      success.hidden = false;
      success.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 900);
  });
})();

/* ── 9. Navbar Active Link Highlight on Scroll ──────────────────────────── */
(function initActiveLinks() {
  const sections  = document.querySelectorAll('section[id]');
  const navLinks  = document.querySelectorAll('.nav-link-custom[href^="#"]');
  if (!sections.length || !navLinks.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id;
        navLinks.forEach(a => {
          const matches = a.getAttribute('href') === `#${id}`;
          a.classList.toggle('active-link', matches);
          a.style.color = matches ? 'rgba(255,255,255,1)' : '';
        });
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });

  sections.forEach(s => observer.observe(s));
})();

/* ── 10. Mobile Menu – Close on Outside Click ───────────────────────────── */
(function initMobileMenuClose() {
  document.addEventListener('click', e => {
    const menu    = document.getElementById('navMenu');
    const toggler = document.querySelector('.navbar-toggler');
    if (!menu || !menu.classList.contains('show')) return;
    if (!menu.contains(e.target) && !toggler?.contains(e.target)) {
      toggler?.click();
    }
  });
})();

/* ── 11. Persona Switcher ────────────────────────────────────────────────── */
(function initPersonaSwitcher() {
  const btns  = document.querySelectorAll('.persona-btn');
  const texts = document.querySelectorAll('.persona-text');
  if (!btns.length) return;

  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      const persona = btn.dataset.persona;

      // Update buttons
      btns.forEach(b => {
        b.classList.toggle('active', b === btn);
        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
      });

      // Swap subtitle text with a quick fade
      const subtitle = document.getElementById('hero-subtitle');
      if (subtitle) {
        subtitle.style.opacity = '0';
        subtitle.style.transform = 'translateY(6px)';
        subtitle.style.transition = 'opacity .18s ease, transform .18s ease';
        setTimeout(() => {
          texts.forEach(t => t.classList.toggle('active', t.dataset.persona === persona));
          subtitle.style.opacity = '1';
          subtitle.style.transform = 'translateY(0)';
        }, 180);
      }
    });
  });
})();
