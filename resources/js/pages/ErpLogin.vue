<template>
  <div class="erp-root" :class="{ dark: isDark, 'lang-hi': locale === 'hi' }" :lang="locale">
    <a class="erp-skip-link" href="#erp-login-form">{{ t.skipLink }}</a>

    <!-- Soft backdrop (matches homepage: calm blobs + dot-grid, no drifting icons) -->
    <div class="erp-mesh" aria-hidden="true">
      <span class="erp-blob erp-blob-a"></span>
      <span class="erp-blob erp-blob-b"></span>
      <span class="erp-dot-grid"></span>
    </div>

    <!-- Utility bar: language + theme -->
    <div class="erp-utility-bar">
      <div class="erp-lang-switch" ref="langSwitch">
        <button type="button" class="erp-theme-toggle" @click="langMenuOpen = !langMenuOpen" :aria-expanded="langMenuOpen" aria-label="Change language" title="English / हिंदी">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" /><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z" />
          </svg>
        </button>
        <Transition name="erp-fade">
          <div v-if="langMenuOpen" class="erp-lang-menu" role="menu">
            <button type="button" role="menuitem" :class="{ 'is-active': locale === 'en' }" @click="setLocale('en')">English</button>
            <button type="button" role="menuitem" :class="{ 'is-active': locale === 'hi' }" @click="setLocale('hi')">हिंदी</button>
          </div>
        </Transition>
      </div>
      <button type="button" class="erp-theme-toggle" @click="toggleTheme" :aria-pressed="isDark" aria-label="Toggle dark mode" title="Toggle dark / light mode">
        <svg v-if="!isDark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
        </svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
        </svg>
      </button>
    </div>

    <!-- Left brand panel -->
    <div class="erp-left">
      <div class="erp-left-orbit" aria-hidden="true">
        <span class="erp-float-icon erp-fi-1">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </span>
        <span class="erp-float-icon erp-fi-2">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
        </span>
        <span class="erp-float-icon erp-fi-3">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </span>
        <span class="erp-float-icon erp-fi-4">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16V9M12 16v-5M17 16V7"/></svg>
        </span>
      </div>
      <div class="erp-left-inner">
        <div class="erp-logo-ring erp-anim" style="--d:0s">
          <img src="/assets/img/logo/new-logo2.png?v=1" alt="ERPSaathi logo" class="erp-logo" />
        </div>
        <h1 class="erp-brand-title erp-anim" style="--d:.08s">{{ t.brandTitle }}</h1>
        <span class="erp-gold-rule erp-anim" style="--d:.12s" aria-hidden="true"></span>
        <p class="erp-brand-sub erp-anim" style="--d:.16s">{{ t.brandSub }}</p>
        <ul class="erp-feature-list">
          <li class="erp-feature erp-anim" style="--d:.24s">
            <span class="erp-feature-icon erp-icon-pulse">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span>{{ t.feature1 }}</span>
          </li>
          <li class="erp-feature erp-anim" style="--d:.32s">
            <span class="erp-feature-icon erp-icon-pulse" style="--p:.4s">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
            </span>
            <span>{{ t.feature2 }}</span>
          </li>
          <li class="erp-feature erp-anim" style="--d:.4s">
            <span class="erp-feature-icon erp-icon-pulse" style="--p:.8s">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <span>{{ t.feature3 }}</span>
          </li>
          <li class="erp-feature erp-anim" style="--d:.48s">
            <span class="erp-feature-icon erp-icon-pulse" style="--p:1.2s">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16V9M12 16v-5M17 16V7"/></svg>
            </span>
            <span>{{ t.feature4 }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Right form panel -->
    <div class="erp-right">
      <div class="erp-form-card erp-anim" style="--d:.1s">
        <div class="erp-mobile-header">
          <img :src="mobileLogoUrl" alt="ERPSaathi logo" class="erp-mobile-logo" />
          <h2 class="erp-mobile-title">{{ t.brandTitle }}</h2>
        </div>
        <h2 class="erp-title">{{ t.welcomeBack }}</h2>
        <p class="erp-subtitle">{{ t.signInSub }}</p>

        <form id="erp-login-form" @submit.prevent="handleLogin" novalidate :aria-busy="loading">
          <div class="erp-field">
            <label class="erp-label" for="erp-email">{{ t.emailLabel }}</label>
            <div class="erp-input-wrap" :class="{ 'is-focused': focusedField === 'email' }">
              <span class="erp-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 6-10 7L2 6" /></svg>
              </span>
              <input id="erp-email" ref="emailInput" type="email" required class="erp-input" placeholder="you@example.com" v-model="email" autocomplete="username" @focus="focusedField = 'email'" @blur="focusedField = null" />
            </div>
          </div>
          <div class="erp-field">
            <label class="erp-label" for="erp-password">{{ t.passwordLabel }}</label>
            <div class="erp-input-wrap" :class="{ 'is-focused': focusedField === 'password' }">
              <span class="erp-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
              </span>
              <input id="erp-password" ref="passwordInput" :type="showPassword ? 'text' : 'password'" required class="erp-input erp-input-password" :placeholder="t.passwordPlaceholder" v-model="password" autocomplete="current-password" @focus="focusedField = 'password'" @blur="focusedField = null" />
              <button type="button" class="erp-toggle-visibility" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" /><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" /><line x1="2" x2="22" y1="2" y2="22" /></svg>
              </button>
            </div>
          </div>
          <div class="erp-row">
            <label class="erp-remember">
              <input type="checkbox" class="erp-checkbox" v-model="remember" />
              <span class="erp-remember-text">{{ t.rememberMe }}</span>
            </label>
            <a :href="forgotPasswordUrl" class="erp-forgot">{{ t.forgotPassword }}</a>
          </div>

          <transition name="erp-fade">
            <div v-if="error" class="erp-error" role="alert" aria-live="assertive">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
              <span>{{ error }}</span>
            </div>
          </transition>

          <button type="submit" class="erp-submit" :disabled="loading">
            <svg v-if="loading" class="erp-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
            <span>{{ loading ? t.signingIn : t.signIn }}</span>
          </button>

          <p class="erp-shortcut-hint">
            {{ t.shortcutHintPrefix }} <kbd>/</kbd> {{ t.shortcutHintMiddle }} ·
            <button type="button" class="erp-shortcut-link" @click="showShortcuts = true">{{ t.keyboardShortcuts }}</button>
          </p>
        </form>
      </div>
    </div>

    <!-- Shortcuts modal -->
    <transition name="erp-fade">
      <div v-if="showShortcuts" class="erp-modal-backdrop" @click.self="showShortcuts = false">
        <div class="erp-modal" role="dialog" aria-modal="true" aria-labelledby="erp-shortcuts-title">
          <div class="erp-modal-head">
            <h3 id="erp-shortcuts-title">{{ t.keyboardShortcuts }}</h3>
            <button ref="shortcutsClose" type="button" class="erp-modal-close" @click="showShortcuts = false" aria-label="Close keyboard shortcuts">&times;</button>
          </div>
          <ul class="erp-shortcut-list">
            <li><kbd>/</kbd><span>{{ t.shortcutEmail }}</span></li>
            <li><kbd>Alt</kbd>+<kbd>P</kbd><span>{{ t.shortcutPassword }}</span></li>
            <li><kbd>Enter</kbd><span>{{ t.shortcutSubmit }}</span></li>
            <li><kbd>Esc</kbd><span>{{ t.shortcutClose }}</span></li>
          </ul>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
const TRANSLATIONS = {
  en: {
    skipLink: 'Skip to sign in form',
    brandTitle: 'School ERP',
    brandSub: 'Manage students, attendance, fees and reports — all in one place.',
    feature1: 'Student & staff management',
    feature2: 'Attendance tracking',
    feature3: 'Fee & payment management',
    feature4: 'Reports & analytics',
    welcomeBack: 'Welcome back',
    signInSub: 'Sign in to access the ERP system.',
    emailLabel: 'Email address',
    passwordLabel: 'Password',
    passwordPlaceholder: 'Enter your password',
    rememberMe: 'Remember me',
    forgotPassword: 'Forgot password?',
    signIn: 'Sign In',
    signingIn: 'Signing in...',
    shortcutHintPrefix: 'Press',
    shortcutHintMiddle: 'to focus email',
    keyboardShortcuts: 'Keyboard shortcuts',
    shortcutEmail: 'Focus the email field',
    shortcutPassword: 'Focus the password field',
    shortcutSubmit: 'Submit the sign-in form',
    shortcutClose: 'Close this dialog',
    errFillBoth: 'Please enter your email and password.',
    errNetwork: 'A network error occurred. Please try again.',
    errInvalid: 'Invalid email or password. Please try again.',
  },
  hi: {
    skipLink: 'साइन इन फ़ॉर्म पर जाएं',
    brandTitle: 'स्कूल ERP',
    brandSub: 'छात्र, उपस्थिति, फीस और रिपोर्ट — सब कुछ एक ही जगह प्रबंधित करें।',
    feature1: 'छात्र और स्टाफ़ प्रबंधन',
    feature2: 'उपस्थिति ट्रैकिंग',
    feature3: 'फीस और भुगतान प्रबंधन',
    feature4: 'रिपोर्ट और विश्लेषण',
    welcomeBack: 'वापसी पर स्वागत है',
    signInSub: 'ERP सिस्टम तक पहुँचने के लिए साइन इन करें।',
    emailLabel: 'ईमेल पता',
    passwordLabel: 'पासवर्ड',
    passwordPlaceholder: 'अपना पासवर्ड दर्ज करें',
    rememberMe: 'मुझे याद रखें',
    forgotPassword: 'पासवर्ड भूल गए?',
    signIn: 'साइन इन करें',
    signingIn: 'साइन इन हो रहा है...',
    shortcutHintPrefix: 'ईमेल पर फ़ोकस के लिए',
    shortcutHintMiddle: 'दबाएं',
    keyboardShortcuts: 'कीबोर्ड शॉर्टकट',
    shortcutEmail: 'ईमेल फ़ील्ड पर फ़ोकस करें',
    shortcutPassword: 'पासवर्ड फ़ील्ड पर फ़ोकस करें',
    shortcutSubmit: 'साइन-इन फ़ॉर्म सबमिट करें',
    shortcutClose: 'यह डायलॉग बंद करें',
    errFillBoth: 'कृपया अपना ईमेल और पासवर्ड दर्ज करें।',
    errNetwork: 'नेटवर्क त्रुटि हुई। कृपया पुनः प्रयास करें।',
    errInvalid: 'अमान्य ईमेल या पासवर्ड। कृपया पुनः प्रयास करें।',
  },
};

export default {
  name: "ErpLogin",
  data() {
    return {
      email: "",
      password: "",
      remember: false,
      showPassword: false,
      loading: false,
      error: null,
      focusedField: null,
      isDark: false,
      showShortcuts: false,
      locale: 'en',
      langMenuOpen: false,
    };
  },
  computed: {
    t() {
      return TRANSLATIONS[this.locale] || TRANSLATIONS.en;
    },
    schoolSlug() {
      return new URLSearchParams(window.location.search).get('school') || '';
    },
    forgotPasswordUrl() {
      return this.schoolSlug
        ? `/erp/forgot-password?school=${encodeURIComponent(this.schoolSlug)}`
        : '/erp/forgot-password';
    },
    mobileLogoUrl() {
      return '/assets/img/logo/new-logo2.png?v=1';
    },
  },
  watch: {
    isDark(value) {
      this.applyTheme(value);
    },
    showShortcuts(open) {
      if (open) this.$nextTick(() => this.$refs.shortcutsClose && this.$refs.shortcutsClose.focus());
    },
  },
  mounted() {
    let saved = null;
    try {
      saved = localStorage.getItem("erp-auth-theme");
    } catch (e) {
      saved = null;
    }
    this.isDark = saved === null ? false : saved === "dark";
    this.applyTheme(this.isDark);
    const savedLang = localStorage.getItem("erp-welcome-lang");
    if (savedLang === "hi" || savedLang === "en") this.locale = savedLang;
    window.addEventListener("keydown", this.handleGlobalKeydown);
    document.addEventListener("click", this.onDocumentClick);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.handleGlobalKeydown);
    document.removeEventListener("click", this.onDocumentClick);
    document.documentElement.style.removeProperty('background-color');
    if (document.body) document.body.style.removeProperty('background-color');
  },
  methods: {
    setLocale(lang) {
      this.locale = lang;
      this.langMenuOpen = false;
      try {
        localStorage.setItem("erp-welcome-lang", lang);
      } catch (e) {
        /* ignore */
      }
    },
    onDocumentClick(event) {
      if (!this.langMenuOpen) return;
      const el = this.$refs.langSwitch;
      if (el && !el.contains(event.target)) this.langMenuOpen = false;
    },
    applyTheme(dark) {
      const bg = dark ? 'var(--brand-950)' : 'var(--rng-bg)';
      document.documentElement.style.backgroundColor = bg;
      if (document.body) document.body.style.backgroundColor = bg;
      document.documentElement.classList.toggle('erp-login-dark', !!dark);
      document.documentElement.classList.toggle('erp-login-light', !dark);
      if (document.body) {
        document.body.classList.toggle('erp-login-dark', !!dark);
        document.body.classList.toggle('erp-login-light', !dark);
      }
      try {
        localStorage.setItem("erp-auth-theme", dark ? "dark" : "light");
      } catch (e) {
        /* ignore */
      }
    },
    toggleTheme() {
      this.isDark = !this.isDark;
    },
    async handleLogin() {
      this.error = null;

      if (!this.email || !this.password) {
        this.error = this.t.errFillBoth;
        return;
      }

      this.loading = true;
      try {
        const school = new URLSearchParams(window.location.search).get('school') || '';
        const authUrl = school
          ? `/erp/authenticate?school=${encodeURIComponent(school)}`
          : '/erp/authenticate';
        const headers = {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        };
        if (school) {
          headers['X-Tenant'] = school;
        }

        const response = await fetch(authUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers,
          body: JSON.stringify({
            email: this.email,
            password: this.password,
            remember: this.remember,
            ...(school ? { school } : {}),
          }),
        });

        const data = await response.json();

        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          this.error = data.message || this.t.errInvalid;
        }
      } catch (err) {
        this.error = this.t.errNetwork;
      } finally {
        this.loading = false;
      }
    },
    focusEmail() { this.$refs.emailInput && this.$refs.emailInput.focus(); },
    focusPassword() { this.$refs.passwordInput && this.$refs.passwordInput.focus(); },
    handleGlobalKeydown(e) {
      const tag = (e.target && e.target.tagName ? e.target.tagName : "").toLowerCase();
      const isTyping = tag === "input" || tag === "textarea" || tag === "select";
      if (e.key === "Escape") { if (this.showShortcuts) this.showShortcuts = false; return; }
      if (e.key === "?" && !isTyping) { e.preventDefault(); this.showShortcuts = !this.showShortcuts; return; }
      if (e.key === "/" && !isTyping) { e.preventDefault(); this.focusEmail(); return; }
      if (e.altKey && (e.key === "d" || e.key === "D")) { e.preventDefault(); this.toggleTheme(); return; }
      if (e.altKey && (e.key === "p" || e.key === "P")) { e.preventDefault(); this.focusPassword(); }
    },
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Noto+Sans+Devanagari:wght@400;500;600&display=swap');

