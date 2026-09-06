<template>
  <div class="erp-root" :class="{ dark: isDark }" :lang="language">
    <a class="erp-skip-link" href="#erp-login-form">Skip to sign in form</a>

    <!-- Aurora + floating blobs backdrop -->
    <div class="erp-mesh" aria-hidden="true">
      <div class="erp-aurora"></div>
      <span class="erp-blob erp-blob-a"></span>
      <span class="erp-blob erp-blob-b"></span>
      <span class="erp-blob erp-blob-c"></span>
      <span class="erp-blob erp-blob-d"></span>
    </div>

    <!-- Page-wide drifting icons -->
    <div class="erp-floaters" aria-hidden="true">
      <svg class="erp-floater erp-floater-1" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 10 12 5 2 10l10 5 10-5Z" /><path d="M6 12v5c0 1.1 2.7 3 6 3s6-1.9 6-3v-5" />
      </svg>
      <svg class="erp-floater erp-floater-2" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 4.5A2.5 2.5 0 0 1 4.5 2H9a2.5 2.5 0 0 1 2.5 2.5v15A2 2 0 0 0 9.5 17H2Z" /><path d="M22 4.5A2.5 2.5 0 0 0 19.5 2H15a2.5 2.5 0 0 0-2.5 2.5v15A2 2 0 0 1 14.5 17H22Z" />
      </svg>
      <svg class="erp-floater erp-floater-3" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="m17 3 4 4L7 21l-4 1 1-4Z" /><path d="m14.5 5.5 4 4" />
      </svg>
      <svg class="erp-floater erp-floater-4" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="2" width="16" height="20" rx="2" /><line x1="8" y1="6" x2="16" y2="6" /><line x1="8" y1="10" x2="10" y2="10" /><line x1="12" y1="10" x2="14" y2="10" /><line x1="16" y1="10" x2="16" y2="10" /><line x1="8" y1="14" x2="10" y2="14" /><line x1="12" y1="14" x2="14" y2="14" /><line x1="16" y1="14" x2="16" y2="14" /><line x1="8" y1="18" x2="16" y2="18" />
      </svg>
      <svg class="erp-floater erp-floater-5" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 21 21 3" /><path d="m16 3 5 5" /><path d="m14.5 8.5 2 2" /><path d="m11 12 2 2" /><path d="m7.5 15.5 2 2" />
      </svg>
      <svg class="erp-floater erp-floater-6" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 3v18h18" /><rect x="7" y="12" width="3" height="6" /><rect x="12.5" y="8" width="3" height="10" /><rect x="18" y="5" width="3" height="13" />
      </svg>
      <svg class="erp-floater erp-floater-7" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" />
      </svg>
      <img src="/assets/img/logo/cute-graduate-boy-character-in-cap-and-gown-holding-diploma-png.webp" alt="" class="erp-floater erp-floater-img erp-floater-6" />
      <img src="/assets/img/logo/kids.webp" alt="" class="erp-floater erp-floater-img erp-floater-10" />
    </div>

    <!-- Utility bar -->
    <div class="erp-utility-bar">
      <div class="erp-utility-item">
        <label class="erp-sr-only" for="erp-language">Language</label>
        <select id="erp-language" ref="languageSelect" class="erp-utility-select" v-model="language">
          <option v-for="lang in languages" :key="lang.code" :value="lang.code">{{ lang.label }}</option>
        </select>
      </div>
      <button type="button" class="erp-theme-toggle" @click="toggleTheme" :aria-pressed="isDark" aria-label="Toggle dark mode" title="Toggle dark / light mode (Alt+D)">
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
          <img src="/assets/img/logo/demo.jpeg" alt="School logo" class="erp-logo" />
        </div>
        <h1 class="erp-brand-title erp-anim" style="--d:.08s">School ERP</h1>
        <p class="erp-brand-sub erp-anim" style="--d:.16s">Manage students, attendance, fees and reports — all in one place.</p>
        <ul class="erp-feature-list">
          <li class="erp-feature erp-anim" style="--d:.24s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Student & staff management</span></li>
          <li class="erp-feature erp-anim" style="--d:.32s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Attendance tracking</span></li>
          <li class="erp-feature erp-anim" style="--d:.4s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Fee & payment management</span></li>
          <li class="erp-feature erp-anim" style="--d:.48s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Reports & analytics</span></li>
        </ul>
        <svg class="erp-illustration erp-anim" style="--d:.56s" viewBox="0 0 200 130" aria-hidden="true">
          <defs>
            <linearGradient id="erp-g1" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#E2C98A" /><stop offset="1" stop-color="#C6A75E" /></linearGradient>
            <linearGradient id="erp-g2" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#C6A75E" /><stop offset="1" stop-color="#8A6D2F" /></linearGradient>
            <linearGradient id="erp-g3" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#8A6D2F" /><stop offset="1" stop-color="#5A6E8C" /></linearGradient>
          </defs>
          <rect x="28" y="88" width="144" height="15" rx="4" fill="url(#erp-g1)" opacity="0.9" />
          <rect x="40" y="72" width="120" height="15" rx="4" fill="url(#erp-g2)" opacity="0.9" />
          <rect x="52" y="56" width="96" height="15" rx="4" fill="url(#erp-g3)" opacity="0.9" />
          <polygon points="100,14 162,40 100,66 38,40" fill="#F7F2E8" opacity="0.92" />
          <rect x="96" y="40" width="8" height="9" fill="#F7F2E8" opacity="0.92" />
          <line x1="142" y1="45" x2="142" y2="70" stroke="#F7F2E8" stroke-width="2" opacity="0.75" />
          <circle cx="142" cy="73" r="3.2" fill="#C6A75E" />
          <circle class="erp-spark erp-spark-1" cx="22" cy="26" r="2.4" fill="#E2C98A" />
          <circle class="erp-spark erp-spark-2" cx="180" cy="32" r="2.8" fill="#C6A75E" />
          <circle class="erp-spark erp-spark-3" cx="188" cy="86" r="2.2" fill="#F7F2E8" />
        </svg>
      </div>
    </div>

    <!-- Right form panel -->
    <div class="erp-right">
      <div class="erp-form-card erp-anim" style="--d:.1s">
        <div class="erp-mobile-header">
          <img src="/assets/img/logo/demo.jpeg" alt="School logo" class="erp-mobile-logo" />
          <h2 class="erp-mobile-title">School ERP</h2>
        </div>
        <h2 class="erp-title">Welcome back</h2>
        <p class="erp-subtitle">Sign in to access the ERP system.</p>

        <form id="erp-login-form" @submit.prevent="handleLogin" novalidate :aria-busy="loading">
          <div class="erp-field">
            <label class="erp-label" for="erp-email">Email address</label>
            <div class="erp-input-wrap" :class="{ 'is-focused': focusedField === 'email' }">
              <span class="erp-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 6-10 7L2 6" /></svg>
              </span>
              <input id="erp-email" ref="emailInput" type="email" required class="erp-input" placeholder="you@example.com" v-model="email" autocomplete="username" @focus="focusedField = 'email'" @blur="focusedField = null" />
            </div>
          </div>
          <div class="erp-field">
            <label class="erp-label" for="erp-password">Password</label>
            <div class="erp-input-wrap" :class="{ 'is-focused': focusedField === 'password' }">
              <span class="erp-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
              </span>
              <input id="erp-password" ref="passwordInput" :type="showPassword ? 'text' : 'password'" required class="erp-input erp-input-password" placeholder="Enter your password" v-model="password" autocomplete="current-password" @focus="focusedField = 'password'" @blur="focusedField = null" />
              <button type="button" class="erp-toggle-visibility" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" /><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" /><line x1="2" x2="22" y1="2" y2="22" /></svg>
              </button>
            </div>
          </div>
          <div class="erp-row">
            <label class="erp-remember">
              <input type="checkbox" class="erp-checkbox" v-model="remember" />
              <span class="erp-remember-text">Remember me</span>
            </label>
            <a href="/erp/forgot-password" class="erp-forgot">Forgot password?</a>
          </div>

          <transition name="erp-fade">
            <div v-if="error" class="erp-error" role="alert" aria-live="assertive">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
              <span>{{ error }}</span>
            </div>
          </transition>

          <button type="submit" class="erp-submit" :disabled="loading">
            <svg v-if="loading" class="erp-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
            <span>{{ loading ? 'Signing in...' : 'Sign In' }}</span>
          </button>

          <p class="erp-shortcut-hint">
            Press <kbd>/</kbd> to focus email ·
            <button type="button" class="erp-shortcut-link" @click="showShortcuts = true">keyboard shortcuts</button>
          </p>
        </form>
      </div>
    </div>

    <!-- Shortcuts modal -->
    <transition name="erp-fade">
      <div v-if="showShortcuts" class="erp-modal-backdrop" @click.self="showShortcuts = false">
        <div class="erp-modal" role="dialog" aria-modal="true" aria-labelledby="erp-shortcuts-title">
          <div class="erp-modal-head">
            <h3 id="erp-shortcuts-title">Keyboard shortcuts</h3>
            <button ref="shortcutsClose" type="button" class="erp-modal-close" @click="showShortcuts = false" aria-label="Close keyboard shortcuts">&times;</button>
          </div>
          <ul class="erp-shortcut-list">
            <li><kbd>/</kbd><span>Focus the email field</span></li>
            <li><kbd>Alt</kbd>+<kbd>P</kbd><span>Focus the password field</span></li>
            <li><kbd>Alt</kbd>+<kbd>D</kbd><span>Toggle dark / light mode</span></li>
            <li><kbd>Alt</kbd>+<kbd>L</kbd><span>Focus the language selector</span></li>
            <li><kbd>Enter</kbd><span>Submit the sign-in form</span></li>
            <li><kbd>Esc</kbd><span>Close this dialog</span></li>
          </ul>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
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
      isDark: true,
      language: "en",
      languages: [{ code: "en", label: "English" }],
      showShortcuts: false,
    };
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
    const saved = localStorage.getItem("erp-login-theme");
    this.isDark = saved === null ? true : saved === "dark";
    this.applyTheme(this.isDark);
    window.addEventListener("keydown", this.handleGlobalKeydown);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.handleGlobalKeydown);
    document.documentElement.classList.remove("erp-login-dark", "erp-login-light");
    document.body.classList.remove("erp-login-dark", "erp-login-light");
  },
  methods: {
    applyTheme(dark) {
      const on = dark ? "erp-login-dark" : "erp-login-light";
      const off = dark ? "erp-login-light" : "erp-login-dark";
      document.documentElement.classList.add(on);
      document.documentElement.classList.remove(off);
      document.body.classList.add(on);
      document.body.classList.remove(off);
      localStorage.setItem("erp-login-theme", dark ? "dark" : "light");
    },
    async handleLogin() {
      this.error = null;

      if (!this.email || !this.password) {
        this.error = "Please enter your email and password.";
        return;
      }

      this.loading = true;
      try {
        const response = await fetch('/erp/authenticate', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: JSON.stringify({
            email: this.email,
            password: this.password,
            remember: this.remember,
          }),
        });

        const data = await response.json();

        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          this.error = data.message || 'Invalid email or password. Please try again.';
        }
      } catch (err) {
        this.error = 'A network error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    toggleTheme() {
      this.isDark = !this.isDark;
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
      if (e.altKey && (e.key === "p" || e.key === "P")) { e.preventDefault(); this.focusPassword(); return; }
      if (e.altKey && (e.key === "l" || e.key === "L")) { e.preventDefault(); this.$refs.languageSelect && this.$refs.languageSelect.focus(); }
    },
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap');

