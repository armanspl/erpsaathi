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
          <a v-if="showLoginCta" :href="primaryHref" class="erp-btn erp-btn-primary erp-header-cta">
            {{ primaryLabel }}
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
          </a>
          <a v-else :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-header-cta">
            Try Demo
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
          </a>
        </div>
      </div>
    </header>

    <main id="top">
      <!-- ============ Hero ============ -->
      <section class="erp-hero" id="overview">
        <div class="erp-hero-atmosphere" aria-hidden="true">
          <div
            v-for="(mod, i) in modules"
            :key="'bg-' + mod.key"
            class="erp-hero-atmosphere-layer"
            :class="{ 'is-active': i === slideIndex }"
            :style="{ backgroundImage: `url(${mod.image})` }"
          ></div>
          <span class="erp-hero-atmosphere-veil"></span>
        </div>

        <div class="erp-container erp-hero-stage">
          <div class="erp-hero-copy">
            <p class="erp-brand-hero">{{ schoolName }}</p>
            <h1 class="erp-hero-title">One system for every school department</h1>
            <p class="erp-hero-sub">
              From UDISE+ compliance to fees, exams and daily operations — everything runs from one secure workspace.
            </p>
            <div class="erp-hero-actions">
              <a v-if="showLoginCta" :href="primaryHref" class="erp-btn erp-btn-primary erp-btn-lg">
                {{ primaryLabel }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </a>
              <a v-else :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-lg">
                Try Demo
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </a>
              <a href="#modules" class="erp-btn erp-btn-ghost erp-btn-lg">Explore modules</a>
            </div>
            <div class="erp-hero-meta">
              <span>{{ slideIndex + 1 }} / {{ modules.length }}</span>
              <span class="erp-hero-meta-dot" aria-hidden="true"></span>
              <span>{{ activeModule.title }}</span>
            </div>
          </div>

          <div class="erp-hero-frame" @mouseenter="pauseSlider" @mouseleave="resumeSlider">
            <div class="erp-hero-frame-bar">
              <span></span><span></span><span></span>
              <p>{{ activeModule.emoji }} {{ activeModule.title }}</p>
            </div>
            <div class="erp-hero-frame-viewport">
              <img
                v-for="(mod, i) in modules"
                :key="'frame-' + mod.key"
                :src="mod.image"
                :alt="mod.title"
                class="erp-hero-frame-img"
                :class="{ 'is-active': i === slideIndex }"
                loading="eager"
                decoding="async"
              />
            </div>
            <p class="erp-hero-frame-caption">{{ activeModule.desc }}</p>
          </div>
        </div>

        <div class="erp-hero-rail" @mouseenter="pauseSlider" @mouseleave="resumeSlider">
          <div class="erp-container">
            <div class="erp-hero-rail-head">
              <p>Module gallery</p>
              <div class="erp-hero-rail-nav">
                <button type="button" class="erp-hero-nav-btn" aria-label="Previous module" @click="prevSlide">‹</button>
                <button type="button" class="erp-hero-nav-btn" aria-label="Next module" @click="nextSlide">›</button>
              </div>
            </div>
            <div ref="railInner" class="erp-hero-rail-inner">
              <button
                v-for="(mod, i) in modules"
                :key="mod.key"
                :ref="(el) => setThumbRef(el, i)"
                type="button"
                class="erp-hero-thumb"
                :class="{ 'is-active': i === slideIndex, 'is-featured': mod.featured }"
                :aria-pressed="i === slideIndex"
                @click="goSlide(i)"
              >
                <img :src="mod.image" alt="" loading="lazy" decoding="async" />
                <span class="erp-hero-thumb-label">
                  <i v-if="mod.featured">Featured</i>
                  {{ mod.title }}
                </span>
              </button>
            </div>
            <div class="erp-hero-progress" aria-hidden="true">
              <span :style="{ width: progressWidth }"></span>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ Introduction ============ -->
      <section class="erp-section erp-intro">
        <div class="erp-container erp-intro-layout">
          <div class="erp-intro-copy">
            <p class="erp-eyebrow">Why schools choose erpsaathi</p>
            <h2 class="erp-section-title erp-section-title-left">One platform for the whole institution</h2>
            <p class="erp-section-lead erp-section-lead-left">
              Replace scattered spreadsheets with a shared, role-based workspace. Administrators,
              teachers and accounts staff work from the same accurate records — with a complete audit trail.
            </p>
          </div>
          <div class="erp-pillars">
            <article class="erp-pillar" v-for="(pillar, idx) in pillars" :key="pillar.title">
              <span class="erp-pillar-index">0{{ idx + 1 }}</span>
              <div class="erp-pillar-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                  <path v-for="(d, i) in pillar.icon" :key="i" :d="d" />
                </svg>
              </div>
              <h3>{{ pillar.title }}</h3>
              <p>{{ pillar.desc }}</p>
            </article>
          </div>
        </div>
      </section>

      <!-- ============ Modules ============ -->
      <section class="erp-section erp-modules" id="modules">
        <div class="erp-container">
          <div class="erp-section-head erp-section-head-modules">
            <div>
              <p class="erp-eyebrow">Capabilities</p>
              <h2 class="erp-section-title erp-section-title-left">Everything a school needs to operate</h2>
            </div>
          </div>

          <div class="erp-module-featured">
            <article
              v-for="mod in featuredModules"
              :key="'feat-' + mod.key"
              class="erp-module erp-module-lg"
            >
              <div class="erp-module-media">
                <img :src="mod.image" :alt="mod.title" loading="lazy" decoding="async" />
              </div>
              <div class="erp-module-body">
                <p class="erp-module-kicker">{{ mod.emoji }} Compliance</p>
                <h3>{{ mod.title }}</h3>
                <p>{{ mod.desc }}</p>
              </div>
            </article>
          </div>

          <div class="erp-module-grid">
            <article class="erp-module" v-for="mod in standardModules" :key="mod.key">
              <div class="erp-module-media">
                <img :src="mod.image" :alt="mod.title" loading="lazy" decoding="async" />
              </div>
              <div class="erp-module-body">
                <h3><span aria-hidden="true">{{ mod.emoji }}</span> {{ mod.title }}</h3>
                <p>{{ mod.desc }}</p>
              </div>
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
            <h2 class="erp-cta-title">{{ showLoginCta ? (authenticated ? 'Your workspace is ready' : 'Sign in to your ERP workspace') : 'Built for modern schools' }}</h2>
            <p class="erp-cta-sub">
              <template v-if="showLoginCta">
                {{ authenticated
                  ? 'Continue where you left off in the dashboard.'
                  : 'Access is available to authorized school staff. Use your ERP credentials to continue.' }}
              </template>
              <template v-else>
                Explore the modules to see how academics, fees, exams and operations work together.
                Staff sign in through their school subdomain.
              </template>
            </p>
          </div>
          <a v-if="showLoginCta" :href="primaryHref" class="erp-btn erp-btn-primary erp-btn-lg">
            {{ primaryLabel }}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
          </a>
          <a v-else :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-lg">
            Try Demo
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
          <h4>Access</h4>
          <template v-if="showLoginCta">
            <a :href="primaryHref" class="erp-footer-login">{{ primaryLabel }}</a>
            <p class="erp-footer-fineprint">For authorized staff only.</p>
          </template>
          <template v-else>
            <a :href="tryDemoUrl" class="erp-footer-login">Try Demo</a>
            <a href="#overview">Overview</a>
            <a href="#modules">Modules</a>
            <a href="#security">Security</a>
            <p class="erp-footer-fineprint">Staff sign in via their school subdomain.</p>
          </template>
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
  showLoginCta: false,
  authenticated: false,
  loginUrl: '/erp/login',
  dashboardUrl: '/erp/dashboard',
  tryDemoUrl: '/erp/demo',
  schoolName: 'erpsaathi',
  logoUrl: '/assets/img/logo/erpsaathi.png',
};