/* Royal Navy + Gold. Every colour comes from the shared tokens in
   resources/css/erp-theme-royal.css (loaded via app.css). */
.erp-root {
  --gold: var(--rng-primary);
  --gold-soft: var(--rng-primary-hover);
  --gold-strong: var(--rng-primary-hover);
  --card-bg: var(--rng-surface);
  --card-border: var(--rng-border);
  --card-text: var(--rng-text);
  --card-muted: var(--rng-text-muted);
  --input-bg: var(--rng-surface);
  --input-bg-solid: var(--rng-surface);
  --input-border: var(--rng-border);
  --error-bg: var(--rng-danger-bg);
  --error-line: var(--rng-danger-bg);
  --error-text: var(--rng-danger-text);
  --accent: var(--rng-primary);
  --accent-strong: var(--rng-primary-hover);
  position: relative;
  min-height: 100vh;
  display: flex;
  font-family: var(--rng-font);
  font-size: 15px;
  line-height: 1.5;
  color: var(--rng-text);
  background: var(--rng-bg);
  overflow: hidden;
}

/* Dark: the whole page goes navy; the card becomes a lighter navy surface. */
.erp-root.dark {
  --card-bg: var(--rng-primary-hover);
  --card-border: rgb(255 255 255 / 0.12);
  --card-text: var(--rng-text-on-dark);
  --card-muted: var(--rng-text-on-dark-muted);
  --input-bg: rgb(255 255 255 / 0.06);
  --input-bg-solid: rgb(255 255 255 / 0.08);
  --input-border: rgb(255 255 255 / 0.18);
  --error-bg: rgb(220 38 38 / 0.18);
  --error-line: rgb(220 38 38 / 0.4);
  --error-text: var(--rng-danger-on-dark);
  --accent: var(--rng-accent);
  --accent-strong: var(--rng-accent);
  color: var(--rng-text-on-dark);
  background: var(--rng-primary);
}

