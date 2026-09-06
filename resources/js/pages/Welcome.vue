<template>
  <div class="erp-site" :class="{ dark: isDark }">
    <a class="erp-skip" href="#overview">Skip to content</a>

    <!-- Soft static backdrop (no infinite animation) -->
    <div class="erp-bg" aria-hidden="true">
      <span class="erp-glow erp-glow-a"></span>
      <span class="erp-glow erp-glow-b"></span>
      <span class="erp-grid"></span>
    </div>

    <!-- ============ Header ============ -->
    <header class="erp-header" :class="{ 'is-hidden': headerHidden }">
      <div class="erp-container erp-header-inner">
        <a href="#top" class="erp-brand" aria-label="Home">
          <img :src="logoUrl" alt="erpsaathi logo" class="erp-brand-logo erp-brand-logo-lg" />
        </a>

        <nav class="erp-nav" aria-label="Primary">
          <a href="#overview">Overview</a>
          <a href="#modules">Modules</a>
          <a href="#security">Security</a>
        </nav>

        <div class="erp-header-actions">
          <button
            type="button"
            class="erp-icon-btn"
            @click="toggleTheme"
            :aria-pressed="isDark"
            aria-label="Toggle dark and light mode"
            title="Toggle dark / light mode"
          >
            <svg v-if="!isDark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <main id="top">
      <!-- ============ Hero ============ -->
      <section class="erp-hero" id="overview">
        <div class="erp-container erp-hero-grid">
          <div class="erp-hero-copy">
            <p class="erp-eyebrow">Complete School ERP Platform</p>
            <h1 class="erp-hero-title">
              Run your entire school from one secure, centralized system
            </h1>
            <p class="erp-hero-sub">
              erpsaathi unifies academics, admissions, attendance, fees, examinations,
              finance, transport and reporting into a single platform — so every department works
              from the same accurate data.
            </p>
            <div class="erp-hero-actions">
              <a href="#modules" class="erp-btn erp-btn-primary erp-btn-lg">
                Explore modules
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </a>
              <a href="#security" class="erp-btn erp-btn-ghost erp-btn-lg">See security</a>
            </div>
            <ul class="erp-hero-points">
              <li v-for="point in heroPoints" :key="point">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                <span>{{ point }}</span>
              </li>
            </ul>
          </div>

          <!-- Static product-style preview, built with CSS only -->
          <div class="erp-preview" aria-hidden="true">
            <div class="erp-preview-window">
              <div class="erp-preview-bar">
                <span></span><span></span><span></span>
              </div>
              <div class="erp-preview-body">
                <div class="erp-preview-side">
                  <span class="erp-preview-logo"></span>
                  <span class="erp-preview-link is-active"></span>
                  <span class="erp-preview-link"></span>
                  <span class="erp-preview-link"></span>
                  <span class="erp-preview-link"></span>
                  <span class="erp-preview-link"></span>
                </div>
                <div class="erp-preview-main">
                  <div class="erp-preview-stats">
                    <div class="erp-preview-stat"><b>1,248</b><i>Students</i></div>
                    <div class="erp-preview-stat"><b>96%</b><i>Attendance</i></div>
                    <div class="erp-preview-stat"><b>₹8.4L</b><i>Fees today</i></div>
                  </div>
                  <div class="erp-preview-chart">
                    <span style="height:38%"></span>
                    <span style="height:62%"></span>
                    <span style="height:48%"></span>
                    <span style="height:78%"></span>
                    <span style="height:56%"></span>
                    <span style="height:88%"></span>
                    <span style="height:67%"></span>
                  </div>
                  <div class="erp-preview-rows">
                    <span></span><span></span><span></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ Introduction ============ -->
      <section class="erp-section erp-intro">
        <div class="erp-container">
          <div class="erp-section-head">
            <h2 class="erp-section-title">One platform for the whole institution</h2>
            <p class="erp-section-lead">
              Instead of scattered spreadsheets and disconnected tools, erpsaathi gives
              administrators, teachers and accounts staff a shared, role-based workspace with a
              complete audit trail.
            </p>
          </div>
          <div class="erp-pillars">
            <div class="erp-pillar" v-for="pillar in pillars" :key="pillar.title">
              <div class="erp-pillar-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                  <path v-for="(d, i) in pillar.icon" :key="i" :d="d" />
                </svg>
              </div>
              <h3>{{ pillar.title }}</h3>
              <p>{{ pillar.desc }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ Modules ============ -->
      <section class="erp-section erp-modules" id="modules">
        <div class="erp-container">
          <div class="erp-section-head">
            <p class="erp-eyebrow">Capabilities</p>
            <h2 class="erp-section-title">Everything a school needs to operate</h2>
            <p class="erp-section-lead">
              Ten core modules, fully integrated. Data entered once flows everywhere it is needed.
            </p>
          </div>
          <div class="erp-module-grid">
            <article class="erp-module" v-for="mod in modules" :key="mod.title">
              <div class="erp-module-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                  <path v-for="(d, i) in mod.icon" :key="i" :d="d" />
                </svg>
              </div>
              <h3>{{ mod.title }}</h3>
              <p>{{ mod.desc }}</p>
            </article>
          </div>
        </div>
      </section>

      <!-- ============ Security / centralized ============ -->
      <section class="erp-section erp-security" id="security">
        <div class="erp-container erp-security-grid">
          <div class="erp-security-copy">
            <p class="erp-eyebrow">Secure &amp; centralized</p>
            <h2 class="erp-section-title">Your school data stays protected and consistent</h2>
            <p class="erp-section-lead">
              Access is controlled per role, every change is recorded, and all modules read from a
              single source of truth — no duplicate records drifting out of sync.
            </p>
            <ul class="erp-check-list">
              <li v-for="item in securityPoints" :key="item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                <span>{{ item }}</span>
              </li>
            </ul>
          </div>
          <div class="erp-security-card" aria-hidden="true">
            <div class="erp-shield">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                <path d="m9 12 2 2 4-4" />
              </svg>
            </div>
            <div class="erp-shield-rows">
              <span><i>Role-based access</i><b>Enabled</b></span>
              <span><i>Audit logging</i><b>All modules</b></span>
              <span><i>Session authentication</i><b>Active</b></span>
              <span><i>Centralized database</i><b>Single source</b></span>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ CTA band ============ -->
      <section class="erp-cta">
        <div class="erp-container erp-cta-inner">
          <div>
            <h2 class="erp-cta-title">Built for modern schools</h2>
            <p class="erp-cta-sub">
              Explore the modules below to see how academics, fees, exams and operations work together in one platform.
              Staff access ERP through their school subdomain.
            </p>
          </div>
          <a href="#modules" class="erp-btn erp-btn-primary erp-btn-lg">
            Explore modules
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
          </a>
        </div>
      </section>
    </main>

    <!-- ============ Footer ============ -->
    <footer class="erp-footer">
      <div class="erp-container erp-footer-grid">
        <div class="erp-footer-brand">
          <div class="erp-brand">
            <img :src="logoUrl" alt="erpsaathi logo" class="erp-brand-logo erp-brand-logo-lg" />
          </div>
          <p class="erp-footer-note">
            A complete school management system covering academics, finance and operations.
          </p>
        </div>

        <nav class="erp-footer-col" aria-label="Modules">
          <h4>Modules</h4>
          <a href="#modules">Academic Management</a>
          <a href="#modules">Student Management</a>
          <a href="#modules">Fee Management</a>
          <a href="#modules">Examination &amp; Results</a>
        </nav>

        <nav class="erp-footer-col" aria-label="More modules">
          <h4>Operations</h4>
          <a href="#modules">Finance &amp; Payroll</a>
          <a href="#modules">Transport</a>
          <a href="#modules">Library &amp; Inventory</a>
          <a href="#modules">Reports</a>
        </nav>

        <div class="erp-footer-col">
          <h4>Platform</h4>
          <a href="#overview">Overview</a>
          <a href="#modules">Modules</a>
          <a href="#security">Security</a>
          <p class="erp-footer-fineprint">Staff sign in via their school subdomain.</p>
        </div>
      </div>
      <div class="erp-container erp-footer-bottom">
        <span>&copy; 2026 erpsaathi. All rights reserved.</span>
        <span>School ERP System</span>
      </div>
    </footer>
  </div>
</template>

<script>
const DEFAULTS = {
  schoolName: 'erpsaathi',
  logoUrl: '/assets/img/logo/erpsaathi.png',
};

export default {
  name: 'Welcome',
  data() {
    const cfg = { ...DEFAULTS, ...(typeof window !== 'undefined' && window.__WELCOME__ ? window.__WELCOME__ : {}) };
    return {
      schoolName: cfg.schoolName || DEFAULTS.schoolName,
      logoUrl: cfg.logoUrl || DEFAULTS.logoUrl,
      isDark: false,
      headerHidden: false,
      lastScrollY: 0,
      scrollTicking: false,
      year: new Date().getFullYear(),
      heroPoints: [
        'Ten integrated modules',
        'Role-based permissions',
        'One centralized database',
      ],
      pillars: [
        {
          title: 'Unified',
          desc: 'Admissions, academics, fees and exams share the same records across every department.',
          icon: ['M3 12h7V3H3zM14 21h7v-9h-7zM14 8h7V3h-7zM3 21h7v-6H3z'],
        },
        {
          title: 'Secure',
          desc: 'Each role sees only what it should, and every create, update and delete is logged.',
          icon: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
        },
        {
          title: 'Insightful',
          desc: 'Live dashboards and reports turn day-to-day activity into decisions, with export controls.',
          icon: ['M3 3v18h18', 'm7 15 3-4 3 3 4-6'],
        },
      ],
      modules: [
        {
          title: 'Academic Management',
          desc: 'Branches, sessions, classes, sections, subjects and homework in one structured hierarchy.',
          icon: ['M22 10 12 5 2 10l10 5 10-5Z', 'M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5'],
        },
        {
          title: 'Student Management',
          desc: 'Complete student profiles — admissions, parents, documents, health, transport and history.',
          icon: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
        },
        {
          title: 'Attendance',
          desc: 'Daily attendance for students, staff and drivers, plus leave management and holidays.',
          icon: ['M8 2v4M16 2v4', 'M3 10h18', 'M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z', 'm9 16 2 2 4-4'],
        },
        {
          title: 'Fee Management',
          desc: 'Fee structures, dues, collection, receipts, discounts and accounting-ready records.',
          icon: ['M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4', 'M4 6v12c0 1.1.9 2 2 2h14v-4', 'M18 12a2 2 0 0 0 0 4h4v-4Z'],
        },
        {
          title: 'Examination & Results',
          desc: 'Exams, terms, schedules, seat planning, marks entry, grades and annual report cards.',
          icon: ['M4 4a2 2 0 0 1 2-2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z', 'M14 2v6h6', 'm9 15 2 2 4-4'],
        },
        {
          title: 'Finance & Payroll',
          desc: 'Office expenses, salary structures and slips, bank accounts, transactions and cash book.',
          icon: ['M3 21h18', 'M3 10h18', 'm12 3 9 7H3l9-7Z', 'M5 10v11M19 10v11M9 10v11M15 10v11'],
        },
        {
          title: 'Library',
          desc: 'Catalogue of books, authors and publishers with member issue, return and fine tracking.',
          icon: ['M2 4.5A2.5 2.5 0 0 1 4.5 2H12v18H4.5A2.5 2.5 0 0 0 2 22.5Z', 'M22 4.5A2.5 2.5 0 0 0 19.5 2H12v18h7.5a2.5 2.5 0 0 1 2.5 2.5Z'],
        },
        {
          title: 'Transport',
          desc: 'Routes, stops, vehicles and driver assignment linked directly to student records.',
          icon: ['M8 6v6M15 6v6M2 12h19.6', 'M18 18h2a1 1 0 0 0 1-1v-4a5 5 0 0 0-1-3l-2-3a2 2 0 0 0-1.7-1H5a2 2 0 0 0-2 2v13h3', 'M9 18h6', 'M7 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM21 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z'],
        },
        {
          title: 'Inventory',
          desc: 'Products, suppliers, purchases and stock levels with low-stock alerts for stores.',
          icon: ['m7.5 4.27 9 5.15', 'M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z', 'm3.3 7 8.7 5 8.7-5', 'M12 22V12'],
        },
        {
          title: 'Reports',
          desc: 'Class-wise, area-wise and finance reports with controlled export to Excel and PDF.',
          icon: ['M3 3v18h18', 'M18 17V9M13 17V5M8 17v-3'],
        },
      ],
      securityPoints: [
        'Role-based permissions on every module',
        'Session-based authentication with a dedicated staff guard',
        'Automatic audit logging of create, update and delete actions',
        'Single centralized database shared by all modules',
        'Controlled data import and export',
      ],
    };
  },
  watch: {
    isDark(value) {
      this.applyTheme(value);
    },
  },
  mounted() {
    let saved = null;
    try {
      saved = localStorage.getItem('erp-welcome-theme-v2');
    } catch (e) {
      saved = null;
    }
    this.isDark = saved === null ? false : saved === 'dark';
    this.applyTheme(this.isDark);

    this.lastScrollY = window.scrollY || 0;
    window.addEventListener('scroll', this.onScroll, { passive: true });
  },
  beforeUnmount() {
    const root = document.documentElement;
    root.style.removeProperty('background-color');
    window.removeEventListener('scroll', this.onScroll);
  },
  methods: {
    onScroll() {
      if (this.scrollTicking) return;
      this.scrollTicking = true;
      window.requestAnimationFrame(() => {
        const y = window.scrollY || 0;
        const delta = y - this.lastScrollY;
        // Reveal on any upward scroll or near the top; hide once past the header on a
        // deliberate downward scroll.
        if (y < 80 || delta < -6) {
          this.headerHidden = false;
        } else if (delta > 6 && y > 140) {
          this.headerHidden = true;
        }
        this.lastScrollY = y;
        this.scrollTicking = false;
      });
    },
    applyTheme(dark) {
      const bg = dark ? '#07080c' : '#f8fafc';
      document.documentElement.style.backgroundColor = bg;
      if (document.body) document.body.style.backgroundColor = bg;
      try {
        localStorage.setItem('erp-welcome-theme-v2', dark ? 'dark' : 'light');
      } catch (e) {
        /* storage unavailable — ignore */
      }
    },
    toggleTheme() {
      this.isDark = !this.isDark;
    },
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap');

/* ---------- Tokens (Midnight Gold, matches ERP login/dashboard) ---------- */
.erp-site {
  --gold: #c6a75e;
  --gold-soft: #e2c98a;
  --gold-strong: #b89445;
  --ink: #07080c;
  --surface: rgba(19, 20, 26, 0.72);
  --surface-solid: #14151b;
  --border: rgba(198, 167, 94, 0.22);
  --border-soft: rgba(243, 239, 230, 0.10);
  --text: #f3efe6;
  --text-muted: #a8a29a;
  --text-dim: #837d74;

  position: relative;
  min-height: 100vh;
  background: var(--ink);
  color: var(--text);
  font-family: 'Manrope', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  overflow-x: hidden;
  scroll-behavior: smooth;
  -webkit-font-smoothing: antialiased;
}

/* Light theme: clean white + indigo (matches the ERP dashboard's --color-primary scale). */
.erp-site:not(.dark) {
  --gold: #4f46e5;
  --gold-soft: #6366f1;
  --gold-strong: #4338ca;
  --ink: #f8fafc;
  --surface: #ffffff;
  --surface-solid: #ffffff;
  --border: rgba(15, 23, 42, 0.10);
  --border-soft: rgba(15, 23, 42, 0.07);
  --text: #0f172a;
  --text-muted: #475569;
  --text-dim: #94a3b8;
}

@media (prefers-reduced-motion: reduce) {
  .erp-site { scroll-behavior: auto; }
}

.erp-container {
  width: 100%;
  max-width: 1140px;
  margin: 0 auto;
  padding: 0 24px;
}

.erp-skip {
  position: absolute;
  left: 16px;
  top: -48px;
  z-index: 100;
  background: var(--surface-solid);
  color: var(--text);
  border: 1px solid var(--border);
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: top 0.15s ease;
}
.erp-skip:focus { top: 16px; }

/* ---------- Backdrop ---------- */
.erp-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}
.erp-glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  opacity: 0.5;
}
.erp-glow-a {
  width: 620px; height: 620px;
  top: -280px; left: -160px;
  background: radial-gradient(circle, rgba(198, 167, 94, 0.30), transparent 70%);
}
.erp-glow-b {
  width: 520px; height: 520px;
  top: 40%; right: -220px;
  background: radial-gradient(circle, rgba(90, 110, 140, 0.22), transparent 70%);
}
.erp-site:not(.dark) .erp-glow { opacity: 0.5; }
.erp-site:not(.dark) .erp-glow-a { background: radial-gradient(circle, rgba(99, 102, 241, 0.16), transparent 70%); }
.erp-site:not(.dark) .erp-glow-b { background: radial-gradient(circle, rgba(56, 189, 248, 0.14), transparent 70%); }
.erp-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(198, 167, 94, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(198, 167, 94, 0.05) 1px, transparent 1px);
  background-size: 64px 64px;
  mask-image: radial-gradient(circle at 50% 0%, #000 0%, transparent 70%);
  -webkit-mask-image: radial-gradient(circle at 50% 0%, #000 0%, transparent 70%);
}
.erp-site:not(.dark) .erp-grid {
  background-image:
    linear-gradient(rgba(15, 23, 42, 0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(15, 23, 42, 0.04) 1px, transparent 1px);
}

.erp-site > *:not(.erp-bg) { position: relative; z-index: 1; }

/* ---------- Header ---------- */
.erp-header {
  position: sticky;
  top: 0;
  z-index: 40;
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  background: color-mix(in srgb, var(--ink) 78%, transparent);
  border-bottom: 1px solid var(--border-soft);
  transition: transform 0.3s ease;
  will-change: transform;
}
.erp-header.is-hidden { transform: translateY(-100%); }
@media (prefers-reduced-motion: reduce) {
  .erp-header { transition: none; }
}
.erp-header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  height: 80px;
}
.erp-brand {
  display: flex;
  align-items: center;
  gap: 11px;
  text-decoration: none;
  color: inherit;
}
.erp-brand-logo {
  width: auto;
  height: 64px;
  max-width: 240px;
  object-fit: contain;
  background: transparent;
  padding: 0;
  border-radius: 0;
  box-shadow: none;
  flex-shrink: 0;
}
.erp-brand-logo-lg {
  height: 72px;
  max-width: 280px;
}
.erp-footer-brand .erp-brand-logo-lg {
  height: 76px;
  max-width: 300px;
}
.erp-brand-text { display: flex; flex-direction: column; line-height: 1.15; }
.erp-brand-name {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 19px;
  font-weight: 700;
  letter-spacing: 0.01em;
}
.erp-brand-kicker {
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--gold);
}
.erp-nav {
  display: none;
  align-items: center;
  gap: 30px;
}
.erp-nav a {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-muted);
  text-decoration: none;
  transition: color 0.15s ease;
}
.erp-nav a:hover { color: var(--text); }
.erp-header-actions { display: flex; align-items: center; gap: 10px; }
.erp-icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--gold-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.erp-icon-btn:hover { background: color-mix(in srgb, var(--gold) 12%, transparent); }
/* Primary action stays reachable at every breakpoint; only the anchor nav collapses. */
.erp-header-cta { padding: 9px 14px; font-size: 13px; }

