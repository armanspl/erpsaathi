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
      <div class="erp-left-inner">
        <div class="erp-logo-ring erp-anim" style="--d:0s">
          <img src="/assets/img/logo/erpsaathi.png" alt="School logo" class="erp-logo" />
        </div>
        <h1 class="erp-brand-title erp-anim" style="--d:.08s">{{ t.brandTitle }}</h1>
        <p class="erp-brand-sub erp-anim" style="--d:.16s">{{ t.brandSub }}</p>
        <ul class="erp-feature-list">
          <li class="erp-feature erp-anim" style="--d:.24s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>{{ t.feature1 }}</span></li>
          <li class="erp-feature erp-anim" style="--d:.32s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>{{ t.feature2 }}</span></li>
          <li class="erp-feature erp-anim" style="--d:.4s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>{{ t.feature3 }}</span></li>
          <li class="erp-feature erp-anim" style="--d:.48s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>{{ t.feature4 }}</span></li>
        </ul>
        <svg class="erp-illustration erp-anim" style="--d:.56s" viewBox="0 0 200 130" aria-hidden="true">
          <defs>
            <linearGradient id="erp-g1" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#6366F1" /><stop offset="1" stop-color="#4F46E5" /></linearGradient>
            <linearGradient id="erp-g2" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#4F46E5" /><stop offset="1" stop-color="#4338CA" /></linearGradient>
            <linearGradient id="erp-g3" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#4338CA" /><stop offset="1" stop-color="#38BDF8" /></linearGradient>
          </defs>
          <rect x="28" y="88" width="144" height="15" rx="4" fill="url(#erp-g1)" opacity="0.9" />
          <rect x="40" y="72" width="120" height="15" rx="4" fill="url(#erp-g2)" opacity="0.9" />
          <rect x="52" y="56" width="96" height="15" rx="4" fill="url(#erp-g3)" opacity="0.9" />
          <polygon points="100,14 162,40 100,66 38,40" fill="#FFFFFF" opacity="0.92" />
          <rect x="96" y="40" width="8" height="9" fill="#FFFFFF" opacity="0.92" />
          <line x1="142" y1="45" x2="142" y2="70" stroke="#FFFFFF" stroke-width="2" opacity="0.75" />
          <circle cx="142" cy="73" r="3.2" fill="#6366F1" />
          <circle class="erp-spark erp-spark-1" cx="22" cy="26" r="2.4" fill="#6366F1" />
          <circle class="erp-spark erp-spark-2" cx="180" cy="32" r="2.8" fill="#38BDF8" />
          <circle class="erp-spark erp-spark-3" cx="188" cy="86" r="2.2" fill="#4F46E5" />
        </svg>
      </div>
    </div>

    <!-- Right form panel -->
    <div class="erp-right">
      <div class="erp-form-card erp-anim" style="--d:.1s">
        <div class="erp-mobile-header">
          <img src="/assets/img/logo/erpsaathi.png" alt="School logo" class="erp-mobile-logo" />
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
    const saved = localStorage.getItem("erp-auth-theme");
    this.isDark = saved === "dark";
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
      const bg = dark ? '#07080c' : '#f8fafc';
      document.documentElement.style.backgroundColor = bg;
      if (document.body) document.body.style.backgroundColor = bg;
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
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap');

.erp-root {
  --gold: #4f46e5;
  --gold-soft: #6366f1;
  --gold-strong: #4338ca;
  --violet: #7c3aed;
  --ink: #0f172a;
  --cream: #0f172a;
  --muted: #475569;
  --line: rgba(15, 23, 42, 0.10);
  --card-bg: rgba(255, 255, 255, 0.92);
  --card-border: rgba(15, 23, 42, 0.10);
  --card-text: #0f172a;
  --card-muted: #475569;
  --input-bg: #ffffff;
  --input-bg-solid: #ffffff;
  --input-border: rgba(15, 23, 42, 0.14);
  --error-bg: #fef2f2;
  --error-line: #fca5a5;
  --error-text: #b91c1c;
  --accent: #4f46e5;
  --accent-strong: #4338ca;
  position: relative;
  min-height: 100vh;
  display: flex;
  font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
  color: #0f172a;
  background: #f8fafc;
  overflow: hidden;
}