.erp-root.lang-hi { font-family: 'Noto Sans Devanagari', var(--rng-font); }
.erp-root.lang-hi .erp-brand-title,
.erp-root.lang-hi .erp-mobile-title,
.erp-root.lang-hi .erp-title,
.erp-root.lang-hi .erp-modal-head h3 { font-family: 'Noto Sans Devanagari', var(--rng-font); }

.erp-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
.erp-skip-link { position: absolute; top: -48px; left: 12px; z-index: 100; background: var(--card-bg); color: var(--card-text); padding: 10px 16px; border-radius: var(--rng-radius-control); font-size: 13px; font-weight: 600; text-decoration: none; transition: top .15s ease; border: 1px solid var(--card-border); }
.erp-skip-link:focus { top: 12px; }

/* Flat split layout — no decorative backdrop. */
.erp-mesh { display: none; }

.erp-anim { animation: erp-rise .5s cubic-bezier(0.16,1,0.3,1) both; animation-delay: var(--d,0s); }
@keyframes erp-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion:reduce) { .erp-anim { animation: none; opacity: 1; transform: none; } }

.erp-utility-bar {
  position: absolute; top: 18px; right: 18px; z-index: 20;
  display: flex; align-items: center; gap: 6px;
  background: var(--rng-surface);
  border: 1px solid var(--rng-border);
  border-radius: 999px; padding: 4px;
  box-shadow: var(--rng-shadow);
}
.erp-root.dark .erp-utility-bar { background: var(--rng-primary-hover); border-color: rgb(255 255 255 / 0.12); }
.erp-theme-toggle {
  width: 30px; height: 30px; border-radius: 50%; border: none;
  background: transparent; color: var(--rng-primary);
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: background .15s ease;
}
.erp-root.dark .erp-theme-toggle { color: var(--rng-text-on-dark); }
.erp-theme-toggle:hover { background: var(--rng-primary-light); }
.erp-root.dark .erp-theme-toggle:hover { background: rgb(255 255 255 / 0.1); }
.erp-theme-toggle:focus-visible { outline: 2px solid var(--rng-primary); outline-offset: 2px; }