export default {
  name: 'Welcome',
  data() {
    const cfg = { ...DEFAULTS, ...(typeof window !== 'undefined' && window.__WELCOME__ ? window.__WELCOME__ : {}) };
    return {
      showLoginCta: !!cfg.showLoginCta,
      authenticated: !!cfg.authenticated,
      loginUrl: cfg.loginUrl || DEFAULTS.loginUrl,
      dashboardUrl: cfg.dashboardUrl || DEFAULTS.dashboardUrl,
      tryDemoUrl: cfg.tryDemoUrl || DEFAULTS.tryDemoUrl,
      schoolName: cfg.schoolName || DEFAULTS.schoolName,
      logoUrl: cfg.logoUrl || DEFAULTS.logoUrl,
      isDark: false,
      headerHidden: false,
      lastScrollY: 0,
      scrollTicking: false,
      year: new Date().getFullYear(),
      slideIndex: 0,
      sliderPaused: false,
      sliderTimer: null,
      slideMs: 3500,
      thumbRefs: [],
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
          key: 'udise-s02',
          title: 'UDISE+ S02',
          emoji: '📋',
          featured: true,
          desc: 'Prepare Form S02 for students not yet on UDISE — select and print in one pass.',
          image: '/assets/img/modules/module-udise-s02.jpg',
        },
        {
          key: 'udise-s03',
          title: 'UDISE+ S03',
          emoji: '🗂️',
          featured: true,
          desc: 'Maintain UDISE+ S03 student registry, PEN and enrolment status for portal sync.',
          image: '/assets/img/modules/module-udise-s03.jpg',
        },
        {
          key: 'import-export',
          title: 'Import & Export',
          emoji: '📥',
          desc: 'Global workbook, student PEN, attendance and exam marks — import and export without leaving the ERP.',
          image: '/assets/img/modules/module-import-export.jpg',
        },
        {
          key: 'academics',
          title: 'Academics',
          emoji: '🎓',
          desc: 'Branches, sessions, classes, sections, subjects and homework in one structured hierarchy.',
          image: '/assets/img/modules/module-academics.jpg',
        },
        {
          key: 'admissions',
          title: 'Admissions',
          emoji: '📝',
          desc: 'Enquiry to registration to admission — one pipeline with shared student records.',
          image: '/assets/img/modules/module-admissions.jpg',
        },
        {
          key: 'people',
          title: 'People',
          emoji: '👥',
          desc: 'Students, parents, teachers, staff and drivers with complete profiles and history.',
          image: '/assets/img/modules/module-people.jpg',
        },
        {
          key: 'attendance',
          title: 'Attendance',
          emoji: '📅',
          desc: 'Daily attendance for students and staff, leave management and monthly summaries.',
          image: '/assets/img/modules/module-attendance.jpg',
        },
        {
          key: 'fees',
          title: 'Fee Management',
          emoji: '💰',
          desc: 'Structures, dues, collection, receipts and accounting-ready fee history.',
          image: '/assets/img/modules/module-fees.jpg',
        },
        {
          key: 'finance',
          title: 'Finance & Payroll',
          emoji: '🏦',
          desc: 'Office expenses, salary slips, bank accounts and day-to-day cash control.',
          image: '/assets/img/modules/module-finance.jpg',
        },
        {
          key: 'transport',
          title: 'Transport Management',
          emoji: '🚌',
          desc: 'Routes, stops, vehicles and drivers linked directly to student transport cards.',
          image: '/assets/img/modules/module-transport.jpg',
        },
        {
          key: 'exams',
          title: 'Exam Management',
          emoji: '📚',
          desc: 'Schedules, seat plans, marks, results, admit cards and annual report cards.',
          image: '/assets/img/modules/module-exams.jpg',
        },
        {
          key: 'reports',
          title: 'Reports',
          emoji: '📈',
          desc: 'Class-wise, area-wise and finance reports with controlled Excel and PDF export.',
          image: '/assets/img/modules/module-reports.jpg',
        },
        {
          key: 'documents',
          title: 'Documents',
          emoji: '📄',
          desc: 'ID cards, certificates, transport cards and template builder in one place.',
          image: '/assets/img/modules/module-documents.jpg',
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
  computed: {
    primaryHref() {
      return this.authenticated ? this.dashboardUrl : this.loginUrl;
    },
    primaryLabel() {
      return this.authenticated ? 'Go to Dashboard' : 'Login to ERP';
    },
    activeModule() {
      return this.modules[this.slideIndex] || this.modules[0];
    },
    progressWidth() {
      const n = this.modules.length || 1;
      return `${((this.slideIndex + 1) / n) * 100}%`;
    },
    featuredModules() {
      return this.modules.filter((m) => m.featured);
    },
    standardModules() {
      return this.modules.filter((m) => !m.featured);
    },
  },
  watch: {
    isDark(value) {
      this.applyTheme(value);
    },
    slideIndex() {
      this.$nextTick(() => this.scrollActiveThumb());
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
    this.startSlider();
    this.$nextTick(() => this.scrollActiveThumb());
  },
  beforeUnmount() {
    const root = document.documentElement;
    root.style.removeProperty('background-color');
    window.removeEventListener('scroll', this.onScroll);
    this.stopSlider();
  },
  methods: {
    setThumbRef(el, index) {
      if (el) this.thumbRefs[index] = el;
    },
    scrollActiveThumb() {
      const rail = this.$refs.railInner;
      const thumb = this.thumbRefs[this.slideIndex];
      if (!rail || !thumb) return;
      const left = thumb.offsetLeft - (rail.clientWidth - thumb.offsetWidth) / 2;
      rail.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
    },
    startSlider() {
      this.stopSlider();
      const reduce = typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce) return;
      this.sliderTimer = window.setInterval(() => {
        if (this.sliderPaused) return;
        this.slideIndex = (this.slideIndex + 1) % this.modules.length;
      }, this.slideMs);
    },
    stopSlider() {
      if (this.sliderTimer) {
        window.clearInterval(this.sliderTimer);
        this.sliderTimer = null;
      }
    },
    pauseSlider() {
      this.sliderPaused = true;
    },
    resumeSlider() {
      this.sliderPaused = false;
    },
    goSlide(index) {
      this.slideIndex = index;
      this.startSlider();
    },
    nextSlide() {
      this.goSlide((this.slideIndex + 1) % this.modules.length);
    },
    prevSlide() {
      const n = this.modules.length;
      this.goSlide((this.slideIndex - 1 + n) % n);
    },
    onScroll() {
      if (this.scrollTicking) return;
      this.scrollTicking = true;
      window.requestAnimationFrame(() => {
        const y = window.scrollY || 0;
        const delta = y - this.lastScrollY;
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

/* ---------- Hero (product stage + thumbnail rail) ---------- */
.erp-hero {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 0;
  scroll-margin-top: 76px;
  overflow: hidden;
  isolation: isolate;
  border-bottom: 1px solid var(--border-soft);
}
.erp-hero-atmosphere {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}
.erp-hero-atmosphere-layer {
  position: absolute;
  inset: -24px;
  background-size: cover;
  background-position: center;
  filter: blur(28px) saturate(1.05);
  opacity: 0;
  transform: scale(1.08);
  transition: opacity 0.8s ease;
}
.erp-hero-atmosphere-layer.is-active { opacity: 0.55; }
.erp-hero-atmosphere-veil {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(7, 8, 12, 0.72) 0%, rgba(7, 8, 12, 0.55) 45%, rgba(7, 8, 12, 0.92) 100%),
    radial-gradient(circle at 78% 30%, rgba(198, 167, 94, 0.16), transparent 42%);
}
.erp-site:not(.dark) .erp-hero-atmosphere-veil {
  background:
    linear-gradient(180deg, rgba(248, 250, 252, 0.78) 0%, rgba(248, 250, 252, 0.62) 42%, rgba(241, 245, 249, 0.96) 100%),
    radial-gradient(circle at 78% 30%, rgba(99, 102, 241, 0.14), transparent 42%);
}
.erp-hero-stage {
  position: relative;
  z-index: 2;
  width: 100%;
  display: grid;
  grid-template-columns: 1fr;
  gap: 28px;
  padding: 96px 24px 28px;
  align-items: center;
}
.erp-brand-hero {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(2.8rem, 7vw, 4.4rem);
  font-weight: 700;
  line-height: 0.92;
  letter-spacing: -0.02em;
  margin: 0 0 14px;
  color: var(--text);
}
.erp-hero-title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(1.45rem, 3.2vw, 2rem);
  font-weight: 600;
  line-height: 1.25;
  margin: 0 0 14px;
  color: var(--text);
  max-width: 16em;
}
.erp-hero-sub {
  font-size: clamp(0.98rem, 2.1vw, 1.08rem);
  line-height: 1.65;
  color: var(--text-muted);
  max-width: 34em;
  margin: 0 0 24px;
}
.erp-hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 18px;
}
.erp-hero-meta {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  font-weight: 650;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.erp-hero-meta-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--gold);
}
.erp-hero-frame {
  border-radius: 18px;
  border: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface-solid) 92%, transparent);
  box-shadow: 0 28px 70px rgba(0, 0, 0, 0.35);
  overflow: hidden;
  animation: erpFeatureIn 0.45s ease;
}
.erp-site:not(.dark) .erp-hero-frame {
  box-shadow: 0 24px 54px rgba(15, 23, 42, 0.14);
}
.erp-hero-frame-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-soft);
  background: color-mix(in srgb, var(--surface) 80%, transparent);
}
.erp-hero-frame-bar span {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--gold) 55%, transparent);
}
.erp-hero-frame-bar p {
  margin: 0 0 0 8px;
  font-size: 12.5px;
  font-weight: 650;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.erp-hero-frame-viewport {
  position: relative;
  aspect-ratio: 16 / 10;
  background: #0b1220;
  overflow: hidden;
}
.erp-hero-frame-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transform: scale(1.03);
  transition: opacity 0.55s ease, transform 0.9s ease;
}
.erp-hero-frame-img.is-active {
  opacity: 1;
  transform: scale(1);
  z-index: 1;
}
.erp-hero-frame-caption {
  margin: 0;
  padding: 14px 16px 16px;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--text-muted);
  border-top: 1px solid var(--border-soft);
}
@keyframes erpFeatureIn {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.erp-hero-rail {
  position: relative;
  z-index: 2;
  border-top: 1px solid var(--border-soft);
  background: color-mix(in srgb, var(--ink) 72%, transparent);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  padding: 16px 0 0;
}
.erp-hero-rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
  padding: 0 2px;
}
.erp-hero-rail-head p {
  margin: 0;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.erp-hero-rail-nav { display: flex; gap: 8px; }
.erp-hero-nav-btn {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text);
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease;
}
.erp-hero-nav-btn:hover { border-color: var(--gold); }
.erp-hero-rail-inner {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(148px, 1fr);
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 14px;
  scroll-behavior: smooth;
  scrollbar-width: thin;
}
.erp-hero-thumb {
  position: relative;
  border: 1px solid var(--border-soft);
  border-radius: 14px;
  overflow: hidden;
  padding: 0;
  background: #0b1220;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}
.erp-hero-thumb img {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 10;
  object-fit: cover;
  opacity: 0.78;
  transition: opacity 0.2s ease, transform 0.35s ease;
}
.erp-hero-thumb-label {
  display: block;
  padding: 8px 10px 10px;
  font-size: 12px;
  font-weight: 650;
  color: var(--text);
  background: var(--surface-solid);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.erp-hero-thumb-label i {
  display: inline-block;
  margin-right: 6px;
  font-style: normal;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gold);
}
.erp-hero-thumb:hover {
  border-color: var(--border);
  transform: translateY(-2px);
}
.erp-hero-thumb:hover img { opacity: 1; transform: scale(1.03); }
.erp-hero-thumb.is-active {
  border-color: var(--gold);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--gold) 45%, transparent);
}
.erp-hero-thumb.is-active img { opacity: 1; }
.erp-hero-thumb.is-featured .erp-hero-thumb-label {
  background: linear-gradient(180deg, color-mix(in srgb, var(--gold) 12%, var(--surface-solid)), var(--surface-solid));
}
.erp-hero-progress {
  height: 3px;
  background: var(--border-soft);
}
.erp-hero-progress span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--gold-soft), var(--gold));
  transition: width 0.45s ease;
}
@media (prefers-reduced-motion: reduce) {
  .erp-hero-atmosphere-layer,
  .erp-hero-frame,
  .erp-hero-frame-img,
  .erp-hero-thumb,
  .erp-hero-thumb img,
  .erp-hero-progress span { transition: none; animation: none; }
}