.erp-root {
  --gold: #c6a75e;
  --gold-soft: #e2c98a;
  --ink: #0b0c10;
  --cream: #f3efe6;
  --muted: #9a958c;
  --line: rgba(198, 167, 94, 0.22);
  --card-bg: rgba(18, 19, 24, 0.82);
  --card-border: rgba(198, 167, 94, 0.28);
  --card-text: #f3efe6;
  --card-muted: #a8a39a;
  --input-bg: rgba(255, 255, 255, 0.04);
  --input-bg-solid: rgba(255, 255, 255, 0.07);
  --input-border: rgba(243, 239, 230, 0.14);
  --error-bg: rgba(180, 60, 60, 0.18);
  --error-line: rgba(248, 180, 180, 0.35);
  --error-text: #f6c1c1;
  --accent: var(--gold);
  --accent-strong: #b89445;
  position: relative;
  min-height: 100vh;
  display: flex;
  font-family: 'Manrope', sans-serif;
  color: var(--cream);
  background: #07080c;
  overflow: hidden;
}

.erp-root:not(.dark) {
  --card-bg: rgba(255, 252, 246, 0.9);
  --card-border: rgba(40, 36, 28, 0.1);
  --card-text: #1a1814;
  --card-muted: #6b655c;
  --input-bg: rgba(255, 255, 255, 0.92);
  --input-bg-solid: #ffffff;
  --input-border: rgba(40, 36, 28, 0.14);
  --error-bg: #fef2f2;
  --error-line: #fca5a5;
  --error-text: #b91c1c;
  --accent: #8a6d2f;
  --accent-strong: #6f5622;
  color: #1a1814;
  background: #ebe6dc;
}