.erp-lang-switch { position: relative; }
.erp-lang-menu {
  position: absolute; top: calc(100% + 8px); right: 0; min-width: 128px;
  border: 1px solid var(--card-border); border-radius: var(--rng-radius-control);
  background: var(--card-bg);
  box-shadow: var(--rng-shadow);
  padding: 4px; display: flex; flex-direction: column; gap: 2px; z-index: 30;
}
.erp-lang-menu button {
  text-align: left; padding: 8px 10px; border-radius: 6px; border: 0;
  background: transparent; font-family: inherit; font-size: 13px; font-weight: 500;
  color: var(--card-muted); cursor: pointer;
}
.erp-lang-menu button:hover { background: var(--rng-row-hover); color: var(--card-text); }
.erp-lang-menu button.is-active { background: var(--rng-primary-light); color: var(--rng-primary); font-weight: 600; }
.erp-root.dark .erp-lang-menu button:hover { background: rgb(255 255 255 / 0.08); }
.erp-root.dark .erp-lang-menu button.is-active { background: rgb(255 255 255 / 0.12); color: var(--rng-text-on-dark); }

/* ── Left: navy brand panel ── */
.erp-left {
  position: relative; z-index: 1; display: none; width: 50%;
  align-items: center; justify-content: center; padding: 56px;
  background: var(--brand-950, var(--rng-navy-deep));
  color: var(--rng-text-on-dark);
  overflow: hidden;
}
@media (min-width:1024px) { .erp-left { display: flex; } }
.erp-left-inner { position: relative; z-index: 1; max-width: 420px; width: 100%; }
.erp-left-orbit {
  position: absolute; inset: 0; pointer-events: none; z-index: 0;
}
.erp-float-icon {
  position: absolute;
  width: 44px; height: 44px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  color: var(--rng-accent);
  background: rgb(255 255 255 / 0.08);
  border: 1px solid rgb(201 162 75 / 0.28);
  backdrop-filter: blur(6px);
  animation: erp-float 7s ease-in-out infinite;
}
.erp-fi-1 { top: 12%; left: 10%; animation-delay: 0s; }
.erp-fi-2 { top: 22%; right: 12%; animation-delay: 1.2s; }
.erp-fi-3 { bottom: 28%; left: 14%; animation-delay: 2.1s; }
.erp-fi-4 { bottom: 16%; right: 16%; animation-delay: 3s; }
@keyframes erp-float {
  0%, 100% { transform: translateY(0) rotate(0deg); opacity: .72; }
  50% { transform: translateY(-12px) rotate(3deg); opacity: 1; }
}
.erp-logo-ring {
  display: inline-flex; align-items: center; justify-content: center;
  margin: 0 0 32px;
  padding: 14px 20px;
  background: var(--rng-surface);
  border-radius: var(--rng-radius-card);
  box-shadow: 0 0 0 0 rgb(201 162 75 / 0.35);
  animation: erp-rise .5s cubic-bezier(0.16,1,0.3,1) both, erp-logo-glow 3.6s ease-in-out infinite;
  animation-delay: var(--d,0s), .6s;
}
.erp-logo-ring::before { display: none; }
.erp-logo { display: block; width: auto; height: 64px; max-width: 220px; object-fit: contain; }
@keyframes erp-logo-glow {
  0%, 100% { box-shadow: 0 0 0 0 rgb(201 162 75 / 0.2); }
  50% { box-shadow: 0 0 28px 2px rgb(201 162 75 / 0.35); }
}