/* ---------- Sections ---------- */
.erp-section { padding: 64px 0; scroll-margin-top: 76px; }
.erp-section-head { max-width: 44em; margin: 0 auto 36px; text-align: center; }
.erp-section-head-modules {
  max-width: none;
  margin: 0 0 34px;
  text-align: left;
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
  align-items: end;
}
.erp-section-title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(1.8rem, 4.4vw, 2.6rem);
  font-weight: 700;
  line-height: 1.18;
  margin: 0 0 14px;
}
.erp-section-title-left { margin-bottom: 10px; }
.erp-section-lead {
  font-size: 1.02rem;
  line-height: 1.7;
  color: var(--text-muted);
  margin: 0;
}
.erp-section-lead-left { max-width: 36em; }
.erp-section-lead-narrow { max-width: 28em; }

/* Intro */
.erp-intro {
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--gold) 6%, transparent), transparent 48%);
}
.erp-intro-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 28px;
  align-items: start;
}
.erp-intro-copy { max-width: 38em; }
.erp-pillars {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
.erp-pillar {
  position: relative;
  border: 1px solid var(--border-soft);
  border-radius: 18px;
  padding: 24px 22px 22px;
  background: var(--surface);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  overflow: hidden;
  transition: border-color 0.2s ease, transform 0.2s ease;
}
.erp-pillar:hover {
  border-color: var(--border);
  transform: translateY(-2px);
}
.erp-pillar-index {
  position: absolute;
  top: 14px;
  right: 16px;
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: color-mix(in srgb, var(--gold) 45%, transparent);
  line-height: 1;
}
.erp-pillar-icon {
  width: 46px;
  height: 46px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  color: var(--gold);
  background: color-mix(in srgb, var(--gold) 12%, transparent);
  border: 1px solid var(--border);
}
.erp-pillar-icon svg { width: 22px; height: 22px; }
.erp-pillar h3 {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 1.35rem;
  font-weight: 700;
  margin: 0 0 8px;
}
.erp-pillar p {
  font-size: 0.94rem;
  line-height: 1.65;
  color: var(--text-muted);
  margin: 0;
}

/* Modules */
.erp-module-featured {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  margin-bottom: 16px;
}
.erp-module-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
.erp-module {
  border: 1px solid var(--border-soft);
  border-radius: 18px;
  overflow: hidden;
  background: var(--surface);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.erp-module:hover {
  transform: translateY(-4px);
  border-color: var(--border);
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.16);
}
.erp-module-lg {
  display: grid;
  grid-template-columns: 1fr;
}
.erp-module-media {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: #0f172a;
}
.erp-module-lg .erp-module-media { aspect-ratio: 16 / 9; }
.erp-module-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.45s ease;
}
.erp-module:hover .erp-module-media img { transform: scale(1.04); }
.erp-module-body { padding: 18px 18px 20px; }
.erp-module-kicker {
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--gold);
}
.erp-module-body h3 {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 1.28rem;
  font-weight: 700;
  margin: 0 0 8px;
}
.erp-module-body p {
  font-size: 0.92rem;
  line-height: 1.65;
  color: var(--text-muted);
  margin: 0;
}
@media (prefers-reduced-motion: reduce) {
  .erp-module:hover,
  .erp-pillar:hover,
  .erp-module:hover .erp-module-media img { transform: none; }
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
  .erp-module-featured { grid-template-columns: repeat(2, 1fr); }
  .erp-module-grid { grid-template-columns: repeat(2, 1fr); }
  .erp-cta-inner { flex-direction: row; align-items: center; justify-content: space-between; }
  .erp-cta-inner > a { flex-shrink: 0; }
  .erp-footer-bottom { flex-direction: row; justify-content: space-between; }
  .erp-footer-grid { grid-template-columns: 2fr 1fr 1fr 1fr; }
}

@media (min-width: 900px) {
  .erp-nav { display: flex; }
  .erp-header-cta { padding: 10px 18px; font-size: 14px; }
  .erp-hero-stage {
    grid-template-columns: 0.95fr 1.05fr;
    gap: 40px;
    padding: 108px 24px 36px;
  }
  .erp-intro-layout {
    grid-template-columns: 0.9fr 1.1fr;
    gap: 40px;
    align-items: center;
  }
  .erp-section-head-modules {
    grid-template-columns: 1.1fr 0.9fr;
    gap: 28px;
  }
  .erp-module-lg {
    grid-template-columns: 1.15fr 0.85fr;
    align-items: stretch;
  }
  .erp-module-lg .erp-module-media {
    aspect-ratio: auto;
    min-height: 100%;
  }
  .erp-module-lg .erp-module-body {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 28px;
  }
  .erp-module-grid { grid-template-columns: repeat(3, 1fr); }
  .erp-security-grid { grid-template-columns: 1.1fr 0.9fr; }
}

@media (min-width: 1100px) {
  .erp-module-grid { grid-template-columns: repeat(3, 1fr); }
  .erp-hero-rail-inner { grid-auto-columns: minmax(160px, 1fr); }
}
</style>