.erp-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
.erp-skip-link { position: absolute; top: -48px; left: 12px; z-index: 100; background: var(--card-bg); color: var(--card-text); padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; transition: top .15s ease; border: 1px solid var(--card-border); }
.erp-skip-link:focus { top: 12px; }

/* Mesh / aurora / blobs — motion unchanged; palette refined */
.erp-mesh { position: absolute; inset: 0; z-index: 0; overflow: hidden; background: radial-gradient(circle at 18% 18%, #1a1620 0%, #07080c 58%); }
.erp-root:not(.dark) .erp-mesh { background: radial-gradient(circle at 18% 18%, #ddd4c4 0%, #ebe6dc 58%); }
.erp-aurora { position: absolute; inset: -20%; background: conic-gradient(from 0deg, rgba(198,167,94,0.18), rgba(90,110,140,0.12), rgba(198,167,94,0.16), rgba(40,50,70,0.14), rgba(198,167,94,0.18)); filter: blur(90px); opacity: .55; animation: erp-aurora-spin 40s linear infinite; }
.erp-root:not(.dark) .erp-aurora { opacity: .35; }
@keyframes erp-aurora-spin { to { transform: rotate(360deg); } }
.erp-blob { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .45; }
.erp-blob-a { width: 480px; height: 480px; top: -120px; left: -80px; background: radial-gradient(circle, rgba(198,167,94,0.45), transparent 70%); animation: erp-drift-a 22s ease-in-out infinite; }
.erp-blob-b { width: 420px; height: 420px; top: 10%; right: -100px; background: radial-gradient(circle, rgba(70,85,110,0.55), transparent 70%); animation: erp-drift-b 26s ease-in-out infinite; }
.erp-blob-c { width: 520px; height: 520px; bottom: -140px; left: 30%; background: radial-gradient(circle, rgba(120,100,70,0.35), transparent 70%); opacity: .35; animation: erp-drift-c 30s ease-in-out infinite; }
.erp-blob-d { width: 360px; height: 360px; bottom: 5%; right: 8%; background: radial-gradient(circle, rgba(180,190,210,0.25), transparent 70%); opacity: .28; animation: erp-drift-d 24s ease-in-out infinite; }
@keyframes erp-drift-a { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(60px,40px) scale(1.1); } }
@keyframes erp-drift-b { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-50px,60px) scale(1.08); } }
@keyframes erp-drift-c { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(40px,-50px) scale(1.12); } }
@keyframes erp-drift-d { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(-30px,-30px) scale(1.06); } }
@media (prefers-reduced-motion:reduce) { .erp-blob, .erp-aurora { animation: none; } }