.erp-brand-title {
  font-family: var(--rng-font);
  font-size: 36px; font-weight: 600; color: var(--rng-text-on-dark);
  margin: 0; line-height: 1.2; letter-spacing: -0.01em;
}
/* The one gold element on this screen. */
.erp-gold-rule {
  display: block; width: 56px; height: 2px; margin: 20px 0;
  background: var(--rng-accent); border-radius: 2px;
}
.erp-brand-sub {
  font-size: 16px; color: var(--rng-text-on-dark-muted); line-height: 1.6;
  margin: 0 0 32px;
}

.erp-feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px; }
.erp-feature {
  display: flex; align-items: center; gap: 12px;
  color: var(--rng-text-on-dark-muted); font-size: 14px; font-weight: 500;
}
.erp-feature-icon {
  width: 32px; height: 32px; border-radius: 10px;
  background: rgb(255 255 255 / 0.1);
  color: var(--rng-accent);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  border: 1px solid rgb(201 162 75 / 0.22);
}
.erp-feature-icon svg { width: 15px; height: 15px; }
.erp-icon-pulse { animation: erp-icon-pulse 2.8s ease-in-out infinite; animation-delay: var(--p, 0s); }
@keyframes erp-icon-pulse {
  0%, 100% { transform: scale(1); background: rgb(255 255 255 / 0.1); }
  50% { transform: scale(1.06); background: rgb(201 162 75 / 0.18); }
}
@media (prefers-reduced-motion: reduce) {
  .erp-float-icon, .erp-icon-pulse, .erp-logo-ring { animation: none !important; }
}