/* ---------- Buttons ---------- */
.erp-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border-radius: 10px;
  font-family: 'Manrope', sans-serif;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
  padding: 10px 18px;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}
.erp-btn-lg { padding: 14px 26px; font-size: 15px; }
.erp-btn-primary {
  background: linear-gradient(135deg, #d4b56a, #9a7a3a);
  color: #17130b;
  box-shadow: 0 10px 26px rgba(198, 167, 94, 0.26);
}
.erp-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(198, 167, 94, 0.36); }
.erp-site:not(.dark) .erp-btn-primary {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff;
  box-shadow: 0 10px 26px rgba(79, 70, 229, 0.24);
}
.erp-site:not(.dark) .erp-btn-primary:hover { box-shadow: 0 14px 30px rgba(79, 70, 229, 0.34); }
.erp-btn-ghost {
  background: transparent;
  color: var(--text);
  border-color: var(--border);
}
.erp-btn-ghost:hover { background: color-mix(in srgb, var(--gold) 10%, transparent); border-color: var(--gold); }
@media (prefers-reduced-motion: reduce) {
  .erp-btn:hover { transform: none; }
}

.erp-eyebrow {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--gold);
  margin: 0 0 14px;
}

/* ---------- Hero ---------- */
.erp-hero { padding: 60px 0 28px; scroll-margin-top: 76px; }
.erp-hero-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 48px;
  align-items: center;
}
.erp-hero-title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(2.35rem, 6vw, 3.6rem);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: 0.005em;
  margin: 0 0 20px;
}
.erp-hero-sub {
  font-size: clamp(1rem, 2.4vw, 1.12rem);
  line-height: 1.7;
  color: var(--text-muted);
  max-width: 34em;
  margin: 0 0 28px;
}
.erp-hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: 30px;
}
.erp-hero-points {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 10px 22px;
}
.erp-hero-points li {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--text-muted);
}
.erp-hero-points svg { color: var(--gold); flex-shrink: 0; }