/* Dark: same indigo/violet brand as the homepage, on slate — not a separate gold theme. */
.erp-root.dark {
  --gold: #6366f1;
  --gold-soft: #818cf8;
  --gold-strong: #4f46e5;
  --violet: #8b5cf6;
  --ink: #0b1120;
  --cream: #e6ebf5;
  --muted: #a3adc2;
  --line: rgba(148, 163, 184, 0.16);
  --card-bg: rgba(17, 26, 46, 0.86);
  --card-border: rgba(148, 163, 184, 0.16);
  --card-text: #e6ebf5;
  --card-muted: #a3adc2;
  --input-bg: rgba(255, 255, 255, 0.04);
  --input-bg-solid: rgba(255, 255, 255, 0.06);
  --input-border: rgba(230, 235, 245, 0.14);
  --error-bg: rgba(180, 60, 60, 0.18);
  --error-line: rgba(248, 180, 180, 0.35);
  --error-text: #f6c1c1;
  --accent: #818cf8;
  --accent-strong: #a5b4fc;
  color: #e6ebf5;
  background: #0b1120;
}

.erp-root.lang-hi { font-family: 'Noto Sans Devanagari', 'Inter', sans-serif; }
.erp-root.lang-hi .erp-brand-title,
.erp-root.lang-hi .erp-mobile-title,
.erp-root.lang-hi .erp-title,
.erp-root.lang-hi .erp-modal-head h3 { font-family: 'Noto Sans Devanagari', 'Plus Jakarta Sans', sans-serif; }

.erp-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
.erp-skip-link { position: absolute; top: -48px; left: 12px; z-index: 100; background: var(--card-bg); color: var(--card-text); padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; transition: top .15s ease; border: 1px solid var(--card-border); }
.erp-skip-link:focus { top: 12px; }