/* ── Right: off-white with a white card ── */
.erp-right { position: relative; z-index: 1; width: 100%; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
@media (min-width:1024px) { .erp-right { width: 50%; padding: 32px; } }

.erp-form-card {
  width: 100%; max-width: 420px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: var(--rng-radius-card);
  padding: 40px 36px;
  color: var(--card-text);
  box-shadow: var(--rng-shadow);
}
@media (max-width:480px) { .erp-form-card { padding: 28px 20px; } }

.erp-mobile-header { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 24px; }
@media (min-width:1024px) { .erp-mobile-header { display: none; } }
.erp-mobile-logo { width: auto; height: 44px; max-width: 160px; object-fit: contain; }
.erp-mobile-title { font-family: var(--rng-font); font-size: 20px; font-weight: 600; color: var(--rng-primary); margin: 0; }
.erp-root.dark .erp-mobile-title { color: var(--rng-text-on-dark); }

.erp-title {
  font-family: var(--rng-font);
  font-size: 26px; font-weight: 600; color: var(--rng-primary);
  margin: 0 0 6px; letter-spacing: -0.01em;
}
.erp-root.dark .erp-title { color: var(--rng-text-on-dark); }
.erp-subtitle { font-size: 14px; color: var(--card-muted); margin: 0 0 28px; }

.erp-field { margin-bottom: 18px; }
.erp-label {
  display: block; font-size: 13px; font-weight: 500;
  color: var(--card-text); margin-bottom: 6px;
}
.erp-input-wrap {
  position: relative; border: 1px solid var(--input-border); border-radius: var(--rng-radius-control);
  background: var(--input-bg);
  transition: border-color .15s ease, box-shadow .15s ease;
}
.erp-input-wrap.is-focused {
  border-color: var(--rng-primary);
  background: var(--input-bg-solid);
  box-shadow: var(--rng-focus-ring);
}
.erp-root.dark .erp-input-wrap.is-focused { border-color: var(--rng-accent); box-shadow: 0 0 0 3px rgb(201 162 75 / 0.25); }
.erp-input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--rng-text-muted); pointer-events: none; display: flex; }
.erp-root.dark .erp-input-icon { color: var(--rng-text-on-dark-muted); }
.erp-input-wrap.is-focused .erp-input-icon { color: var(--rng-primary); }
.erp-root.dark .erp-input-wrap.is-focused .erp-input-icon { color: var(--rng-text-on-dark); }
.erp-input {
  width: 100%; padding: 11px 14px 11px 38px; border: none; border-radius: var(--rng-radius-control);
  background: transparent; font-size: 15px; color: var(--card-text); outline: none;
  box-sizing: border-box; font-family: inherit;
}
.erp-input::placeholder { color: var(--rng-placeholder); }
.erp-input-password { padding-right: 40px; }
.erp-toggle-visibility {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  background: none; border: none; padding: 4px; cursor: pointer; color: var(--rng-text-muted);
  display: flex; align-items: center; border-radius: 6px;
}
.erp-root.dark .erp-toggle-visibility { color: var(--rng-text-on-dark-muted); }
.erp-toggle-visibility:hover { color: var(--card-text); }
.erp-toggle-visibility:focus-visible,
.erp-forgot:focus-visible,
.erp-submit:focus-visible,
.erp-input:focus-visible { outline: 2px solid var(--rng-primary); outline-offset: 2px; }
.erp-root.dark .erp-submit:focus-visible { outline-color: var(--rng-accent); }