/* Static CSS product preview */
.erp-preview { perspective: 1400px; }
.erp-preview-window {
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--surface);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 0 30px 70px rgba(0, 0, 0, 0.4);
  overflow: hidden;
}
.erp-site:not(.dark) .erp-preview-window { box-shadow: 0 24px 54px rgba(15, 23, 42, 0.12); }
.erp-preview-bar {
  display: flex;
  gap: 7px;
  padding: 13px 16px;
  border-bottom: 1px solid var(--border-soft);
}
.erp-preview-bar span {
  width: 10px; height: 10px; border-radius: 50%;
  background: color-mix(in srgb, var(--gold) 45%, transparent);
}
.erp-preview-body { display: flex; min-height: 300px; }
.erp-preview-side {
  width: 34%;
  max-width: 150px;
  padding: 18px 14px;
  border-right: 1px solid var(--border-soft);
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.erp-preview-logo {
  width: 42px; height: 42px; border-radius: 10px;
  background: linear-gradient(135deg, var(--gold-soft), var(--gold-strong));
}
.erp-preview-link {
  height: 9px;
  border-radius: 5px;
  background: var(--border-soft);
}
.erp-preview-link.is-active { background: color-mix(in srgb, var(--gold) 55%, transparent); width: 80%; }
.erp-preview-main { flex: 1; padding: 18px; display: flex; flex-direction: column; gap: 16px; }
.erp-preview-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.erp-preview-stat {
  border: 1px solid var(--border-soft);
  border-radius: 10px;
  padding: 10px 8px;
  text-align: center;
}
.erp-preview-stat b {
  display: block;
  font-family: 'Cormorant Garamond', serif;
  font-size: 18px;
  font-weight: 700;
  color: var(--text);
}
.erp-preview-stat i {
  font-style: normal;
  font-size: 9.5px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-dim);
}
.erp-preview-chart {
  flex: 1;
  min-height: 96px;
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--border-soft);
  border-radius: 10px;
}
.erp-preview-chart span {
  flex: 1;
  border-radius: 4px 4px 0 0;
  background: linear-gradient(180deg, var(--gold-soft), color-mix(in srgb, var(--gold) 30%, transparent));
}
.erp-preview-rows { display: flex; flex-direction: column; gap: 8px; }
.erp-preview-rows span {
  height: 12px;
  border-radius: 5px;
  background: var(--border-soft);
}
.erp-preview-rows span:nth-child(2) { width: 82%; }
.erp-preview-rows span:nth-child(3) { width: 64%; }