/* Backdrop — matches the homepage: two soft blobs + a faint dot-grid, no continuous drifting icons. */
.erp-mesh { position: absolute; inset: 0; z-index: 0; overflow: hidden; background: radial-gradient(circle at 18% 18%, #eef2ff 0%, #f8fafc 58%); }
.erp-root.dark .erp-mesh { background: radial-gradient(circle at 18% 18%, #111a2e 0%, #0b1120 58%); }
.erp-blob { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .32; animation: erp-drift-a 26s ease-in-out infinite; }
.erp-blob-a { width: 480px; height: 480px; top: -160px; left: -120px; background: radial-gradient(circle, var(--gold-soft), transparent 70%); }
.erp-blob-b { width: 420px; height: 420px; top: 15%; right: -140px; background: radial-gradient(circle, var(--violet), transparent 70%); animation-delay: -12s; }
.erp-root.dark .erp-blob { opacity: .2; }
@keyframes erp-drift-a { 0%,100% { transform: translate(0,0); } 50% { transform: translate(22px,-16px); } }
@media (prefers-reduced-motion:reduce) { .erp-blob { animation: none; } }
.erp-dot-grid {
  position: absolute; inset: 0;
  background-image: radial-gradient(rgba(15,23,42,0.09) 1px, transparent 1px);
  background-size: 26px 26px;
  mask-image: radial-gradient(circle at 20% 10%, #000 0%, transparent 65%);
  -webkit-mask-image: radial-gradient(circle at 20% 10%, #000 0%, transparent 65%);
}
.erp-root.dark .erp-dot-grid { background-image: radial-gradient(rgba(148,163,184,0.14) 1px, transparent 1px); }

.erp-anim { animation: erp-rise .6s cubic-bezier(0.16,1,0.3,1) both; animation-delay: var(--d,0s); }
@keyframes erp-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion:reduce) { .erp-anim { animation: none; opacity: 1; transform: none; } }

.erp-utility-bar {
  position: absolute; top: 18px; right: 18px; z-index: 20;
  display: flex; align-items: center; gap: 8px;
  background: rgba(255, 255, 255, 0.75);
  border: 1px solid rgba(15, 23, 42, 0.10);
  backdrop-filter: blur(12px);
  border-radius: 999px; padding: 6px;
}
.erp-root.dark .erp-utility-bar {
  background: rgba(12, 12, 16, 0.55);
  border-color: rgba(198, 167, 94, 0.28);
}
.erp-theme-toggle {
  width: 30px; height: 30px; border-radius: 50%; border: none;
  background: rgba(79, 70, 229, 0.12); color: #4f46e5;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: background .15s ease, transform .15s ease;
}
.erp-root.dark .erp-theme-toggle {
  background: rgba(198, 167, 94, 0.16); color: #e2c98a;
}
.erp-theme-toggle:hover { background: rgba(79, 70, 229, 0.2); transform: rotate(15deg); }
.erp-root.dark .erp-theme-toggle:hover { background: rgba(198, 167, 94, 0.28); }
.erp-theme-toggle:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }

.erp-lang-switch { position: relative; }
.erp-lang-menu {
  position: absolute; top: calc(100% + 8px); right: 0; min-width: 128px;
  border: 1px solid var(--card-border); border-radius: 10px;
  background: var(--card-bg); backdrop-filter: blur(18px);
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
  padding: 6px; display: flex; flex-direction: column; gap: 2px; z-index: 30;
}
.erp-lang-menu button {
  text-align: left; padding: 8px 10px; border-radius: 7px; border: 0;
  background: transparent; font-family: inherit; font-size: 13px; font-weight: 600;
  color: var(--card-muted); cursor: pointer;
}
.erp-lang-menu button:hover { background: rgba(79, 70, 229, 0.08); color: var(--card-text); }
.erp-lang-menu button.is-active { background: rgba(79, 70, 229, 0.12); color: var(--accent); }
.erp-root.dark .erp-lang-menu button:hover { background: rgba(99, 102, 241, 0.14); }
.erp-root.dark .erp-lang-menu button.is-active { background: rgba(99, 102, 241, 0.18); }

.erp-left { position: relative; z-index: 1; display: none; width: 45%; align-items: center; justify-content: center; padding: 48px; }
@media (min-width:1024px) { .erp-left { display: flex; } }
.erp-left-inner { max-width: 400px; }
.erp-logo-ring {
  width: auto;
  height: auto;
  min-width: 180px;
  min-height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
  position: relative;
}
.erp-logo-ring::before { display: none; }
.erp-logo {
  position: relative;
  width: auto;
  height: 110px;
  max-width: 260px;
  object-fit: contain;
  background: transparent;
  border-radius: 0;
  padding: 0;
  box-shadow: none;
}
.erp-root.dark .erp-logo {
  background: transparent;
  box-shadow: none;
}

.erp-brand-title {
  font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif;
  font-size: 40px; font-weight: 700; color: #0f172a;
  margin-bottom: 12px; line-height: 1.15; letter-spacing: 0.01em; text-align: center;
}
.erp-root.dark .erp-brand-title { color: #f7f2e8; }
.erp-brand-sub {
  font-size: 15px; color: #475569; line-height: 1.65;
  margin-bottom: 32px; text-align: center;
}
.erp-root.dark .erp-brand-sub { color: rgba(243, 239, 230, 0.68); }

.erp-feature-list { list-style: none; padding: 0; margin: 0 0 24px; display: flex; flex-direction: column; gap: 12px; }
.erp-feature {
  display: flex; align-items: center; gap: 12px;
  color: #0f172a; font-size: 14px; font-weight: 500;
  padding: 11px 14px; border-radius: 12px;
  background: rgba(255,255,255,0.72);
  border: 1px solid rgba(15,23,42,0.08);
  backdrop-filter: blur(6px);
  transition: background .2s ease, transform .2s ease, border-color .2s ease;
}
.erp-root.dark .erp-feature {
  color: #f3efe6;
  background: rgba(255,255,255,0.04);
  border-color: rgba(198,167,94,0.16);
}
.erp-feature:hover { background: rgba(99,102,241,0.08); transform: translateX(3px); border-color: rgba(79,70,229,0.28); }
.erp-root.dark .erp-feature:hover { background: rgba(198,167,94,0.1); border-color: rgba(198,167,94,0.35); }
.erp-feature-icon {
  width: 26px; height: 26px; border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.erp-root.dark .erp-feature-icon {
  background: linear-gradient(135deg, #c6a75e, #8a6d2f);
  color: #0b0c10;
}

.erp-illustration {
  width: 100%; max-width: 260px; display: block; margin: 8px auto 0;
  animation-name: erp-rise, erp-bob;
  animation-duration: .6s, 5s;
  animation-delay: var(--d,0s), .6s;
  animation-iteration-count: 1, infinite;
  animation-timing-function: cubic-bezier(0.16,1,0.3,1), ease-in-out;
  filter: saturate(0.85) brightness(1.05);
}
@keyframes erp-bob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
@media (prefers-reduced-motion:reduce) { .erp-illustration { animation: none; } }
.erp-spark { animation: erp-twinkle 2.4s ease-in-out infinite; transform-origin: center; }
.erp-spark-2 { animation-delay: .6s; } .erp-spark-3 { animation-delay: 1.2s; }
@keyframes erp-twinkle { 0%,100% { opacity: .25; transform: scale(.8); } 50% { opacity: 1; transform: scale(1.15); } }
@media (prefers-reduced-motion:reduce) { .erp-spark { animation: none; opacity: .8; } }

.erp-right { position: relative; z-index: 1; width: 100%; display: flex; align-items: center; justify-content: center; padding: 32px; }
@media (min-width:1024px) { .erp-right { width: 55%; } }

.erp-form-card {
  width: 100%; max-width: 420px;
  background: var(--card-bg);
  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);
  border: 1px solid var(--card-border);
  border-radius: 20px;
  padding: 38px 36px;
  color: var(--card-text);
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.10), 0 1px 0 rgba(255,255,255,0.8) inset;
  transition: background .25s ease, border-color .25s ease, box-shadow .25s ease;
}
.erp-root.dark .erp-form-card {
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(198, 167, 94, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.04);
}
@media (max-width:480px) { .erp-form-card { padding: 30px 22px; } }

.erp-mobile-header { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 28px; }
@media (min-width:1024px) { .erp-mobile-header { display: none; } }
.erp-mobile-logo { width: auto; height: 44px; max-width: 160px; object-fit: contain; background: transparent; border-radius: 0; box-shadow: none; }
.erp-mobile-title { font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif; font-size: 22px; font-weight: 700; color: var(--card-text); }

.erp-title {
  font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif;
  font-size: 30px; font-weight: 700; color: var(--card-text);
  margin-bottom: 6px; letter-spacing: -0.01em;
}
.erp-subtitle { font-size: 14px; color: var(--card-muted); margin-bottom: 26px; }

.erp-field { margin-bottom: 18px; }
.erp-label {
  display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
  text-transform: uppercase; color: var(--card-muted); margin-bottom: 7px;
}
.erp-input-wrap {
  position: relative; border: 1px solid var(--input-border); border-radius: 10px;
  background: var(--input-bg);
  transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
.erp-input-wrap.is-focused {
  border-color: var(--gold);
  background: var(--input-bg-solid);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.14);
}
.erp-root.dark .erp-input-wrap.is-focused {
  box-shadow: 0 0 0 3px rgba(198, 167, 94, 0.16);
}
.erp-input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; display: flex; transition: color .18s ease; }
.erp-input-wrap.is-focused .erp-input-icon { color: var(--gold); }
.erp-input {
  width: 100%; padding: 13px 14px 13px 40px; border: none; border-radius: 10px;
  background: transparent; font-size: 14px; color: var(--card-text); outline: none;
  box-sizing: border-box; font-family: inherit;
}
.erp-input::placeholder { color: #94a3b8; }
.erp-input-password { padding-right: 40px; }
.erp-toggle-visibility {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; padding: 4px; cursor: pointer; color: #94a3b8;
  display: flex; align-items: center; transition: color .15s ease;
}
.erp-toggle-visibility:hover { color: var(--card-text); }
.erp-toggle-visibility:focus-visible,
.erp-forgot:focus-visible,
.erp-submit:focus-visible,
.erp-input:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }

.erp-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
.erp-remember { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
.erp-checkbox { width: 16px; height: 16px; border-radius: 4px; accent-color: var(--gold); }
.erp-remember-text { font-size: 13px; color: var(--card-muted); }
.erp-forgot { font-size: 13px; color: var(--accent); font-weight: 600; text-decoration: none; }
.erp-forgot:hover { color: var(--gold-strong); text-decoration: underline; text-underline-offset: 3px; }

.erp-error {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--error-bg); border: 1px solid var(--error-line); color: var(--error-text);
  padding: 11px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px;
}
.erp-error svg { flex-shrink: 0; margin-top: 1px; }
.erp-fade-enter-active, .erp-fade-leave-active { transition: opacity .15s ease, transform .15s ease; }
.erp-fade-enter-from, .erp-fade-leave-to { opacity: 0; transform: translateY(-4px); }

.erp-submit {
  position: relative; overflow: hidden; width: 100%; padding: 14px;
  border-radius: 10px; border: none;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #ffffff; font-size: 14px; font-weight: 700; letter-spacing: 0.02em;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 10px 28px rgba(79, 70, 229, 0.28);
  transition: transform .15s ease, box-shadow .15s ease;
}
.erp-root.dark .erp-submit {
  background: linear-gradient(135deg, #d4b56a, #9a7a3a);
  color: #14110c;
  box-shadow: 0 10px 28px rgba(198, 167, 94, 0.28);
}
.erp-submit::after {
  content: ""; position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
  background: linear-gradient(120deg, transparent, rgba(255,255,255,0.4), transparent);
  transform: skewX(-20deg) translateX(0); transition: transform .5s ease;
}
.erp-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 14px 32px rgba(79, 70, 229, 0.38); }
.erp-root.dark .erp-submit:hover:not(:disabled) { box-shadow: 0 14px 32px rgba(198, 167, 94, 0.38); }
.erp-submit:hover:not(:disabled)::after { transform: skewX(-20deg) translateX(280%); }
.erp-submit:active:not(:disabled) { transform: translateY(0); }
.erp-submit:disabled { opacity: .65; cursor: not-allowed; }
.erp-spin { animation: erp-spin 1s linear infinite; }
@keyframes erp-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@media (prefers-reduced-motion:reduce) { .erp-spin { animation: none; } }

.erp-shortcut-hint { text-align: center; font-size: 11.5px; color: var(--card-muted); margin-top: 16px; }
.erp-shortcut-hint kbd, .erp-shortcut-list kbd {
  display: inline-block; padding: 1px 6px; border-radius: 4px;
  border: 1px solid var(--input-border); background: var(--input-bg-solid);
  font-size: 11px; font-family: inherit; color: var(--card-text);
}
.erp-shortcut-link { background: none; border: none; padding: 0; color: var(--accent); font-weight: 600; font-size: 11.5px; cursor: pointer; }

.erp-modal-backdrop {
  position: fixed; inset: 0; z-index: 50;
  background: rgba(5, 6, 10, 0.72); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.erp-modal {
  width: 100%; max-width: 380px; background: var(--card-bg); backdrop-filter: blur(18px);
  border: 1px solid var(--card-border); border-radius: 16px; padding: 22px; color: var(--card-text);
}
.erp-modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.erp-modal-head h3 { font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif; font-size: 22px; font-weight: 700; }
.erp-modal-close {
  width: 28px; height: 28px; border-radius: 50%; border: none;
  background: rgba(148,163,184,0.18); color: var(--card-text); font-size: 16px; line-height: 1; cursor: pointer;
}
.erp-modal-close:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
.erp-shortcut-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.erp-shortcut-list li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--card-muted); }
.erp-shortcut-list span { margin-left: auto; }
</style>