.erp-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
.erp-remember { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
.erp-checkbox { width: 16px; height: 16px; border-radius: 4px; accent-color: var(--rng-primary); }
.erp-remember-text { font-size: 13px; color: var(--card-muted); }
.erp-forgot { font-size: 13px; color: var(--accent); font-weight: 500; text-decoration: none; }
.erp-forgot:hover { text-decoration: underline; text-underline-offset: 3px; }

.erp-error {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--error-bg); border: 1px solid var(--error-line); color: var(--error-text);
  padding: 10px 12px; border-radius: var(--rng-radius-control); font-size: 13px; margin-bottom: 18px;
}
.erp-error svg { flex-shrink: 0; margin-top: 1px; }
.erp-fade-enter-active, .erp-fade-leave-active { transition: opacity .15s ease, transform .15s ease; }
.erp-fade-enter-from, .erp-fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* Primary button: navy, white text, primary-hover on hover. */
.erp-submit {
  width: 100%; padding: 12px;
  border-radius: var(--rng-radius-control); border: none;
  background: var(--brand-600, var(--rng-primary));
  color: var(--rng-text-on-dark); font-size: 15px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s ease;
}
.erp-submit:hover:not(:disabled) { background: var(--brand-700, var(--rng-primary-hover)); }
.erp-root.dark .erp-submit { background: var(--rng-surface); color: var(--brand-600, var(--rng-primary)); }
.erp-root.dark .erp-submit:hover:not(:disabled) { background: var(--brand-50, var(--rng-primary-light)); }
.erp-submit:disabled { opacity: .65; cursor: not-allowed; }
.erp-spin { animation: erp-spin 1s linear infinite; }
@keyframes erp-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@media (prefers-reduced-motion:reduce) { .erp-spin { animation: none; } }

