<template>
  <div class="erp-root" :class="{ dark: isDark }">
    <a class="erp-skip-link" href="#erp-reset-form">Skip to form</a>

    <!-- Aurora + floating blobs backdrop -->
    <div class="erp-mesh" aria-hidden="true">
      <div class="erp-aurora"></div>
      <span class="erp-blob erp-blob-a"></span>
      <span class="erp-blob erp-blob-b"></span>
      <span class="erp-blob erp-blob-c"></span>
      <span class="erp-blob erp-blob-d"></span>
    </div>

    <!-- Floating icons -->
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

    <!-- Theme toggle -->
    <div class="erp-utility-bar">
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
          <img src="/assets/img/logo/new-logo2.png?v=1" alt="ERPSaathi logo" class="erp-logo" />
        </div>
        <h1 class="erp-brand-title erp-anim" style="--d:.08s">School ERP</h1>
        <span class="erp-gold-rule erp-anim" style="--d:.12s" aria-hidden="true"></span>
        <p class="erp-brand-sub erp-anim" style="--d:.16s">Manage students, attendance, fees and reports — all in one place.</p>
        <ul class="erp-feature-list">
          <li class="erp-feature erp-anim" style="--d:.24s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Student & staff management</span></li>
          <li class="erp-feature erp-anim" style="--d:.32s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Attendance tracking</span></li>
          <li class="erp-feature erp-anim" style="--d:.4s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Fee & payment management</span></li>
          <li class="erp-feature erp-anim" style="--d:.48s"><span class="erp-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg></span><span>Reports & analytics</span></li>
        </ul>
      </div>
    </div>

    <!-- Right form panel - Reset Password -->
    <div class="erp-right">
      <div class="erp-form-card erp-anim" style="--d:.1s">
        <div class="erp-mobile-header">
          <img src="/assets/img/logo/new-logo2.png?v=1" alt="ERPSaathi logo" class="erp-mobile-logo" />
          <h2 class="erp-mobile-title">School ERP</h2>
        </div>

        <!-- Invalid/expired token -->
        <div v-if="invalidToken" class="erp-error" role="alert">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
          <span>This reset link is invalid or has expired. Please request a new one.</span>
        </div>

        <!-- Success state -->
        <div v-else-if="submitted" class="erp-success" role="alert">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
          <div>
            <strong>Password updated!</strong>
            <p>Your password has been changed successfully. <a href="/erp/login" style="color:var(--success-text);font-weight:600;">Sign in</a> with your new password.</p>
          </div>
        </div>

        <!-- Form -->
        <form v-else-if="!invalidToken" id="erp-reset-form" @submit.prevent="handleResetPassword" novalidate :aria-busy="loading">
          <h2 class="erp-title">Reset password</h2>
          <p class="erp-subtitle">Enter your new password below.</p>

          <!-- New Password -->
          <div class="erp-field">
            <label class="erp-label" for="erp-new-password">New password</label>
            <div class="erp-input-wrap" :class="{ 'is-focused': focusedField === 'password' }">
              <span class="erp-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
              </span>
              <input id="erp-new-password" ref="passwordInput" :type="showPassword ? 'text' : 'password'" required class="erp-input erp-input-password" placeholder="Enter new password" v-model="password" autocomplete="new-password" @focus="focusedField = 'password'" @blur="focusedField = null" />
              <button type="button" class="erp-toggle-visibility" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" /><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" /><line x1="2" x2="22" y1="2" y2="22" /></svg>
              </button>
            </div>
          </div>

          <!-- Confirm Password -->
          <div class="erp-field">
            <label class="erp-label" for="erp-confirm-password">Confirm new password</label>
            <div class="erp-input-wrap" :class="{ 'is-focused': focusedField === 'confirm' }">
              <span class="erp-input-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
              </span>
              <input id="erp-confirm-password" :type="showConfirm ? 'text' : 'password'" required class="erp-input erp-input-password" placeholder="Confirm new password" v-model="confirmPassword" autocomplete="new-password" @focus="focusedField = 'confirm'" @blur="focusedField = null" />
              <button type="button" class="erp-toggle-visibility" @click="showConfirm = !showConfirm" :aria-label="showConfirm ? 'Hide confirm' : 'Show confirm'">
                <svg v-if="!showConfirm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" /><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" /><line x1="2" x2="22" y1="2" y2="22" /></svg>
              </button>
            </div>
          </div>

          <!-- Error -->
          <transition name="erp-fade">
            <div v-if="error" class="erp-error" role="alert" aria-live="assertive">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
              <span>{{ error }}</span>
            </div>
          </transition>

          <button type="submit" class="erp-submit" :disabled="loading">
            <svg v-if="loading" class="erp-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56" /></svg>
            <span>{{ loading ? 'Updating...' : 'Update Password' }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: "ErpResetPassword",
  data() {
    return {
      password: "",
      confirmPassword: "",
      showPassword: false,
      showConfirm: false,
      loading: false,
      error: null,
      submitted: false,
      invalidToken: false,
      focusedField: null,
      isDark: true,
      token: "",
      email: "",
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
      saved = localStorage.getItem("erp-auth-theme");
    } catch (e) {
      saved = null;
    }
    this.isDark = saved === null ? true : saved === "dark";
    this.applyTheme(this.isDark);

    const params = new URLSearchParams(window.location.search);
    this.token = params.get("token") || "";
    this.email = params.get("email") || "";

    if (!this.token || !this.email) {
      this.invalidToken = true;
    }
  },
  beforeUnmount() {
    document.documentElement.style.removeProperty('background-color');
    if (document.body) document.body.style.removeProperty('background-color');
  },
  methods: {
    applyTheme(dark) {
      const bg = dark ? 'var(--rng-primary)' : 'var(--rng-bg)';
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
    async handleResetPassword() {
      this.error = null;

      if (!this.password || !this.confirmPassword) {
        this.error = "Please fill in both password fields.";
        return;
      }

      if (this.password.length < 6) {
        this.error = "Password must be at least 6 characters.";
        return;
      }

      if (this.password !== this.confirmPassword) {
        this.error = "Passwords do not match.";
        return;
      }

      this.loading = true;
      try {
        const response = await fetch('/erp/update-password', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: JSON.stringify({
            email: this.email,
            token: this.token,
            password: this.password,
            password_confirmation: this.confirmPassword,
          }),
        });

        const data = await response.json();

        if (data.success) {
          this.submitted = true;
        } else {
          this.error = data.message || 'Unable to reset password. The link may have expired.';
        }
      } catch (err) {
        this.error = 'A network error occurred. Please try again.';
      } finally {
        this.loading = false;
      }
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
  background: var(--rng-primary);
  color: var(--rng-text-on-dark);
}
@media (min-width:1024px) { .erp-left { display: flex; } }
.erp-left-inner { max-width: 420px; width: 100%; }
.erp-logo-ring {
  display: inline-flex; align-items: center; justify-content: center;
  margin: 0 0 32px;
  padding: 14px 20px;
  background: var(--rng-surface);
  border-radius: var(--rng-radius-card);
}
.erp-logo-ring::before { display: none; }
.erp-logo { display: block; width: auto; height: 64px; max-width: 220px; object-fit: contain; }

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
  width: 24px; height: 24px; border-radius: 50%;
  background: rgb(255 255 255 / 0.1);
  color: var(--rng-text-on-dark);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.erp-feature-icon svg { width: 14px; height: 14px; }

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
  background: var(--rng-primary);
  color: var(--rng-text-on-dark); font-size: 15px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s ease;
}
.erp-submit:hover:not(:disabled) { background: var(--rng-primary-hover); }
.erp-root.dark .erp-submit { background: var(--rng-surface); color: var(--rng-primary); }
.erp-root.dark .erp-submit:hover:not(:disabled) { background: var(--rng-primary-light); }
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

/* Success notice + "Back to login" (forgot / reset password) */
.erp-success {
  display: flex; align-items: flex-start; gap: 10px;
  background: var(--rng-success-bg); border: 1px solid var(--rng-success-bg); color: var(--rng-success-text);
  padding: 14px 16px; border-radius: var(--rng-radius-control); font-size: 13px; line-height: 1.5;
}
.erp-root.dark .erp-success { background: rgb(22 163 74 / 0.18); border-color: rgb(22 163 74 / 0.4); color: var(--rng-success-on-dark); }
.erp-success svg { flex-shrink: 0; }
.erp-success strong { display: block; margin-bottom: 4px; }
.erp-back-link { text-align: center; margin-top: 22px; }
.erp-back-link a {
  display: inline-flex; align-items: center; gap: 6px;
  color: var(--accent); font-size: 13px; font-weight: 500; text-decoration: none;
}
.erp-back-link a:hover { text-decoration: underline; text-underline-offset: 3px; }
.erp-back-link a:focus-visible { outline: 2px solid var(--rng-primary); outline-offset: 2px; }
</style>
