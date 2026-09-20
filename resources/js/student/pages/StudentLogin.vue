<template>
  <div class="s-root" :class="{ dark: isDark }">
    <div class="s-mesh" aria-hidden="true"></div>

    <div class="s-utility-bar">
      <button type="button" class="s-theme-toggle" @click="toggleTheme" :aria-pressed="isDark" aria-label="Toggle dark mode" title="Toggle dark / light mode">
        <svg v-if="!isDark" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg>
      </button>
    </div>

    <div class="s-left">
      <div class="s-left-inner">
        <h1 class="s-brand-title">Student Portal</h1>
        <p class="s-brand-sub">View your attendance, exam results, fees, transport and more — all in one place.</p>
        <ul class="s-feature-list">
          <li class="s-feature"><span class="s-feature-icon">📝</span><span>Exams, marks & report cards</span></li>
          <li class="s-feature"><span class="s-feature-icon">💳</span><span>Fee summary & receipts</span></li>
          <li class="s-feature"><span class="s-feature-icon">🗓️</span><span>Attendance & calendar</span></li>
          <li class="s-feature"><span class="s-feature-icon">🚌</span><span>Transport details</span></li>
        </ul>
      </div>
    </div>

    <div class="s-right">
      <div class="s-form-card">
        <h2 class="s-title">Student Sign In</h2>
        <p class="s-subtitle">Enter your admission number and date of birth to continue.</p>

        <form @submit.prevent="handleLogin" novalidate :aria-busy="loading">
          <div class="s-field">
            <label class="s-label" for="s-adm">Admission No.</label>
            <input id="s-adm" type="text" required class="s-input" placeholder="e.g. 16701" v-model="admissionNo" autocomplete="username" />
          </div>
          <div class="s-field">
            <label class="s-label" for="s-dob">Date of Birth</label>
            <input id="s-dob" type="date" required class="s-input" v-model="dob" autocomplete="bday" />
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
    return { admissionNo: '', dob: '', loading: false, error: null, isDark: false };
  },
  watch: {
    isDark(value) { this.applyTheme(value); },
  },
  mounted() {
    const saved = localStorage.getItem('student-auth-theme');
    this.isDark = saved !== '0';
    this.applyTheme(this.isDark);
  },
  methods: {
    applyTheme(dark) {
      const bg = dark ? '#07080c' : '#f8fafc';
      document.documentElement.style.backgroundColor = bg;
      if (document.body) document.body.style.backgroundColor = bg;
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
.s-root {
  --gold: #4f46e5; --gold-strong: #4338ca; --card-bg: rgba(255,255,255,0.92); --card-border: rgba(15,23,42,0.10);
  --card-text: #0f172a; --card-muted: #475569; --input-bg: #ffffff; --input-border: rgba(15,23,42,0.14);
  --error-bg: #fef2f2; --error-line: #fca5a5; --error-text: #b91c1c;
  position: relative; min-height: 100vh; display: flex; font-family: 'Manrope', ui-sans-serif, sans-serif;
  color: #0f172a; background: #f8fafc; overflow: hidden;
}
.s-root.dark {
  --gold: #c6a75e; --gold-strong: #b89445; --card-bg: rgba(19,20,26,0.82); --card-border: rgba(198,167,94,0.28);
  --card-text: #f3efe6; --card-muted: #a8a29a; --input-bg: rgba(255,255,255,0.04); --input-border: rgba(243,239,230,0.14);
  --error-bg: rgba(180,60,60,0.18); --error-line: rgba(248,180,180,0.35); --error-text: #f6c1c1;
  color: #f3efe6; background: #07080c;
}
.s-mesh { position: absolute; inset: 0; z-index: 0; background: radial-gradient(circle at 18% 18%, #eef2ff 0%, #f8fafc 58%); }
.s-root.dark .s-mesh { background: radial-gradient(circle at 18% 18%, #1a1620 0%, #07080c 58%); }

.s-utility-bar { position: absolute; top: 18px; right: 18px; z-index: 20; background: rgba(255,255,255,0.75); border: 1px solid rgba(15,23,42,0.10); backdrop-filter: blur(12px); border-radius: 999px; padding: 6px; }
.s-root.dark .s-utility-bar { background: rgba(12,12,16,0.55); border-color: rgba(198,167,94,0.28); }
.s-theme-toggle { width: 30px; height: 30px; border-radius: 50%; border: none; background: rgba(79,70,229,0.12); color: #4f46e5; display: flex; align-items: center; justify-content: center; cursor: pointer; }
.s-root.dark .s-theme-toggle { background: rgba(198,167,94,0.16); color: #e2c98a; }

.s-left { position: relative; z-index: 1; display: none; width: 45%; align-items: center; justify-content: center; padding: 48px; }
@media (min-width:1024px) { .s-left { display: flex; } }
.s-left-inner { max-width: 400px; }
.s-brand-title { font-family: 'Cormorant Garamond', serif; font-size: 40px; font-weight: 600; color: #0f172a; margin-bottom: 12px; text-align: center; }
.s-root.dark .s-brand-title { color: #f7f2e8; }
.s-brand-sub { font-size: 15px; color: #475569; line-height: 1.65; margin-bottom: 28px; text-align: center; }
.s-root.dark .s-brand-sub { color: rgba(243,239,230,0.68); }
.s-feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
.s-feature { display: flex; align-items: center; gap: 12px; color: #0f172a; font-size: 14px; font-weight: 500; padding: 11px 14px; border-radius: 12px; background: rgba(255,255,255,0.72); border: 1px solid rgba(15,23,42,0.08); }
.s-root.dark .s-feature { color: #f3efe6; background: rgba(255,255,255,0.04); border-color: rgba(198,167,94,0.16); }
.s-feature-icon { width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.s-right { position: relative; z-index: 1; width: 100%; display: flex; align-items: center; justify-content: center; padding: 32px; }
@media (min-width:1024px) { .s-right { width: 55%; } }
.s-form-card { width: 100%; max-width: 420px; background: var(--card-bg); backdrop-filter: blur(22px); border: 1px solid var(--card-border); border-radius: 20px; padding: 38px 36px; color: var(--card-text); box-shadow: 0 20px 50px rgba(15,23,42,0.10); }
.s-title { font-family: 'Cormorant Garamond', serif; font-size: 30px; font-weight: 600; margin-bottom: 6px; }
.s-subtitle { font-size: 14px; color: var(--card-muted); margin-bottom: 24px; }
.s-field { margin-bottom: 18px; }
.s-label { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--card-muted); margin-bottom: 7px; }
.s-input { width: 100%; padding: 13px 14px; border: 1px solid var(--input-border); border-radius: 10px; background: var(--input-bg); font-size: 14px; color: var(--card-text); outline: none; box-sizing: border-box; }
.s-input:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(79,70,229,0.14); }
.s-root.dark .s-input:focus { box-shadow: 0 0 0 3px rgba(198,167,94,0.16); }
.s-error { display: flex; align-items: flex-start; gap: 8px; background: var(--error-bg); border: 1px solid var(--error-line); color: var(--error-text); padding: 11px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
.s-fade-enter-active, .s-fade-leave-active { transition: opacity .15s ease; }
.s-fade-enter-from, .s-fade-leave-to { opacity: 0; }
.s-submit { position: relative; width: 100%; padding: 14px; border-radius: 10px; border: none; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 10px 28px rgba(79,70,229,0.28); }
.s-root.dark .s-submit { background: linear-gradient(135deg, #d4b56a, #9a7a3a); color: #14110c; box-shadow: 0 10px 28px rgba(198,167,94,0.28); }
.s-submit:disabled { opacity: .65; cursor: not-allowed; }
.s-spin { animation: s-spin 1s linear infinite; }
@keyframes s-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.s-hint { text-align: center; font-size: 12px; color: var(--card-muted); margin-top: 16px; }
</style>