.erp-shortcut-hint { text-align: center; font-size: 12px; color: var(--card-muted); margin: 16px 0 0; }
.erp-shortcut-hint kbd, .erp-shortcut-list kbd {
  display: inline-block; padding: 1px 6px; border-radius: 4px;
  border: 1px solid var(--input-border); background: var(--input-bg-solid);
  font-size: 11px; font-family: inherit; color: var(--card-text);
}
.erp-shortcut-link { background: none; border: none; padding: 0; color: var(--accent); font-weight: 500; font-size: 12px; cursor: pointer; }

.erp-modal-backdrop {
  position: fixed; inset: 0; z-index: 50;
  background: rgb(15 27 61 / 0.55);
  display: flex; align-items: center; justify-content: center; padding: 16px;
}
.erp-modal {
  width: 100%; max-width: 380px; background: var(--card-bg);
  border: 1px solid var(--card-border); border-radius: var(--rng-radius-card); padding: 22px; color: var(--card-text);
  box-shadow: var(--rng-shadow);
}
.erp-modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.erp-modal-head h3 { font-family: var(--rng-font); font-size: 18px; font-weight: 600; margin: 0; }
.erp-modal-close {
  width: 28px; height: 28px; border-radius: 50%; border: none;
  background: var(--rng-row-hover); color: var(--card-text); font-size: 16px; line-height: 1; cursor: pointer;
}
.erp-root.dark .erp-modal-close { background: rgb(255 255 255 / 0.1); }
.erp-modal-close:focus-visible { outline: 2px solid var(--rng-primary); outline-offset: 2px; }
.erp-shortcut-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.erp-shortcut-list li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--card-muted); }
.erp-shortcut-list span { margin-left: auto; }
</style>