/* ---------- Sections ---------- */
.erp-section { padding: 52px 0; scroll-margin-top: 76px; }
.erp-section-head { max-width: 44em; margin: 0 auto 36px; text-align: center; }
.erp-section-title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(1.8rem, 4.4vw, 2.6rem);
  font-weight: 700;
  line-height: 1.18;
  margin: 0 0 14px;
}
.erp-section-lead {
  font-size: 1.02rem;
  line-height: 1.7;
  color: var(--text-muted);
  margin: 0;
}

/* Intro pillars */
.erp-pillars {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}
.erp-pillar {
  border: 1px solid var(--border-soft);
  border-radius: 14px;
  padding: 26px 24px;
  background: var(--surface);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}
.erp-pillar-icon,
.erp-module-icon {
  width: 44px;
  height: 44px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  color: var(--gold);
  background: color-mix(in srgb, var(--gold) 12%, transparent);
  border: 1px solid var(--border);
}
.erp-pillar-icon svg,
.erp-module-icon svg { width: 22px; height: 22px; }
.erp-pillar h3,
.erp-module h3 {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0 0 8px;
}
.erp-pillar p,
.erp-module p {
  font-size: 0.92rem;
  line-height: 1.65;
  color: var(--text-muted);
  margin: 0;
}

/* Modules grid */
.erp-module-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}
.erp-module {
  border: 1px solid var(--border-soft);
  border-radius: 14px;
  padding: 24px 22px;
  background: var(--surface);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  transition: transform 0.15s ease, border-color 0.15s ease;
}
.erp-module:hover { transform: translateY(-3px); border-color: var(--border); }
@media (prefers-reduced-motion: reduce) {
  .erp-module:hover { transform: none; }
}