.erp-floaters { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.erp-floater { position: absolute; color: var(--gold-soft); opacity: .18; }
.erp-root:not(.dark) .erp-floater { color: #6f5622; opacity: .14; }
.erp-floater-1 { top: 12%; left: 8%; animation: erp-path-1 26s ease-in-out infinite; }
.erp-floater-2 { top: 68%; left: 14%; animation: erp-path-2 32s ease-in-out infinite; animation-delay: -6s; }
.erp-floater-3 { top: 22%; left: 82%; animation: erp-path-3 24s ease-in-out infinite; animation-delay: -3s; }
.erp-floater-4 { top: 78%; left: 78%; animation: erp-path-4 30s ease-in-out infinite; animation-delay: -12s; }
.erp-floater-5 { top: 45%; left: 46%; animation: erp-path-5 34s ease-in-out infinite; animation-delay: -18s; opacity: .12; }
.erp-floater-6 { top: 8%; left: 55%; animation: erp-path-6 28s ease-in-out infinite; animation-delay: -9s; }
.erp-floater-7 { top: 88%; left: 40%; animation: erp-path-7 22s ease-in-out infinite; animation-delay: -4s; opacity: .14; }
.erp-floater-img { width: 100px; height: auto; object-fit: contain; opacity: .38; filter: drop-shadow(0 8px 18px rgba(0,0,0,0.45)); }
.erp-floater-8 { top: 16%; left: 30%; width: 96px; animation: erp-path-8 36s ease-in-out infinite; animation-delay: -14s; }
.erp-floater-9 { top: 60%; left: 62%; width: 72px; animation: erp-path-9 30s ease-in-out infinite; animation-delay: -20s; }
.erp-floater-10 { top: 34%; left: 4%; width: 88px; animation: erp-path-10 33s ease-in-out infinite; animation-delay: -8s; }
@keyframes erp-path-1 { 0%,100% { transform: translate(0,0) rotate(0deg); } 25% { transform: translate(90px,65px) rotate(14deg); } 50% { transform: translate(25px,150px) rotate(-8deg); } 75% { transform: translate(-70px,45px) rotate(10deg); } }
@keyframes erp-path-2 { 0%,100% { transform: translate(0,0) rotate(0deg); } 30% { transform: translate(-100px,-55px) rotate(-16deg); } 60% { transform: translate(45px,-120px) rotate(9deg); } 85% { transform: translate(110px,-20px) rotate(-10deg); } }
@keyframes erp-path-3 { 0%,100% { transform: translate(0,0) rotate(0deg); } 25% { transform: translate(-80px,90px) rotate(-18deg); } 55% { transform: translate(-135px,10px) rotate(10deg); } 80% { transform: translate(-45px,-80px) rotate(-7deg); } }
@keyframes erp-path-4 { 0%,100% { transform: translate(0,0) rotate(0deg); } 20% { transform: translate(-110px,-80px) rotate(15deg); } 50% { transform: translate(-35px,-155px) rotate(-12deg); } 75% { transform: translate(80px,-55px) rotate(8deg); } }
@keyframes erp-path-5 { 0%,100% { transform: translate(0,0) rotate(0deg) scale(1); } 33% { transform: translate(130px,-90px) rotate(10deg) scale(1.12); } 66% { transform: translate(-90px,-135px) rotate(-10deg) scale(.92); } }
@keyframes erp-path-6 { 0%,100% { transform: translate(0,0) rotate(0deg); } 25% { transform: translate(80px,100px) rotate(-14deg); } 55% { transform: translate(145px,25px) rotate(11deg); } 80% { transform: translate(45px,-55px) rotate(-8deg); } }
@keyframes erp-path-7 { 0%,100% { transform: translate(0,0) rotate(0deg); } 30% { transform: translate(-65px,-80px) rotate(13deg); } 65% { transform: translate(55px,-125px) rotate(-15deg); } }
@keyframes erp-path-8 { 0%,100% { transform: translate(0,0) rotate(0deg); } 25% { transform: translate(120px,60px) rotate(6deg); } 55% { transform: translate(40px,160px) rotate(-5deg); } 80% { transform: translate(-90px,55px) rotate(4deg); } }
@keyframes erp-path-9 { 0%,100% { transform: translate(0,0) rotate(0deg); } 30% { transform: translate(-110px,-70px) rotate(-6deg); } 60% { transform: translate(-40px,-160px) rotate(5deg); } 85% { transform: translate(95px,-50px) rotate(-4deg); } }
@keyframes erp-path-10 { 0%,100% { transform: translate(0,0) rotate(0deg); } 28% { transform: translate(100px,90px) rotate(-5deg); } 58% { transform: translate(170px,-20px) rotate(4deg); } 82% { transform: translate(60px,-100px) rotate(-3deg); } }
@media (prefers-reduced-motion:reduce) { .erp-floater { animation: none; } }
@media (max-width:640px) { .erp-floater { opacity: .1; } .erp-floater-5 { display: none; } .erp-floater-img { opacity: .25; } .erp-floater-8,.erp-floater-9,.erp-floater-10 { width: 56px; } }

.erp-anim { animation: erp-rise .6s cubic-bezier(0.16,1,0.3,1) both; animation-delay: var(--d,0s); }
@keyframes erp-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@media (prefers-reduced-motion:reduce) { .erp-anim { animation: none; opacity: 1; transform: none; } }

.erp-utility-bar {
  position: absolute; top: 18px; right: 18px; z-index: 20;
  display: flex; align-items: center; gap: 8px;
  background: rgba(12, 12, 16, 0.55);
  border: 1px solid rgba(198, 167, 94, 0.28);
  backdrop-filter: blur(12px);
  border-radius: 999px; padding: 6px;
}
.erp-root:not(.dark) .erp-utility-bar {
  background: rgba(255, 252, 246, 0.7);
  border-color: rgba(40, 36, 28, 0.12);
}
.erp-utility-select {
  appearance: none; background: transparent; border: none;
  color: var(--gold-soft); font-size: 12.5px; font-weight: 600;
  padding: 6px 10px; border-radius: 999px; cursor: pointer;
}
.erp-root:not(.dark) .erp-utility-select { color: #5c4a24; }
.erp-utility-select:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
.erp-utility-select option { color: #1a1814; }
.erp-theme-toggle {
  width: 30px; height: 30px; border-radius: 50%; border: none;
  background: rgba(198, 167, 94, 0.16); color: var(--gold-soft);
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: background .15s ease, transform .15s ease;
}
.erp-root:not(.dark) .erp-theme-toggle { background: rgba(138, 109, 47, 0.12); color: #6f5622; }
.erp-theme-toggle:hover { background: rgba(198, 167, 94, 0.28); transform: rotate(15deg); }
.erp-theme-toggle:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }

.erp-left { position: relative; z-index: 1; display: none; width: 45%; align-items: center; justify-content: center; padding: 48px; }
@media (min-width:1024px) { .erp-left { display: flex; } }
.erp-left-inner { max-width: 400px; }
.erp-logo-ring { width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; margin: 0 auto 28px; position: relative; }
.erp-logo-ring::before {
  content: ""; position: absolute; inset: 0; border-radius: 50%;
  background: conic-gradient(from 0deg, var(--gold-soft), #5a6e8c, var(--gold), var(--gold-soft));
  opacity: .5; filter: blur(6px); animation: erp-logo-spin 6s linear infinite;
}
@media (prefers-reduced-motion:reduce) { .erp-logo-ring::before { animation: none; } }
@keyframes erp-logo-spin { to { transform: rotate(360deg); } }
.erp-logo {
  position: relative; width: 84px; height: 84px; object-fit: contain;
  background: #14151a; border-radius: 18px; padding: 12px;
  box-shadow: 0 8px 28px rgba(0,0,0,0.45), 0 0 0 1px rgba(198,167,94,0.25);
}
.erp-root:not(.dark) .erp-logo { background: #fff; box-shadow: 0 8px 24px rgba(40,36,28,0.12); }

.erp-brand-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 40px; font-weight: 600; color: #f7f2e8;
  margin-bottom: 12px; line-height: 1.15; letter-spacing: 0.01em; text-align: center;
}
.erp-root:not(.dark) .erp-brand-title { color: #1a1814; }
.erp-brand-sub {
  font-size: 15px; color: rgba(243, 239, 230, 0.68); line-height: 1.65;
  margin-bottom: 32px; text-align: center;
}
.erp-root:not(.dark) .erp-brand-sub { color: rgba(26, 24, 20, 0.65); }

.erp-feature-list { list-style: none; padding: 0; margin: 0 0 24px; display: flex; flex-direction: column; gap: 12px; }
.erp-feature {
  display: flex; align-items: center; gap: 12px;
  color: #f3efe6; font-size: 14px; font-weight: 500;
  padding: 11px 14px; border-radius: 12px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(198,167,94,0.16);
  backdrop-filter: blur(6px);
  transition: background .2s ease, transform .2s ease, border-color .2s ease;
}
.erp-root:not(.dark) .erp-feature {
  color: #1a1814; background: rgba(255,255,255,0.55); border-color: rgba(40,36,28,0.08);
}
.erp-feature:hover { background: rgba(198,167,94,0.1); transform: translateX(3px); border-color: rgba(198,167,94,0.35); }
.erp-feature-icon {
  width: 26px; height: 26px; border-radius: 50%;
  background: linear-gradient(135deg, #c6a75e, #8a6d2f);
  color: #0b0c10; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
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
  box-shadow:
    0 24px 64px rgba(0, 0, 0, 0.45),
    0 0 0 1px rgba(198, 167, 94, 0.06),
    inset 0 1px 0 rgba(255, 255, 255, 0.04);
  transition: background .25s ease, border-color .25s ease, box-shadow .25s ease;
}
.erp-root:not(.dark) .erp-form-card {
  box-shadow: 0 20px 50px rgba(40, 36, 28, 0.1), 0 1px 0 rgba(255,255,255,0.8) inset;
}
@media (max-width:480px) { .erp-form-card { padding: 30px 22px; } }

.erp-mobile-header { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 28px; }
@media (min-width:1024px) { .erp-mobile-header { display: none; } }
.erp-mobile-logo { width: 48px; height: 48px; object-fit: contain; border-radius: 11px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.erp-mobile-title { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; color: var(--card-text); }

.erp-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 34px; font-weight: 600; color: var(--card-text);
  margin-bottom: 6px; letter-spacing: 0.01em;
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
  box-shadow: 0 0 0 3px rgba(198, 167, 94, 0.16);
}
.erp-input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #8a857c; pointer-events: none; display: flex; transition: color .18s ease; }
.erp-input-wrap.is-focused .erp-input-icon { color: var(--gold); }
.erp-input {
  width: 100%; padding: 13px 14px 13px 40px; border: none; border-radius: 10px;
  background: transparent; font-size: 14px; color: var(--card-text); outline: none;
  box-sizing: border-box; font-family: 'Manrope', sans-serif;
}
.erp-input::placeholder { color: #7a756c; }
.erp-input-password { padding-right: 40px; }
.erp-toggle-visibility {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; padding: 4px; cursor: pointer; color: #8a857c;
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
.erp-forgot { font-size: 13px; color: var(--gold-soft); font-weight: 600; text-decoration: none; }
.erp-root:not(.dark) .erp-forgot { color: var(--accent); }
.erp-forgot:hover { color: var(--gold); text-decoration: underline; text-underline-offset: 3px; }

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
  background: linear-gradient(135deg, #d4b56a, #9a7a3a);
  color: #14110c; font-size: 14px; font-weight: 700; letter-spacing: 0.02em;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 10px 28px rgba(198, 167, 94, 0.28);
  transition: transform .15s ease, box-shadow .15s ease;
}
.erp-submit::after {
  content: ""; position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
  background: linear-gradient(120deg, transparent, rgba(255,255,255,0.4), transparent);
  transform: skewX(-20deg) translateX(0); transition: transform .5s ease;
}
.erp-submit:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 14px 32px rgba(198, 167, 94, 0.38); }
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
  font-size: 11px; font-family: 'Manrope', sans-serif; color: var(--card-text);
}
.erp-shortcut-link { background: none; border: none; padding: 0; color: var(--gold-soft); font-weight: 600; font-size: 11.5px; cursor: pointer; }
.erp-root:not(.dark) .erp-shortcut-link { color: var(--accent); }

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
.erp-modal-head h3 { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; }
.erp-modal-close {
  width: 28px; height: 28px; border-radius: 50%; border: none;
  background: rgba(148,163,184,0.18); color: var(--card-text); font-size: 16px; line-height: 1; cursor: pointer;
}
.erp-modal-close:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
.erp-shortcut-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.erp-shortcut-list li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--card-muted); }
.erp-shortcut-list span { margin-left: auto; }
</style>