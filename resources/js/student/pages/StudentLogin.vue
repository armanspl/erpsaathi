<template>
  <div class="s-root" :class="{ dark: isDark }">
    <a class="s-skip-link" href="#student-login-form">Skip to form</a>

    <div class="s-mesh" aria-hidden="true">
      <span class="s-blob s-blob-a"></span>
      <span class="s-blob s-blob-b"></span>
      <span class="s-dot-grid"></span>
    </div>

    <div class="s-utility-bar">
      <button type="button" class="s-theme-toggle" @click="toggleTheme" :aria-pressed="isDark" aria-label="Toggle dark mode" title="Toggle dark / light mode">
        <svg v-if="!isDark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg>
      </button>
    </div>

    <div class="s-left">
      <div class="s-left-orbit" aria-hidden="true">
        <span class="s-float-icon s-fi-1">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
        </span>
        <span class="s-float-icon s-fi-2">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        </span>
        <span class="s-float-icon s-fi-3">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
        </span>
        <span class="s-float-icon s-fi-4">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v12M16 8v10"/><path d="M4 10h4M12 6h4M4 18h16"/></svg>
        </span>
      </div>
      <div class="s-left-inner">
        <div class="s-logo-ring s-anim" style="--d:0s">
          <img src="/assets/img/logo/new-logo2.png?v=1" alt="ERPSaathi logo" class="s-logo" />
        </div>
        <h1 class="s-brand-title s-anim" style="--d:.08s">Student Portal</h1>
        <span class="s-gold-rule s-anim" style="--d:.12s" aria-hidden="true"></span>
        <p class="s-brand-sub s-anim" style="--d:.16s">View your attendance, exam results, fees, transport and more — all in one place.</p>
        <ul class="s-feature-list">
          <li class="s-feature s-anim" style="--d:.24s">
            <span class="s-feature-icon s-icon-pulse">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
            </span>
            <span>Exams, marks &amp; report cards</span>
          </li>
          <li class="s-feature s-anim" style="--d:.32s">
            <span class="s-feature-icon s-icon-pulse" style="--p:.4s">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            </span>
            <span>Fee summary &amp; receipts</span>
          </li>
          <li class="s-feature s-anim" style="--d:.4s">
            <span class="s-feature-icon s-icon-pulse" style="--p:.8s">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
            </span>
            <span>Attendance &amp; calendar</span>
          </li>
          <li class="s-feature s-anim" style="--d:.48s">
            <span class="s-feature-icon s-icon-pulse" style="--p:1.2s">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v12M16 8v10"/><path d="M4 10h4M12 6h4M4 18h16"/></svg>
            </span>
            <span>Transport details</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="s-right">
      <div class="s-form-card s-anim" style="--d:.1s">
        <div class="s-mobile-header">
          <img :src="mobileLogoUrl" alt="ERPSaathi logo" class="s-mobile-logo" />
          <h2 class="s-mobile-title">Student Portal</h2>
        </div>

        <h2 class="s-title">Student Sign In</h2>
        <p class="s-subtitle">Enter your admission number and date of birth to continue.</p>

        <form id="student-login-form" @submit.prevent="handleLogin" novalidate :aria-busy="loading">
          <div class="s-field">
            <label class="s-label" for="s-adm">Admission No.</label>
            <div class="s-input-wrap" :class="{ 'is-focused': focusedField === 'adm' }">
              <span class="s-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              </span>
              <input id="s-adm" type="text" required class="s-input" placeholder="e.g. 16701" v-model="admissionNo" autocomplete="username" @focus="focusedField = 'adm'" @blur="focusedField = null" />
            </div>
          </div>
          <div class="s-field">
            <label class="s-label" for="s-dob">Date of Birth</label>
            <div class="s-input-wrap" :class="{ 'is-focused': focusedField === 'dob' }">
              <span class="s-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              </span>
              <input id="s-dob" type="date" required class="s-input" v-model="dob" autocomplete="bday" @focus="focusedField = 'dob'" @blur="focusedField = null" />
            </div>
          </div>

          <transition name="s-fade">
            <div v-if="error" class="s-error" role="alert" aria-live="assertive">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
              <span>{{ error }}</span>
            </div>
          </transition>

          <button type="submit" class="s-submit" :disabled="loading">
            <svg v-if="loading" class="s-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
            <span>{{ loading ? 'Signing in...' : 'Sign In' }}</span>
          </button>

          <p class="s-hint">Ask your school office if you don't know your admission number.</p>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'StudentLogin',
  data() {
    return {
      admissionNo: '',
      dob: '',
      loading: false,
      error: null,
      focusedField: null,
      isDark: false,
    };
  },
  computed: {
    mobileLogoUrl() {
      return '/assets/img/logo/new-logo2.png?v=1';
    },
  },
  watch: {
    isDark(value) { this.applyTheme(value); },
  },
  mounted() {
    let saved = null;
    try {
      saved = localStorage.getItem('student-auth-theme');
    } catch {
      saved = null;
    }
    // Default light (School Green); only force dark when explicitly saved as '1'
    this.isDark = saved === null ? false : saved === '1';
    this.applyTheme(this.isDark);
  },
  beforeUnmount() {
    document.documentElement.style.removeProperty('background-color');
    if (document.body) document.body.style.removeProperty('background-color');
  },
  methods: {
    applyTheme(dark) {
      const bg = dark ? 'var(--brand-950)' : 'var(--rng-bg)';
      document.documentElement.style.backgroundColor = bg;
      if (document.body) document.body.style.backgroundColor = bg;
      document.documentElement.classList.toggle('student-login-dark', !!dark);
      document.documentElement.classList.toggle('student-login-light', !dark);
      if (document.body) {
        document.body.classList.toggle('student-login-dark', !!dark);
        document.body.classList.toggle('student-login-light', !dark);
      }
      try { localStorage.setItem('student-auth-theme', dark ? '1' : '0'); } catch { /* ignore */ }
    },
    toggleTheme() { this.isDark = !this.isDark; },
    async handleLogin() {
      this.error = null;
      if (!this.admissionNo || !this.dob) {
        this.error = 'Please enter your admission number and date of birth.';
        return;
      }
      this.loading = true;
      try {
        const response = await fetch('/student/authenticate', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: JSON.stringify({ admission_no: this.admissionNo, dob: this.dob }),
        });
        const data = await response.json();
        if (data.success && data.redirect) {
          window.location.href = data.redirect;
        } else {
          this.error = data.message || 'Could not sign in. Please check your details and try again.';
        }
      } catch {
        this.error = 'A network error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
/* Royal Navy + Gold — tokens from resources/css/erp-theme-royal.css */
.s-root {
  --card-bg: var(--rng-surface);
  --card-border: var(--rng-border);
  --card-text: var(--rng-text);
  --card-muted: var(--rng-text-muted);
  --input-bg: var(--rng-surface);
  --input-border: var(--rng-border);
  --error-bg: var(--rng-danger-bg);
  --error-line: var(--rng-danger-bg);
  --error-text: var(--rng-danger-text);
  --accent: var(--rng-primary);
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
.s-root.dark {
  --card-bg: var(--rng-navy-mid);
  --card-border: rgb(255 255 255 / 0.1);
  --card-text: var(--rng-dark-text);
  --card-muted: var(--rng-dark-text-muted);
  --input-bg: rgb(255 255 255 / 0.04);
  --input-border: rgb(255 255 255 / 0.14);
  --error-bg: rgb(220 38 38 / 0.18);
  --error-line: rgb(252 165 165 / 0.35);
  --error-text: var(--rng-danger-on-dark);
  --accent: var(--rng-accent);
  color: var(--rng-dark-text);
  background: var(--rng-primary);
}

.s-skip-link {
  position: absolute; left: -9999px; top: 8px; z-index: 50;
  background: var(--rng-surface); color: var(--rng-primary);
  padding: 8px 12px; border-radius: 8px; font-weight: 600;
}
.s-skip-link:focus { left: 8px; }

.s-mesh { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.s-blob {
  position: absolute; border-radius: 50%; filter: blur(60px); opacity: .45;
}
.s-blob-a { width: 320px; height: 320px; top: -80px; left: -60px; background: rgb(15 27 61 / 0.12); }
.s-blob-b { width: 280px; height: 280px; bottom: -60px; right: -40px; background: rgb(201 162 75 / 0.14); }
.s-root.dark .s-blob-a { background: rgb(201 162 75 / 0.08); opacity: .55; }
.s-root.dark .s-blob-b { background: rgb(255 255 255 / 0.05); }
.s-dot-grid {
  position: absolute; inset: 0;
  background-image: radial-gradient(rgb(15 27 61 / 0.08) 1px, transparent 1px);
  background-size: 22px 22px; opacity: .5;
}
.s-root.dark .s-dot-grid {
  background-image: radial-gradient(rgb(255 255 255 / 0.06) 1px, transparent 1px);
}

.s-anim { animation: s-rise .5s cubic-bezier(0.16,1,0.3,1) both; animation-delay: var(--d,0s); }
@keyframes s-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.s-utility-bar {
  position: absolute; top: 18px; right: 18px; z-index: 20;
  display: flex; gap: 6px;
  background: var(--rng-surface);
  border: 1px solid var(--rng-border);
  border-radius: 999px; padding: 6px;
  box-shadow: var(--rng-shadow);
}
.s-root.dark .s-utility-bar {
  background: rgb(10 20 48 / 0.7);
  border-color: rgb(255 255 255 / 0.12);
}
.s-theme-toggle {
  width: 30px; height: 30px; border-radius: 50%; border: none;
  background: var(--rng-primary-light); color: var(--rng-primary);
  display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.s-root.dark .s-theme-toggle { background: rgb(255 255 255 / 0.08); color: var(--rng-text-on-dark); }
.s-theme-toggle:hover { background: var(--rng-primary-light); }
.s-root.dark .s-theme-toggle:hover { background: rgb(255 255 255 / 0.12); }
.s-theme-toggle:focus-visible { outline: 2px solid var(--rng-primary); outline-offset: 2px; }

.s-left {
  position: relative; z-index: 1; display: none; width: 50%;
  align-items: center; justify-content: center; padding: 56px;
  background: var(--brand-950, var(--rng-navy-deep));
  color: var(--rng-text-on-dark);
  overflow: hidden;
}
@media (min-width:1024px) { .s-left { display: flex; } }
.s-left-inner { position: relative; z-index: 1; max-width: 420px; width: 100%; }
.s-left-orbit { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
.s-float-icon {
  position: absolute;
  width: 44px; height: 44px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  color: var(--rng-accent);
  background: rgb(255 255 255 / 0.08);
  border: 1px solid rgb(201 162 75 / 0.28);
  backdrop-filter: blur(6px);
  animation: s-float 7s ease-in-out infinite;
}
.s-fi-1 { top: 12%; left: 10%; animation-delay: 0s; }
.s-fi-2 { top: 22%; right: 12%; animation-delay: 1.2s; }
.s-fi-3 { bottom: 28%; left: 14%; animation-delay: 2.1s; }
.s-fi-4 { bottom: 16%; right: 16%; animation-delay: 3s; }
@keyframes s-float {
  0%, 100% { transform: translateY(0) rotate(0deg); opacity: .72; }
  50% { transform: translateY(-12px) rotate(3deg); opacity: 1; }
}

.s-logo-ring {
  display: inline-flex; align-items: center; justify-content: center;
  margin: 0 0 32px; padding: 14px 20px;
  background: var(--rng-surface);
  border-radius: var(--rng-radius-card);
  box-shadow: 0 0 0 0 rgb(201 162 75 / 0.35);
  animation: s-rise .5s cubic-bezier(0.16,1,0.3,1) both, s-logo-glow 3.6s ease-in-out infinite;
  animation-delay: var(--d,0s), .6s;
}
.s-logo { display: block; width: auto; height: 64px; max-width: 220px; object-fit: contain; }
@keyframes s-logo-glow {
  0%, 100% { box-shadow: 0 0 0 0 rgb(201 162 75 / 0.2); }
  50% { box-shadow: 0 0 28px 2px rgb(201 162 75 / 0.35); }
}

.s-brand-title {
  font-family: var(--rng-font);
  font-size: 36px; font-weight: 600; color: var(--rng-text-on-dark);
  margin: 0; line-height: 1.2; letter-spacing: -0.01em;
}
.s-gold-rule {
  display: block; width: 56px; height: 2px; margin: 20px 0;
  background: var(--rng-accent); border-radius: 2px;
}
.s-brand-sub {
  font-size: 16px; color: var(--rng-text-on-dark-muted); line-height: 1.6;
  margin: 0 0 32px;
}

.s-feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 14px; }
.s-feature {
  display: flex; align-items: center; gap: 12px;
  color: var(--rng-text-on-dark-muted); font-size: 14px; font-weight: 500;
}
.s-feature-icon {
  width: 32px; height: 32px; border-radius: 10px;
  background: rgb(255 255 255 / 0.1);
  color: var(--rng-accent);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  border: 1px solid rgb(201 162 75 / 0.22);
}
.s-feature-icon svg { width: 15px; height: 15px; }
.s-icon-pulse { animation: s-icon-pulse 2.8s ease-in-out infinite; animation-delay: var(--p, 0s); }
@keyframes s-icon-pulse {
  0%, 100% { transform: scale(1); background: rgb(255 255 255 / 0.1); }
  50% { transform: scale(1.06); background: rgb(201 162 75 / 0.18); }
}

.s-right { position: relative; z-index: 1; width: 100%; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
@media (min-width:1024px) { .s-right { width: 50%; padding: 32px; } }

.s-form-card {
  width: 100%; max-width: 420px;
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: var(--rng-radius-card);
  padding: 40px 36px;
  color: var(--card-text);
  box-shadow: var(--rng-shadow);
}
@media (max-width:480px) { .s-form-card { padding: 28px 20px; } }

.s-mobile-header { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 24px; }
@media (min-width:1024px) { .s-mobile-header { display: none; } }
.s-mobile-logo { width: auto; height: 44px; max-width: 160px; object-fit: contain; }
.s-mobile-title { font-family: var(--rng-font); font-size: 20px; font-weight: 600; color: var(--rng-primary); margin: 0; }
.s-root.dark .s-mobile-title { color: var(--rng-text-on-dark); }

.s-title {
  font-family: var(--rng-font);
  font-size: 26px; font-weight: 600; color: var(--rng-primary);
  margin: 0 0 6px; letter-spacing: -0.01em;
}
.s-root.dark .s-title { color: var(--rng-text-on-dark); }
.s-subtitle { font-size: 14px; color: var(--card-muted); margin: 0 0 28px; }

.s-field { margin-bottom: 18px; }
.s-label {
  display: block; font-size: 13px; font-weight: 500;
  color: var(--card-text); margin-bottom: 6px;
}
.s-input-wrap {
  position: relative; border: 1px solid var(--input-border); border-radius: var(--rng-radius-control);
  background: var(--input-bg);
  transition: border-color .15s ease, box-shadow .15s ease;
}
.s-input-wrap.is-focused {
  border-color: var(--rng-primary);
  box-shadow: var(--rng-focus-ring);
}
.s-root.dark .s-input-wrap.is-focused { border-color: var(--rng-accent); box-shadow: 0 0 0 3px rgb(201 162 75 / 0.25); }
.s-input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--rng-text-muted); pointer-events: none; display: flex; }
.s-root.dark .s-input-icon { color: var(--rng-dark-text-muted); }
.s-input-wrap.is-focused .s-input-icon { color: var(--rng-primary); }
.s-root.dark .s-input-wrap.is-focused .s-input-icon { color: var(--rng-accent); }
.s-input {
  width: 100%; padding: 11px 14px 11px 38px; border: none; border-radius: var(--rng-radius-control);
  background: transparent; font-size: 15px; color: var(--card-text); outline: none;
  box-sizing: border-box; font-family: inherit;
}
.s-input::placeholder { color: var(--rng-placeholder); }

.s-error {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--error-bg); border: 1px solid var(--error-line); color: var(--error-text);
  padding: 10px 12px; border-radius: var(--rng-radius-control); font-size: 13px; margin-bottom: 18px;
}
.s-error svg { flex-shrink: 0; margin-top: 1px; }
.s-fade-enter-active, .s-fade-leave-active { transition: opacity .15s ease, transform .15s ease; }
.s-fade-enter-from, .s-fade-leave-to { opacity: 0; transform: translateY(-4px); }

.s-submit {
  width: 100%; padding: 12px;
  border-radius: var(--rng-radius-control); border: none;
  background: var(--brand-600, var(--rng-primary));
  color: var(--rng-text-on-dark); font-size: 15px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s ease;
}
.s-submit:hover:not(:disabled) { background: var(--brand-700, var(--rng-primary-hover)); }
.s-root.dark .s-submit { background: var(--rng-surface); color: var(--brand-600, var(--rng-primary)); }
.s-root.dark .s-submit:hover:not(:disabled) { background: var(--brand-50, var(--rng-primary-light)); }
.s-submit:disabled { opacity: .65; cursor: not-allowed; }
.s-submit:focus-visible { outline: 2px solid var(--rng-primary); outline-offset: 2px; }
.s-root.dark .s-submit:focus-visible { outline-color: var(--rng-accent); }
.s-spin { animation: s-spin 1s linear infinite; }
@keyframes s-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.s-hint { text-align: center; font-size: 12px; color: var(--card-muted); margin: 16px 0 0; }

@media (prefers-reduced-motion: reduce) {
  .s-anim, .s-float-icon, .s-icon-pulse, .s-logo-ring, .s-spin { animation: none !important; }
}
</style>