/* Security */
.erp-security-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 40px;
  align-items: center;
}
.erp-check-list {
  list-style: none;
  padding: 0;
  margin: 24px 0 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.erp-check-list li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 0.98rem;
  line-height: 1.5;
  color: var(--text);
}
.erp-check-list svg {
  color: var(--gold);
  flex-shrink: 0;
  margin-top: 2px;
}
.erp-security-card {
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 30px;
  background: var(--surface);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}
.erp-shield {
  width: 60px;
  height: 60px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gold);
  background: color-mix(in srgb, var(--gold) 14%, transparent);
  border: 1px solid var(--border);
  margin-bottom: 22px;
}
.erp-shield svg { width: 30px; height: 30px; }
.erp-shield-rows { display: flex; flex-direction: column; gap: 12px; }
.erp-shield-rows span {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-soft);
  font-size: 0.9rem;
}
.erp-shield-rows span:last-child { border-bottom: 0; padding-bottom: 0; }
.erp-shield-rows i { font-style: normal; color: var(--text-muted); }
.erp-shield-rows b { color: var(--gold-soft); font-weight: 600; }

/* CTA band */
.erp-cta {
  padding: 20px 0 76px;
}
.erp-cta-inner {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 22px;
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 40px;
  background:
    linear-gradient(120deg, color-mix(in srgb, var(--gold) 12%, transparent), transparent 60%),
    var(--surface);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}
.erp-cta-title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(1.5rem, 3.6vw, 2.1rem);
  font-weight: 700;
  margin: 0 0 8px;
}
.erp-cta-sub {
  font-size: 0.98rem;
  color: var(--text-muted);
  margin: 0;
  max-width: 40em;
  line-height: 1.6;
}

/* ---------- Footer ---------- */
.erp-footer {
  border-top: 1px solid var(--border-soft);
  padding: 52px 0 28px;
  background: color-mix(in srgb, var(--ink) 60%, #000 6%);
}
.erp-site:not(.dark) .erp-footer { background: #f1f5f9; }
.erp-footer-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 34px;
  padding-bottom: 34px;
}
.erp-footer-brand .erp-brand { margin-bottom: 14px; }
.erp-footer-note {
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--text-muted);
  margin: 0;
  max-width: 30em;
}
.erp-footer-col { display: flex; flex-direction: column; gap: 10px; }
.erp-footer-col h4 {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--text-dim);
  margin: 0 0 4px;
}
.erp-footer-col a {
  font-size: 0.9rem;
  color: var(--text-muted);
  text-decoration: none;
  transition: color 0.15s ease;
}
.erp-footer-col a:hover { color: var(--text); }
.erp-footer-login { color: var(--gold-soft) !important; font-weight: 600; }
.erp-footer-fineprint {
  font-size: 0.8rem;
  color: var(--text-dim);
  margin: 4px 0 0;
}
.erp-footer-bottom {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 24px;
  border-top: 1px solid var(--border-soft);
  font-size: 0.82rem;
  color: var(--text-dim);
}

/* ---------- Responsive ---------- */
@media (min-width: 640px) {
  .erp-pillars { grid-template-columns: repeat(3, 1fr); }
  .erp-module-grid { grid-template-columns: repeat(2, 1fr); }
  .erp-cta-inner { flex-direction: row; align-items: center; justify-content: space-between; }
  .erp-cta-inner > a { flex-shrink: 0; }
  .erp-footer-bottom { flex-direction: row; justify-content: space-between; }
  .erp-footer-grid { grid-template-columns: 2fr 1fr 1fr 1fr; }
}

@media (min-width: 900px) {
  .erp-nav { display: flex; }
  .erp-header-cta { padding: 10px 18px; font-size: 14px; }
  .erp-hero { padding: 76px 0 44px; }
  .erp-hero-grid { grid-template-columns: 1.05fr 0.95fr; gap: 56px; }
  .erp-module-grid { grid-template-columns: repeat(3, 1fr); }
  .erp-security-grid { grid-template-columns: 1.1fr 0.9fr; }
}

@media (min-width: 1100px) {
  .erp-module-grid { grid-template-columns: repeat(5, 1fr); }
}
</style>
