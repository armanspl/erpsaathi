<template>
  <div class="erp-site" :class="{ dark: isDark, 'lang-hi': locale === 'hi' }" :lang="locale" id="top">
    <div class="erp-progress-bar" :style="{ width: scrollProgress + '%' }" aria-hidden="true"></div>
    <a class="erp-skip" href="#hero-content">{{ c.skipLink }}</a>

    <!-- ============ Navbar ============ -->
    <header class="erp-nav-bar" :class="{ 'is-scrolled': isScrolled, 'is-hidden': headerHidden }">
      <div class="erp-container erp-nav-inner">
        <a href="#top" class="erp-brand" aria-label="ERPSaathi home">
          <img :src="logoUrl" alt="ERPSaathi logo" class="erp-brand-logo" />
        </a>

        <nav class="erp-nav-links" aria-label="Primary">
          <a v-for="link in c.navLinks" :key="link.href" :href="link.href">{{ link.label }}</a>
        </nav>

        <div class="erp-nav-utility">
          <div class="erp-lang-switch">
            <button
              type="button"
              class="erp-lang-toggle"
              @click="toggleLocale"
              :aria-label="locale === 'en' ? 'Switch to Hindi' : 'Switch to English'"
              :title="locale === 'en' ? 'हिंदी' : 'English'"
            >
              {{ locale === 'en' ? 'हिंदी' : 'English' }}
            </button>
          </div>
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

          <button
            type="button"
            class="erp-hamburger"
            :class="{ 'is-open': mobileMenuOpen }"
            @click="mobileMenuOpen = !mobileMenuOpen"
            :aria-expanded="mobileMenuOpen"
            aria-label="Toggle menu"
          >
            <span></span><span></span><span></span>
          </button>
        </div>

        <div class="erp-nav-actions">
          <a :href="primaryHref" class="erp-nav-login">{{ primaryLabel }}</a>
          <a :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-sm">{{ c.navRequestDemo }}</a>
        </div>
      </div>

      <Transition name="erp-mobile-menu">
        <div v-if="mobileMenuOpen" class="erp-mobile-menu">
          <nav aria-label="Mobile">
            <a v-for="link in c.navLinks" :key="'m-' + link.href" :href="link.href" @click="mobileMenuOpen = false">{{ link.label }}</a>
            <a :href="primaryHref">{{ primaryLabel }}</a>
          </nav>
          <a :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-block">{{ c.navRequestDemo }}</a>
        </div>
      </Transition>
    </header>
    <div class="erp-nav-spacer" aria-hidden="true"></div>

    <main id="hero-content">
      <!-- ============ Hero ============ -->
      <section class="erp-hero">
        <div class="erp-hero-decor" aria-hidden="true">
          <span class="erp-blob erp-blob-a"></span>
          <span class="erp-blob erp-blob-b"></span>
          <span class="erp-dot-grid"></span>
        </div>

        <div class="erp-container erp-hero-grid">
          <div class="erp-hero-copy" :class="{ 'is-in': heroIn }">
            <p class="erp-hero-brand">ERPSaathi</p>
            <p class="erp-hero-kicker">{{ c.heroPillSuffix }}</p>
            <h1 class="erp-hero-title">
              <template v-if="c.heroTitleBefore">{{ c.heroTitleBefore }} </template><span class="erp-brand-word">{{ c.heroTitleHighlight }}</span><template v-if="c.heroTitleAfter"> {{ c.heroTitleAfter }}</template>
            </h1>
            <p class="erp-hero-sub">{{ c.heroSub }}</p>
            <div class="erp-hero-actions">
              <a :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-lg">
                {{ c.heroCtaPrimary }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </a>
              <a href="#features" class="erp-btn erp-btn-ghost erp-btn-lg">{{ c.heroCtaSecondary }}</a>
            </div>
          </div>

          <div
            class="erp-hero-visual"
            :class="{ 'is-in': heroIn }"
            @mouseenter="pauseHeroSlider"
            @mouseleave="resumeHeroSlider"
          >
            <figure class="erp-hero-shot">
              <div class="erp-hero-slides">
                <img
                  v-for="(slide, i) in heroSlides"
                  :key="slide.key"
                  :src="slide.image"
                  :alt="'ERPSaathi ' + slide.label"
                  class="erp-hero-shot-img"
                  :class="{ 'is-active': i === heroSlideIndex, 'is-prev': i === heroPrevSlideIndex }"
                  :width="i === 0 ? 1280 : 960"
                  :height="i === 0 ? 720 : 600"
                  :loading="i === 0 ? 'eager' : 'lazy'"
                  :fetchpriority="i === 0 ? 'high' : 'low'"
                  decoding="async"
                />
              </div>
              <div class="erp-hero-slide-meta" aria-hidden="true">
                <span class="erp-hero-slide-label">{{ activeHeroSlide.label }}</span>
                <div class="erp-hero-slide-dots">
                  <button
                    v-for="(slide, i) in heroSlides"
                    :key="'dot-' + slide.key"
                    type="button"
                    class="erp-hero-slide-dot"
                    :class="{ 'is-active': i === heroSlideIndex }"
                    :aria-label="'Show ' + slide.label"
                    @click="goHeroSlide(i)"
                  ></button>
                </div>
              </div>
            </figure>
          </div>
        </div>
      </section>

      <!-- ============ Trust / Proof ============ -->
      <section class="erp-section erp-trust-section">
        <div class="erp-container">
          <ul class="erp-trust-bar" aria-label="ERPSaathi capabilities">
            <li
              v-for="(item, i) in c.trustItems"
              :key="item"
              v-reveal
              :style="{ transitionDelay: (i * 90) + 'ms' }"
            >
              <div class="erp-trust-chip" :style="{ '--trust-i': i }">
                <span class="erp-trust-dot" aria-hidden="true"></span>
                <span class="erp-trust-label">{{ item }}</span>
              </div>
            </li>
          </ul>
        </div>
      </section>

      <!-- ============ Problem → Solution ============ -->
      <section class="erp-section erp-problem-section">
        <div class="erp-container">
          <div class="erp-section-head" v-reveal>
            <p class="erp-eyebrow">{{ c.problemEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.problemTitle }}</h2>
            <p class="erp-section-lead">{{ c.problemLead }}</p>
          </div>
          <div class="erp-compare">
            <div class="erp-compare-col erp-compare-before" v-reveal>
              <p class="erp-compare-heading">{{ c.compareBeforeHeading }}</p>
              <ul>
                <li
                  v-for="(item, i) in c.problems"
                  :key="item"
                  class="erp-compare-item"
                  :style="{ '--item-i': i }"
                >
                  <span class="erp-compare-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                  </span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
            <div class="erp-compare-arrow" aria-hidden="true" v-reveal>
              <span class="erp-compare-arrow-orb">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </span>
            </div>
            <div class="erp-compare-col erp-compare-after" v-reveal style="transition-delay: 120ms">
              <p class="erp-compare-heading">{{ c.compareAfterHeading }}</p>
              <ul>
                <li
                  v-for="(item, i) in c.solutions"
                  :key="item"
                  class="erp-compare-item"
                  :style="{ '--item-i': i }"
                >
                  <span class="erp-compare-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                  </span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ Feature grid ============ -->
      <section class="erp-section erp-features" id="features">
        <div class="erp-container">
          <div class="erp-section-head erp-features-head" v-reveal>
            <p class="erp-eyebrow">{{ c.featuresEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.featuresTitle }}</h2>
            <p class="erp-section-lead">{{ c.featuresLead }}</p>
          </div>
          <div class="erp-feature-grid">
            <article
              class="erp-feature-card"
              v-for="(feature, idx) in c.features"
              :key="feature.title"
              v-reveal
              :style="{ transitionDelay: (idx % 4) * 80 + 'ms' }"
            >
              <div class="erp-feature-chip" :style="{ '--feat-i': idx }">
                <div class="erp-feature-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path v-for="(d, i) in featureIcons[idx]" :key="i" :d="d" />
                  </svg>
                </div>
                <h3>{{ feature.title }}</h3>
                <p>{{ feature.desc }}</p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <!-- ============ Module showcase ============ -->
      <section class="erp-section erp-showcase" id="modules">
        <div class="erp-container">
          <div class="erp-section-head erp-showcase-head">
            <p class="erp-eyebrow">{{ c.showcaseEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.showcaseTitle }}</h2>
          </div>
          <div class="erp-showcase-layout" @mouseenter="pauseSlider" @mouseleave="resumeSlider">
            <div class="erp-showcase-nav" role="tablist" aria-label="Modules">
              <button
                type="button"
                v-for="(mod, i) in c.showcaseModules"
                :key="showcaseMeta[i].key"
                class="erp-showcase-nav-item"
                :class="{ 'is-active': i === slideIndex }"
                role="tab"
                :aria-selected="i === slideIndex"
                @click="goSlide(i)"
              >
                <span class="erp-showcase-nav-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path v-for="(d, di) in showcaseIcons[i]" :key="di" :d="d" />
                  </svg>
                </span>
                <span class="erp-showcase-nav-text">
                  <span class="erp-showcase-nav-title">{{ mod.title }}</span>
                  <span class="erp-showcase-nav-desc">{{ mod.short }}</span>
                  <span class="erp-showcase-nav-bar" v-if="i === slideIndex">
                    <span></span>
                  </span>
                </span>
              </button>
            </div>

            <div class="erp-showcase-frame">
              <div class="erp-mock-bar erp-showcase-frame-bar">
                <span></span><span></span><span></span>
                <p>{{ activeModule.title }}</p>
              </div>
              <div class="erp-showcase-viewport">
                <img
                  v-for="(mod, i) in c.showcaseModules"
                  :key="'shot-' + showcaseMeta[i].key"
                  :src="showcaseMeta[i].image"
                  :alt="mod.title"
                  class="erp-showcase-img"
                  :class="{ 'is-active': i === slideIndex, 'is-prev': i === prevSlideIndex }"
                  loading="lazy"
                  decoding="async"
                />
              </div>
              <Transition name="erp-caption-fade" mode="out-in">
                <p class="erp-showcase-caption" :key="slideIndex">{{ activeModule.desc }}</p>
              </Transition>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ UDISE+ ============ -->
      <section class="erp-section erp-udise">
        <div class="erp-container erp-udise-grid">
          <div class="erp-udise-copy" v-reveal>
            <p class="erp-eyebrow">{{ c.udiseEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.udiseTitle }}</h2>
            <p class="erp-section-lead">{{ c.udiseLead }}</p>
            <div class="erp-udise-caps">
              <article v-for="cap in c.udiseCaps" :key="cap.title" class="erp-udise-cap">
                <h3>{{ cap.title }}</h3>
                <p>{{ cap.desc }}</p>
              </article>
            </div>
            <p class="erp-udise-proof">{{ c.udiseProof }}</p>
          </div>
          <div class="erp-udise-visual" v-reveal>
            <figure class="erp-udise-shot">
              <div class="erp-udise-shot-chrome" aria-hidden="true">
                <span></span><span></span><span></span>
                <p>erpsaathi.com/erp/udise</p>
              </div>
              <img
                :src="udiseImage"
                alt="UDISE+ S02 form in ERPSaathi"
                class="erp-udise-shot-img"
                loading="lazy"
                decoding="async"
              />
            </figure>
          </div>
        </div>
      </section>

      <!-- ============ Why ERPSaathi ============ -->
      <section class="erp-section erp-why" id="why">
        <div class="erp-container">
          <div class="erp-section-head">
            <p class="erp-eyebrow">{{ c.whyEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.whyTitle }}</h2>
          </div>
          <div class="erp-why-grid">
            <article
              class="erp-why-card"
              v-for="(item, idx) in c.whyItems"
              :key="item.title"
              v-reveal
              :style="{ transitionDelay: (idx % 4) * 70 + 'ms' }"
            >
              <div class="erp-why-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                  <path v-for="(d, i) in whyIcons[idx]" :key="i" :d="d" />
                </svg>
              </div>
              <h3>{{ item.title }}</h3>
              <p>{{ item.desc }}</p>
            </article>
          </div>
        </div>
      </section>

      <!-- ============ How it works ============ -->
      <section class="erp-section erp-steps">
        <div class="erp-container">
          <div class="erp-section-head">
            <p class="erp-eyebrow">{{ c.stepsEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.stepsTitle }}</h2>
            <p class="erp-section-lead">{{ c.stepsLead }}</p>
          </div>
          <div class="erp-steps-row">
            <span class="erp-steps-line" aria-hidden="true"></span>
            <div class="erp-step" v-for="(step, idx) in c.steps" :key="step.title" v-reveal :style="{ transitionDelay: idx * 120 + 'ms' }">
              <span class="erp-step-num">0{{ idx + 1 }}</span>
              <h3>{{ step.title }}</h3>
              <p>{{ step.desc }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ About ============ -->
      <section class="erp-section erp-about" id="about">
        <div class="erp-container erp-about-inner" v-reveal>
          <p class="erp-eyebrow">{{ c.aboutEyebrow }}</p>
          <p class="erp-about-text">{{ c.aboutText }}</p>
          <ul class="erp-about-chips">
            <li v-for="item in c.securityPoints" :key="item">{{ item }}</li>
          </ul>
        </div>
      </section>

      <!-- ============ FAQ ============ -->
      <section class="erp-section erp-faq-section">
        <div class="erp-container">
          <div class="erp-section-head">
            <p class="erp-eyebrow">{{ c.faqEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.faqTitle }}</h2>
          </div>
          <div class="erp-faq">
            <div
              class="erp-faq-item"
              v-for="(faq, idx) in c.faqs"
              :key="faq.q"
              :class="{ 'is-open': faqOpen === idx }"
              v-reveal
              :style="{ transitionDelay: (idx % 3) * 60 + 'ms' }"
            >
              <button type="button" class="erp-faq-q" :aria-expanded="faqOpen === idx" @click="toggleFaq(idx)">
                <span>{{ faq.q }}</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="erp-faq-chevron"><path d="m6 9 6 6 6-6" /></svg>
              </button>
              <div class="erp-faq-a-wrap">
                <div class="erp-faq-a">
                  <p>{{ faq.a }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ Final CTA ============ -->
      <section class="erp-cta" id="contact">
        <div class="erp-container erp-cta-inner" v-reveal>
          <div class="erp-cta-copy">
            <h2 class="erp-cta-title">{{ c.ctaTitle }}</h2>
            <p class="erp-cta-sub">{{ c.ctaSub }}</p>
          </div>
          <div class="erp-cta-actions">
            <a :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-lg">{{ c.ctaPrimary }}</a>
            <a :href="whatsappUrl" target="_blank" rel="noopener" class="erp-btn erp-btn-ghost erp-btn-lg">{{ c.ctaSecondary }}</a>
          </div>
        </div>
      </section>
    </main>

    <!-- ============ Footer ============ -->
    <footer class="erp-footer">
      <div class="erp-container erp-footer-grid">
        <div class="erp-footer-brand">
          <img :src="logoUrl" alt="ERPSaathi logo" class="erp-brand-logo" />
          <p class="erp-footer-note">{{ c.footerTagline }}</p>
        </div>

        <nav class="erp-footer-col" aria-label="Site">
          <h4>{{ c.footerLinksHeading }}</h4>
          <a href="#top">{{ c.footerLinks.home }}</a>
          <a href="#features">{{ c.footerLinks.features }}</a>
          <a href="#modules">{{ c.footerLinks.modules }}</a>
          <a href="#about">{{ c.footerLinks.about }}</a>
          <a href="#contact">{{ c.footerLinks.contact }}</a>
          <a :href="tryDemoUrl">{{ c.footerLinks.requestDemo }}</a>
        </nav>

        <nav class="erp-footer-col" aria-label="Product">
          <h4>{{ c.footerProductHeading }}</h4>
          <a href="#modules">{{ c.footerProduct.admissions }}</a>
          <a href="#modules">{{ c.footerProduct.students }}</a>
          <a href="#modules">{{ c.footerProduct.attendance }}</a>
          <a href="#modules">{{ c.footerProduct.fees }}</a>
          <a href="#modules">{{ c.footerProduct.exams }}</a>
          <a href="#modules">{{ c.footerProduct.reports }}</a>
        </nav>

        <nav class="erp-footer-col" aria-label="Legal">
          <h4>{{ c.footerLegalHeading }}</h4>
          <a href="/privacy-policy">{{ c.footerPrivacy }}</a>
          <a href="/terms">{{ c.footerTerms }}</a>
        </nav>

        <div class="erp-footer-col">
          <h4>{{ c.footerContactHeading }}</h4>
          <p class="erp-footer-contact-name">{{ c.footerContactNote }}</p>
          <p class="erp-footer-contact-person">{{ whatsappContact }}</p>
          <a :href="whatsappUrl" target="_blank" rel="noopener" class="erp-footer-whatsapp">
            <span>WhatsApp:</span> <span class="erp-footer-phone">+91 99428 82661</span>
          </a>
        </div>
      </div>
      <div class="erp-container erp-footer-bottom">
        <span>&copy; {{ year }} ERPSaathi. {{ c.footerCopyright }}</span>
        <span>{{ c.footerSystemLabel }}</span>
      </div>
    </footer>

    <!-- ============ WhatsApp floating button ============ -->
    <a class="erp-whatsapp-fab" :href="whatsappUrl" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
      <span class="erp-whatsapp-ring" aria-hidden="true"></span>
      <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12.02 2C6.5 2 2.02 6.48 2.02 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38A9.94 9.94 0 0 0 12.02 22C17.54 22 22 17.52 22 12S17.54 2 12.02 2Zm5.84 14.2c-.25.7-1.45 1.35-2 1.44-.53.09-1.16.13-1.87-.12a12.9 12.9 0 0 1-1.86-.7c-3.28-1.42-5.4-4.72-5.56-4.95-.16-.23-1.33-1.77-1.33-3.37s.83-2.39 1.13-2.72c.3-.32.65-.4.87-.4h.62c.2 0 .47-.08.73.56.27.65.9 2.24.98 2.4.08.16.13.35.02.57-.1.23-.15.36-.3.55-.16.2-.33.44-.47.6-.16.16-.32.34-.14.66.19.32.84 1.38 1.8 2.24 1.24 1.1 2.28 1.44 2.6 1.6.32.16.5.14.7-.08.2-.23.83-.97 1.06-1.3.22-.32.44-.27.74-.16.3.11 1.9.9 2.23 1.06.32.16.53.24.6.38.08.14.08.79-.17 1.5Z" />
      </svg>
      <span class="erp-whatsapp-tooltip">{{ c.whatsappTooltip }}</span>
    </a>
  </div>
</template>

<script>
const DEFAULTS = {
  authenticated: false,
  loginUrl: '/erp/login',
  dashboardUrl: '/erp/dashboard',
  tryDemoUrl: '/erp/demo',
  schoolName: 'ERPSaathi',
  logoUrl: '/assets/img/logo/erpsaathi.png',
};

/* Non-translatable structural data, paired by array index with content.features / content.showcaseModules. */
const FEATURE_ICONS = [
  ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
  ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'M9 10h6M9 14h4'],
  ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M23 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
  ['M9 12h6M9 16h6M9 8h1', 'M4 4h11l5 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z'],
  ['M3 4h18v18H3z', 'M16 2v4M8 2v4M3 10h18', 'm9 16 2 2 4-4'],
  ['M3 7h18v12H3z', 'M3 10h18', 'M7 15h4'],
  ['M3 21h18', 'M5 21V9l7-5 7 5v12', 'M9 21v-6h6v6'],
  ['M22 10 12 5 2 10l10 5 10-5Z', 'M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5'],
  ['M4 19.5A2.5 2.5 0 0 1 6.5 17H20', 'M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z'],
  ['M8 6v6M16 6v6', 'M2 12h20l-1 8H3l-1-8Z', 'M5 18a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM19 18a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z', 'M2 12V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4'],
  ['M3 3v18h18', 'M7 16v-4M12 16V8M17 16v-7'],
  ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'M9 13h6M9 17h6'],
  ['m17 2 4 4-4 4', 'M3 11V9a4 4 0 0 1 4-4h14', 'm7 22-4-4 4-4', 'M21 13v2a4 4 0 0 1-4 4H3'],
];

const SHOWCASE_META = [
  { key: 'academics', image: '/assets/img/modules/module-academics.jpg' },
  { key: 'admissions', image: '/assets/img/modules/module-admissions.jpg' },
  { key: 'attendance', image: '/assets/img/modules/module-attendance.jpg' },
  { key: 'fees', image: '/assets/img/modules/module-fees.jpg' },
  { key: 'finance', image: '/assets/img/modules/module-finance.jpg' },
  { key: 'exams', image: '/assets/img/modules/module-exams.jpg' },
  { key: 'transport', image: '/assets/img/modules/module-transport.jpg' },
  { key: 'reports', image: '/assets/img/modules/module-reports.jpg' },
];

const HERO_SLIDES = [
  { key: 'dashboard', image: '/assets/img/dashboard/erpsaathi-hero-dashboard.png', label: 'Dashboard' },
  { key: 'academics', image: '/assets/img/dashboard/hero-academics.png', label: 'Academics' },
  { key: 'admissions', image: '/assets/img/dashboard/hero-admissions.png', label: 'Admissions' },
  { key: 'attendance', image: '/assets/img/dashboard/hero-attendance.png', label: 'Attendance' },
  { key: 'fees', image: '/assets/img/dashboard/hero-fees.png', label: 'Fees' },
  { key: 'finance', image: '/assets/img/dashboard/hero-finance.png', label: 'Finance' },
  { key: 'exams', image: '/assets/img/dashboard/hero-exams.png', label: 'Examination' },
  { key: 'transport', image: '/assets/img/dashboard/hero-transport.png', label: 'Transport' },
];

const SHOWCASE_ICON_MAP = [7, 3, 4, 5, 6, 8, 9, 10];
const SHOWCASE_ICONS = SHOWCASE_ICON_MAP.map((i) => FEATURE_ICONS[i]);
/* Feature cards (no UDISE — covered in dedicated section): Students, Admissions, Attendance, Fees, Academics, Exams, Finance, Transport */
const FEATURE_CARD_ICON_MAP = [2, 3, 4, 5, 7, 8, 6, 9];
const FEATURE_CARD_ICONS = FEATURE_CARD_ICON_MAP.map((i) => FEATURE_ICONS[i]);

const WHY_ICONS = [
  ['M15 7h3a5 5 0 0 1 5 5 5 5 0 0 1-5 5h-3', 'M9 17H6a5 5 0 0 1-5-5 5 5 0 0 1 5-5h3', 'M8 12h8'],
  ['M3 3h7v7H3z', 'M14 3h7v7h-7z', 'M14 14h7v7h-7z', 'M3 14h7v7H3z'],
  ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
  ['M3 3v18h18', 'M7 16v-4M12 16V8M17 16v-7'],
];

const CONTENT_EN = {
  skipLink: 'Skip to content',
  navLinks: [
    { label: 'Home', href: '#top' },
    { label: 'Features', href: '#features' },
    { label: 'Modules', href: '#modules' },
    { label: 'Why ERPSaathi', href: '#why' },
    { label: 'About', href: '#about' },
    { label: 'Contact', href: '#contact' },
  ],
  navRequestDemo: 'Request a Demo',

  heroPillSuffix: 'School ERP for India',
  heroTitleBefore: 'One system for',
  heroTitleHighlight: 'every school department',
  heroTitleAfter: '',
  heroSub: 'Students, academics, attendance, fees, finance, transport, exams and reports — all from one secure school ERP.',
  heroCtaPrimary: 'Request a Demo',
  heroCtaSecondary: 'Explore Features',

  trustItems: [
    '13+ Modules',
    'One Database',
    'Audit Logging',
    'Role-Based Access',
  ],

  problemEyebrow: 'The problem',
  problemTitle: 'Schools Still Run on Disconnected Tools',
  problemLead: 'Academics, fees and admissions often live in separate systems — so records drift, work repeats, and nobody has one clear picture.',
  compareBeforeHeading: 'Before ERPSaathi',
  compareAfterHeading: 'With ERPSaathi',
  problems: [
    'Multiple disconnected systems for admissions, fees and academics',
    'Hard to track pending and partial fee payments',
    'Student data scattered across registers and spreadsheets',
    'Reports that take hours to compile by hand',
  ],
  solutions: [
    'One connected platform for every department',
    'Real-time fee dues, receipts and collection reports',
    'A single student record shared across every module',
    'Instant reports, filtered the way you need',
  ],

  featuresEyebrow: 'Capabilities',
  featuresTitle: 'Capabilities at a Glance',
  featuresLead: 'A high-level map of what ERPSaathi covers. Explore each module in detail below.',
  features: [
    { title: 'Student Management', desc: 'Profiles, families and session history in one record.' },
    { title: 'Admissions', desc: 'Enquiry to registration to admission in one flow.' },
    { title: 'Attendance', desc: 'Daily student and staff attendance with summaries.' },
    { title: 'Fee Management', desc: 'Structures, dues, collection and printable receipts.' },
    { title: 'Academics', desc: 'Classes, sections, subjects and homework together.' },
    { title: 'Examination', desc: 'Schedules, marks, results and report cards.' },
    { title: 'Finance & Payroll', desc: 'Expenses, salary slips and bank control.' },
    { title: 'Transport', desc: 'Routes, vehicles and drivers linked to students.' },
  ],

  showcaseEyebrow: 'Product tour',
  showcaseTitle: 'See ERPSaathi in Action',
  showcaseModules: [
    { title: 'Academics', short: 'Classes & subjects', desc: 'Classes, sections, subjects and homework in one structured hierarchy.' },
    { title: 'Admissions', short: 'Enquiry to admit', desc: 'Enquiry to registration to admission — one pipeline with shared student records.' },
    { title: 'Attendance', short: 'Daily tracking', desc: 'Daily attendance for students and staff, with leave and monthly summaries.' },
    { title: 'Fee Management', short: 'Dues & receipts', desc: 'Structures, dues, collection and accounting-ready fee history.' },
    { title: 'Finance & Payroll', short: 'Expenses & salary', desc: 'Office expenses, salary slips, bank accounts and cash control.' },
    { title: 'Examination', short: 'Marks & results', desc: 'Schedules, marks, results, admit cards and report cards.' },
    { title: 'Transport', short: 'Routes & vehicles', desc: 'Routes, stops, vehicles and drivers linked to student transport.' },
    { title: 'Reports', short: 'Export ready', desc: 'Class-wise, area-wise and finance reports with controlled export.' },
  ],

  udiseEyebrow: 'Built for Indian schools',
  udiseTitle: 'UDISE+ Compliance Without Duplicate Work',
  udiseLead: 'Prepare forms and maintain registry status from the same student records already in ERPSaathi.',
  udiseCaps: [
    { title: 'UDISE+ S02', desc: 'Prepare and print forms for students not yet enrolled on UDISE.' },
    { title: 'UDISE+ S03', desc: 'Maintain the student registry, PEN and enrolment status.' },
  ],
  udiseProof: 'No separate spreadsheet.',

  whyEyebrow: 'Why ERPSaathi',
  whyTitle: 'Built for How Schools Actually Work',
  whyItems: [
    { title: 'One shared student record', desc: 'Every department reads the same student data — no scattered files.' },
    { title: 'One login for every department', desc: 'Admissions, fees, academics, exams and transport in one platform.' },
    { title: 'Secure by role', desc: 'Staff see only the modules and actions their role allows.' },
    { title: 'Reports without spreadsheet work', desc: 'Class, fee and operations reports from live school data.' },
  ],

  stepsEyebrow: 'Getting started',
  stepsTitle: 'How It Works',
  stepsLead: 'Setup → Run → Grow — from day one to daily operations.',
  steps: [
    { title: 'Setup', desc: 'Configure school info, branches, users and academic sessions.' },
    { title: 'Run', desc: 'Manage students, attendance, fees, academics and exams every day.' },
    { title: 'Grow', desc: 'Use reports and shared records to run the school with clarity.' },
  ],

  aboutEyebrow: 'About ERPSaathi',
  aboutText: 'ERPSaathi is a school ERP built for Indian schools. It connects admissions, academics, attendance, fees, examinations, finance, transport and UDISE+ in one platform — so every module works from the same student records.',
  securityPoints: [
    'Role-based access',
    'Session authentication',
    'Audit logging',
    'Centralized database',
  ],

  faqEyebrow: 'FAQ',
  faqTitle: 'Questions School Owners Ask',
  faqs: [
    { q: 'What is ERPSaathi?', a: 'A school ERP that connects admissions, academics, attendance, fees, exams, finance, transport and UDISE+ in one system.' },
    { q: 'Which departments can use it?', a: 'Administrators, teachers, accounts and transport staff — each with role-based access to only what they need.' },
    { q: 'Does it support UDISE+?', a: 'Yes. S02 and S03 workflows, PEN tracking and enrolment status are built in and stay synced with student data.' },
    { q: 'Can staff have different permissions?', a: 'Yes. Access is role-based and configurable per module and action.' },
    { q: 'Can we try a demo?', a: 'Yes. Request a Demo opens a working sample school with sample data — no signup required.' },
    { q: 'Does it fit small and large schools?', a: 'Yes. Branches, sessions, classes and sections scale from one campus to multi-branch schools.' },
  ],

  ctaTitle: 'Ready to Run Your School on One System?',
  ctaSub: 'Explore a live demo school, or talk to us about getting ERPSaathi set up for yours.',
  ctaPrimary: 'Request a Demo',
  ctaSecondary: 'Talk to Us',

  footerTagline: 'One system for every school department.',
  footerLinksHeading: 'Links',
  footerLinks: { home: 'Home', features: 'Features', modules: 'Modules', about: 'About', contact: 'Contact', requestDemo: 'Request Demo' },
  footerProductHeading: 'Product',
  footerProduct: { admissions: 'Admissions', students: 'Students', attendance: 'Attendance', fees: 'Fees', exams: 'Exams', reports: 'Reports' },
  footerLegalHeading: 'Legal',
  footerPrivacy: 'Privacy Policy',
  footerTerms: 'Terms & Conditions',
  footerContactHeading: 'Contact',
  footerContactNote: 'Sales & Support',
  footerCopyright: 'All rights reserved.',
  footerSystemLabel: 'School ERP System',

  whatsappTooltip: 'Chat on WhatsApp',
};

const CONTENT_HI = {
  skipLink: 'सीधे सामग्री पर जाएं',
  navLinks: [
    { label: 'होम', href: '#top' },
    { label: 'विशेषताएँ', href: '#features' },
    { label: 'मॉड्यूल', href: '#modules' },
    { label: 'ERPSaathi क्यों', href: '#why' },
    { label: 'हमारे बारे में', href: '#about' },
    { label: 'संपर्क करें', href: '#contact' },
  ],
  navRequestDemo: 'डेमो के लिए अनुरोध करें',

  heroPillSuffix: 'भारत के लिए स्कूल ERP',
  heroTitleBefore: '',
  heroTitleHighlight: 'हर स्कूल विभाग',
  heroTitleAfter: 'के लिए एकीकृत सिस्टम',
  heroSub: 'छात्र, शैक्षणिक कार्य, उपस्थिति, फीस, वित्त, परिवहन, परीक्षा और रिपोर्ट — एक सुरक्षित स्कूल ERP से।',
  heroCtaPrimary: 'डेमो के लिए अनुरोध करें',
  heroCtaSecondary: 'विशेषताएँ देखें',

  trustItems: [
    '13+ मॉड्यूल',
    'एक डेटाबेस',
    'ऑडिट लॉगिंग',
    'भूमिका-आधारित पहुँच',
  ],

  problemEyebrow: 'समस्या',
  problemTitle: 'स्कूल अभी भी बिखरे हुए टूल पर चल रहे हैं',
  problemLead: 'शैक्षणिक कार्य, फीस और प्रवेश अक्सर अलग-अलग सिस्टम में रहते हैं — इसलिए रिकॉर्ड बिगड़ते हैं, काम दोहराया जाता है, और कोई एक साफ़ तस्वीर नहीं मिलती।',
  compareBeforeHeading: 'ERPSaathi से पहले',
  compareAfterHeading: 'ERPSaathi के साथ',
  problems: [
    'प्रवेश, फीस और शैक्षणिक कार्यों के लिए कई अलग सिस्टम',
    'बकाया और आंशिक फीस भुगतान पर नज़र रखना कठिन',
    'छात्र डेटा रजिस्टरों और स्प्रेडशीट में बिखरा हुआ',
    'रिपोर्ट तैयार करने में घंटों लगना',
  ],
  solutions: [
    'हर विभाग के लिए एक जुड़ा हुआ प्लेटफ़ॉर्म',
    'रीयल-टाइम फीस बकाया, रसीदें और वसूली रिपोर्ट',
    'हर मॉड्यूल में साझा एकल छात्र रिकॉर्ड',
    'ज़रूरत के अनुसार फ़िल्टर की गई, तुरंत तैयार रिपोर्ट',
  ],

  featuresEyebrow: 'क्षमताएँ',
  featuresTitle: 'क्षमताएँ एक नज़र में',
  featuresLead: 'ERPSaathi क्या कवर करता है, इसका उच्च-स्तरीय नक्शा। नीचे प्रत्येक मॉड्यूल विस्तार से देखें।',
  features: [
    { title: 'छात्र प्रबंधन', desc: 'प्रोफ़ाइल, परिवार और सत्र इतिहास एक ही रिकॉर्ड में।' },
    { title: 'प्रवेश', desc: 'पूछताछ से पंजीकरण से प्रवेश तक — एक ही प्रक्रिया।' },
    { title: 'उपस्थिति', desc: 'सारांश के साथ छात्रों और स्टाफ़ की दैनिक उपस्थिति।' },
    { title: 'फीस प्रबंधन', desc: 'संरचनाएँ, बकाया, वसूली और प्रिंट योग्य रसीदें।' },
    { title: 'शैक्षणिक', desc: 'कक्षाएँ, सेक्शन, विषय और होमवर्क एक साथ।' },
    { title: 'परीक्षा', desc: 'समय सारिणी, अंक, परिणाम और रिपोर्ट कार्ड।' },
    { title: 'वित्त और पेरोल', desc: 'व्यय, वेतन पर्ची और बैंक नियंत्रण।' },
    { title: 'परिवहन', desc: 'रूट, वाहन और ड्राइवर छात्रों से जुड़े।' },
  ],

  showcaseEyebrow: 'प्रोडक्ट टूर',
  showcaseTitle: 'ERPSaathi को काम करते देखें',
  showcaseModules: [
    { title: 'शैक्षणिक', short: 'कक्षाएँ और विषय', desc: 'कक्षाएँ, सेक्शन, विषय और होमवर्क एक संरचित पदानुक्रम में।' },
    { title: 'प्रवेश', short: 'पूछताछ से प्रवेश', desc: 'पूछताछ से पंजीकरण से प्रवेश तक — साझा छात्र रिकॉर्ड के साथ एक प्रक्रिया।' },
    { title: 'उपस्थिति', short: 'दैनिक ट्रैकिंग', desc: 'छात्रों और स्टाफ़ की दैनिक उपस्थिति, अवकाश और मासिक सारांश।' },
    { title: 'फीस प्रबंधन', short: 'बकाया और रसीदें', desc: 'संरचनाएँ, बकाया, वसूली और खाता-तैयार फीस इतिहास।' },
    { title: 'वित्त और पेरोल', short: 'व्यय और वेतन', desc: 'कार्यालय व्यय, वेतन पर्ची, बैंक खाते और नकद नियंत्रण।' },
    { title: 'परीक्षा', short: 'अंक और परिणाम', desc: 'समय सारिणी, अंक, परिणाम, प्रवेश पत्र और रिपोर्ट कार्ड।' },
    { title: 'परिवहन', short: 'रूट और वाहन', desc: 'रूट, स्टॉप, वाहन और ड्राइवर छात्र परिवहन से जुड़े।' },
    { title: 'रिपोर्ट', short: 'एक्सपोर्ट तैयार', desc: 'नियंत्रित एक्सपोर्ट के साथ कक्षा-वार, क्षेत्र-वार और वित्तीय रिपोर्ट।' },
  ],

  udiseEyebrow: 'भारतीय स्कूलों के लिए बनाया गया',
  udiseTitle: 'बिना दोहरे काम के UDISE+ अनुपालन',
  udiseLead: 'फॉर्म तैयार करें और रजिस्ट्री स्थिति बनाए रखें — उसी छात्र रिकॉर्ड से जो पहले से ERPSaathi में है।',
  udiseCaps: [
    { title: 'UDISE+ S02', desc: 'जिन छात्रों का UDISE पर नामांकन नहीं हुआ, उनके फॉर्म तैयार और प्रिंट करें।' },
    { title: 'UDISE+ S03', desc: 'छात्र रजिस्ट्री, PEN और नामांकन स्थिति बनाए रखें।' },
  ],
  udiseProof: 'कोई अलग स्प्रेडशीट नहीं।',

  whyEyebrow: 'ERPSaathi क्यों',
  whyTitle: 'स्कूलों के असली काम के लिए बनाया गया',
  whyItems: [
    { title: 'एक साझा छात्र रिकॉर्ड', desc: 'हर विभाग एक ही छात्र डेटा से काम करता है — बिखरी फ़ाइलें नहीं।' },
    { title: 'हर विभाग के लिए एक लॉगिन', desc: 'प्रवेश, फीस, शैक्षणिक, परीक्षा और परिवहन एक ही प्लेटफ़ॉर्म में।' },
    { title: 'भूमिका से सुरक्षित', desc: 'स्टाफ़ केवल वे मॉड्यूल और कार्य देखता है जिनकी अनुमति है।' },
    { title: 'बिना स्प्रेडशीट रिपोर्ट', desc: 'लाइव स्कूल डेटा से कक्षा, फीस और संचालन रिपोर्ट।' },
  ],

  stepsEyebrow: 'शुरुआत करें',
  stepsTitle: 'यह कैसे काम करता है',
  stepsLead: 'सेटअप → चलाएँ → बढ़ाएँ — पहले दिन से दैनिक संचालन तक।',
  steps: [
    { title: 'सेटअप', desc: 'स्कूल जानकारी, शाखाएँ, उपयोगकर्ता और शैक्षणिक सत्र कॉन्फ़िगर करें।' },
    { title: 'चलाएँ', desc: 'रोज छात्र, उपस्थिति, फीस, शैक्षणिक कार्य और परीक्षा प्रबंधित करें।' },
    { title: 'बढ़ाएँ', desc: 'रिपोर्ट और साझा रिकॉर्ड से स्कूल को स्पष्टता के साथ चलाएँ।' },
  ],

  aboutEyebrow: 'ERPSaathi के बारे में',
  aboutText: 'ERPSaathi भारतीय स्कूलों के लिए बना स्कूल ERP है। यह प्रवेश, शैक्षणिक, उपस्थिति, फीस, परीक्षा, वित्त, परिवहन और UDISE+ को एक प्लेटफ़ॉर्म में जोड़ता है — ताकि हर मॉड्यूल एक ही छात्र रिकॉर्ड से काम करे।',
  securityPoints: [
    'भूमिका-आधारित पहुँच',
    'सत्र प्रमाणीकरण',
    'ऑडिट लॉगिंग',
    'केंद्रीकृत डेटाबेस',
  ],

  faqEyebrow: 'FAQ',
  faqTitle: 'स्कूल मालिक अक्सर जो सवाल पूछते हैं',
  faqs: [
    { q: 'ERPSaathi क्या है?', a: 'एक स्कूल ERP जो प्रवेश, शैक्षणिक, उपस्थिति, फीस, परीक्षा, वित्त, परिवहन और UDISE+ को एक सिस्टम में जोड़ता है।' },
    { q: 'कौन-से विभाग इसका उपयोग कर सकते हैं?', a: 'प्रशासक, शिक्षक, लेखा और परिवहन स्टाफ़ — प्रत्येक को केवल ज़रूरी मॉड्यूल की भूमिका-आधारित पहुँच।' },
    { q: 'क्या यह UDISE+ सपोर्ट करता है?', a: 'हाँ। S02 और S03 वर्कफ़्लो, PEN ट्रैकिंग और नामांकन स्थिति बिल्ट-इन हैं और छात्र डेटा से सिंक रहती हैं।' },
    { q: 'क्या स्टाफ़ की अनुमतियाँ अलग हो सकती हैं?', a: 'हाँ। पहुँच भूमिका-आधारित है और मॉड्यूल व कार्य के अनुसार सेट की जा सकती है।' },
    { q: 'क्या डेमो आज़मा सकते हैं?', a: 'हाँ। डेमो अनुरोध से सैंपल डेटा वाला कार्यशील डेमो स्कूल खुलता है — साइनअप की ज़रूरत नहीं।' },
    { q: 'क्या छोटे और बड़े स्कूल दोनों के लिए ठीक है?', a: 'हाँ। शाखाएँ, सत्र, कक्षाएँ और सेक्शन एकल परिसर से बहु-शाखा तक स्केल करते हैं।' },
  ],

  ctaTitle: 'अपने स्कूल को एक सिस्टम पर चलाने के लिए तैयार हैं?',
  ctaSub: 'लाइव डेमो स्कूल देखें, या ERPSaathi सेटअप के लिए हमसे बात करें।',
  ctaPrimary: 'डेमो के लिए अनुरोध करें',
  ctaSecondary: 'हमसे बात करें',

  footerTagline: 'हर स्कूल विभाग के लिए एक सिस्टम।',
  footerLinksHeading: 'लिंक',
  footerLinks: { home: 'होम', features: 'विशेषताएँ', modules: 'मॉड्यूल', about: 'हमारे बारे में', contact: 'संपर्क करें', requestDemo: 'डेमो का अनुरोध करें' },
  footerProductHeading: 'उत्पाद',
  footerProduct: { admissions: 'प्रवेश', students: 'छात्र', attendance: 'उपस्थिति', fees: 'फीस', exams: 'परीक्षा', reports: 'रिपोर्ट' },
  footerLegalHeading: 'कानूनी',
  footerPrivacy: 'गोपनीयता नीति',
  footerTerms: 'नियम और शर्तें',
  footerContactHeading: 'संपर्क',
  footerContactNote: 'बिक्री और सहायता',
  footerCopyright: 'सर्वाधिकार सुरक्षित।',
  footerSystemLabel: 'स्कूल ERP सिस्टम',

  whatsappTooltip: 'व्हाट्सएप पर चैट करें',
};

export default {
  name: 'Welcome',
  directives: {
    reveal: {
      mounted(el) {
        if (typeof window === 'undefined') return;
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return;
        el.classList.add('erp-reveal');
        if (!('IntersectionObserver' in window)) {
          el.classList.add('is-visible');
          return;
        }
        const io = new IntersectionObserver(
          (entries) => {
            entries.forEach((entry) => {
              if (entry.isIntersecting) {
                el.classList.add('is-visible');
                io.unobserve(el);
              }
            });
          },
          { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
        );
        io.observe(el);
      },
    },
  },
  data() {
    const cfg = { ...DEFAULTS, ...(typeof window !== 'undefined' && window.__WELCOME__ ? window.__WELCOME__ : {}) };
    return {
      authenticated: !!cfg.authenticated,
      loginUrl: cfg.loginUrl || DEFAULTS.loginUrl,
      dashboardUrl: cfg.dashboardUrl || DEFAULTS.dashboardUrl,
      tryDemoUrl: cfg.tryDemoUrl || DEFAULTS.tryDemoUrl,
      schoolName: cfg.schoolName || DEFAULTS.schoolName,
      logoUrl: cfg.logoUrl || DEFAULTS.logoUrl,
      isDark: false,
      locale: 'en',
      isScrolled: false,
      headerHidden: false,
      lastScrollY: 0,
      mobileMenuOpen: false,
      scrollTicking: false,
      scrollProgress: 0,
      heroIn: false,
      year: new Date().getFullYear(),
      whatsappNumber: '919942882661',
      whatsappContact: 'Arman Ansari',
      faqOpen: null,
      heroSlides: HERO_SLIDES,
      heroSlideIndex: 0,
      heroPrevSlideIndex: -1,
      heroSliderPaused: false,
      heroSliderTimer: null,
      heroSlideMs: 5200,

      contentEn: CONTENT_EN,
      contentHi: CONTENT_HI,
      featureIcons: FEATURE_CARD_ICONS,
      showcaseMeta: SHOWCASE_META,
      showcaseIcons: SHOWCASE_ICONS,
      whyIcons: WHY_ICONS,

      slideIndex: 0,
      prevSlideIndex: -1,
      sliderPaused: false,
      sliderTimer: null,
      slideMs: 4600,

      udiseImage: '/assets/img/modules/module-udise-s02.jpg',
    };
  },
  computed: {
    c() {
      return this.locale === 'hi' ? this.contentHi : this.contentEn;
    },
    primaryHref() {
      return this.authenticated ? this.dashboardUrl : this.loginUrl;
    },
    primaryLabel() {
      if (this.authenticated) return this.locale === 'hi' ? 'डैशबोर्ड' : 'Dashboard';
      return this.locale === 'hi' ? 'लॉगिन' : 'Login';
    },
    activeModule() {
      return this.c.showcaseModules[this.slideIndex] || this.c.showcaseModules[0];
    },
    activeHeroSlide() {
      return this.heroSlides[this.heroSlideIndex] || this.heroSlides[0];
    },
    whatsappUrl() {
      const msg = this.locale === 'hi'
        ? `नमस्ते ${this.whatsappContact}, मुझे अपने स्कूल के लिए ERPSaathi में रुचि है।`
        : `Hi ${this.whatsappContact}, I'm interested in ERPSaathi for my school.`;
      return `https://wa.me/${this.whatsappNumber}?text=${encodeURIComponent(msg)}`;
    },
  },
  watch: {
    isDark(value) {
      this.applyTheme(value);
    },
  },
  mounted() {
    let saved = null;
    try {
      saved = localStorage.getItem('erp-welcome-theme-v3');
    } catch (e) {
      saved = null;
    }
    this.isDark = saved === null ? false : saved === 'dark';
    this.applyTheme(this.isDark);

    let savedLang = null;
    try {
      savedLang = localStorage.getItem('erp-welcome-lang');
    } catch (e) {
      savedLang = null;
    }
    if (savedLang === 'hi' || savedLang === 'en') this.locale = savedLang;

    this.lastScrollY = window.scrollY || 0;
    this.onScroll();
    window.addEventListener('scroll', this.onScroll, { passive: true });
    this.startSlider();
    this.startHeroSlider();

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        this.heroIn = true;
      });
    });
  },
  beforeUnmount() {
    const root = document.documentElement;
    root.style.removeProperty('background-color');
    window.removeEventListener('scroll', this.onScroll);
    this.stopSlider();
    this.stopHeroSlider();
  },
  methods: {
    startSlider() {
      this.stopSlider();
      const reduce = typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce) return;
      this.sliderTimer = window.setInterval(() => {
        if (this.sliderPaused) return;
        this.goSlide((this.slideIndex + 1) % this.c.showcaseModules.length, false);
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
    goSlide(index, restart = true) {
      this.prevSlideIndex = this.slideIndex;
      this.slideIndex = index;
      if (restart) this.startSlider();
    },
    startHeroSlider() {
      this.stopHeroSlider();
      const reduce = typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reduce) return;
      this.heroSliderTimer = window.setInterval(() => {
        if (this.heroSliderPaused) return;
        this.goHeroSlide((this.heroSlideIndex + 1) % this.heroSlides.length, false);
      }, this.heroSlideMs);
    },
    stopHeroSlider() {
      if (this.heroSliderTimer) {
        window.clearInterval(this.heroSliderTimer);
        this.heroSliderTimer = null;
      }
    },
    pauseHeroSlider() {
      this.heroSliderPaused = true;
    },
    resumeHeroSlider() {
      this.heroSliderPaused = false;
    },
    goHeroSlide(index, restart = true) {
      if (index === this.heroSlideIndex) return;
      this.heroPrevSlideIndex = this.heroSlideIndex;
      this.heroSlideIndex = index;
      if (restart) this.startHeroSlider();
    },
    onScroll() {
      if (this.scrollTicking) return;
      this.scrollTicking = true;
      window.requestAnimationFrame(() => {
        const y = window.scrollY || 0;
        this.isScrolled = y > 12;

        const delta = y - this.lastScrollY;
        // Hide on scroll down, show on scroll up (always show near top / mobile menu open)
        if (y <= 80 || this.mobileMenuOpen) {
          this.headerHidden = false;
        } else if (delta > 8) {
          this.headerHidden = true;
        } else if (delta < -8) {
          this.headerHidden = false;
        }
        this.lastScrollY = y;

        const doc = document.documentElement;
        const max = (doc.scrollHeight || 0) - (doc.clientHeight || 0);
        this.scrollProgress = max > 0 ? Math.min(100, Math.max(0, (y / max) * 100)) : 0;

        this.scrollTicking = false;
      });
    },
    applyTheme(dark) {
      const bg = dark ? '#0b1120' : '#ffffff';
      document.documentElement.style.backgroundColor = bg;
      if (document.body) document.body.style.backgroundColor = bg;
      try {
        localStorage.setItem('erp-welcome-theme-v3', dark ? 'dark' : 'light');
      } catch (e) {
        /* storage unavailable — ignore */
      }
    },
    toggleTheme() {
      this.isDark = !this.isDark;
    },
    toggleLocale() {
      this.setLocale(this.locale === 'en' ? 'hi' : 'en');
    },
    setLocale(lang) {
      this.locale = lang;
      try {
        localStorage.setItem('erp-welcome-lang', lang);
      } catch (e) {
        /* storage unavailable — ignore */
      }
    },
    toggleFaq(idx) {
      this.faqOpen = this.faqOpen === idx ? null : idx;
    },
  },
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap');

/* ---------- Design tokens ---------- */
.erp-site {
  --brand-50: #eef2ff;
  --brand-100: #e0e7ff;
  --brand-500: #6366f1;
  --brand-600: #4f46e5;
  --brand-700: #4338ca;
  --violet-500: #7c3aed;
  --green: #16a34a;
  --red: #dc2626;

  --bg: #ffffff;
  --bg-tint: #f8fafc;
  --surface: #ffffff;
  --surface-2: #f8fafc;
  --border: rgba(15, 23, 42, 0.09);
  --border-soft: rgba(15, 23, 42, 0.06);
  --text: #0f172a;
  --text-muted: #475569;
  --text-dim: #94a3b8;
  --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 12px 32px rgba(15, 23, 42, 0.08);
  --shadow-lg: 0 24px 60px rgba(15, 23, 42, 0.12);

  --space-section: clamp(64px, 8vw, 112px);

  position: relative;
  min-height: 100vh;
  background: var(--bg);
  color: var(--text);
  font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  scroll-behavior: smooth;
  -webkit-font-smoothing: antialiased;
}
.erp-site.dark {
  --bg: #0b1120;
  --bg-tint: #0f1729;
  --surface: #111a2e;
  --surface-2: #0f1729;
  --border: rgba(148, 163, 184, 0.14);
  --border-soft: rgba(148, 163, 184, 0.08);
  --text: #e6ebf5;
  --text-muted: #a3adc2;
  --text-dim: #6b7690;
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 12px 32px rgba(0, 0, 0, 0.35);
  --shadow-lg: 0 24px 60px rgba(0, 0, 0, 0.45);
}
.erp-site.lang-hi { font-family: 'Noto Sans Devanagari', 'Inter', sans-serif; }
.erp-site.lang-hi h1, .erp-site.lang-hi h2, .erp-site.lang-hi h3, .erp-site.lang-hi .erp-brand-word {
  font-family: 'Noto Sans Devanagari', 'Plus Jakarta Sans', sans-serif;
}
@media (prefers-reduced-motion: reduce) {
  .erp-site { scroll-behavior: auto; }
}

.erp-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 24px; }

.erp-skip {
  position: absolute;
  left: 16px;
  top: -48px;
  z-index: 100;
  background: var(--surface);
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

h1, h2, h3, .erp-brand-word {
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  letter-spacing: -0.02em;
}

/* ---------- Progress bar ---------- */
.erp-progress-bar {
  position: fixed;
  top: 0;
  left: 0;
  height: 3px;
  z-index: 80;
  width: 0;
  background: linear-gradient(90deg, var(--brand-500), var(--violet-500));
  transition: width 0.12s linear;
  pointer-events: none;
}

/* ---------- Navbar ---------- */
.erp-nav-bar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 60;
  background: color-mix(in srgb, var(--bg) 82%, transparent);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-bottom: 1px solid transparent;
  transform: translateY(0);
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.28s ease;
  will-change: transform;
}
.erp-nav-bar.is-scrolled {
  border-bottom-color: var(--border-soft);
  box-shadow: var(--shadow-sm);
}
.erp-nav-bar.is-hidden { transform: translateY(-100%); pointer-events: none; }
@media (prefers-reduced-motion: reduce) {
  .erp-nav-bar { transition: border-color 0.2s ease, box-shadow 0.2s ease; }
}
.erp-nav-spacer { height: 88px; }
.erp-nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: 88px;
  transition: height 0.2s ease;
}
.erp-nav-bar.is-scrolled .erp-nav-inner { height: 72px; }
.erp-brand { display: flex; align-items: center; text-decoration: none; }
.erp-brand-logo { height: 52px; width: auto; max-width: 240px; object-fit: contain; }
.erp-nav-links { display: none; align-items: center; gap: 28px; }
.erp-nav-links a {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-muted);
  text-decoration: none;
  transition: color 0.15s ease;
}
.erp-nav-links a:hover { color: var(--text); }
.erp-nav-actions { display: none; align-items: center; gap: 14px; }
.erp-nav-login {
  font-size: 14px;
  font-weight: 650;
  color: var(--text);
  text-decoration: none;
}
.erp-nav-login:hover { color: var(--brand-600); }
.erp-nav-utility { display: flex; align-items: center; gap: 8px; }
.erp-icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.erp-icon-btn:hover { background: var(--surface-2); color: var(--text); }

.erp-lang-switch { position: relative; }
.erp-lang-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 52px;
  height: 34px;
  padding: 0 12px;
  border-radius: 10px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: 0.01em;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.erp-lang-toggle:hover {
  border-color: color-mix(in srgb, var(--brand-500) 40%, var(--border));
  color: var(--brand-600);
  background: color-mix(in srgb, var(--brand-500) 8%, var(--surface));
}
.erp-site.dark .erp-lang-toggle:hover { color: var(--brand-500); }

.erp-hamburger {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 5px;
  width: 34px;
  height: 34px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: transparent;
  cursor: pointer;
}
.erp-hamburger span {
  display: block;
  height: 2px;
  margin: 0 8px;
  background: var(--text);
  border-radius: 2px;
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.erp-hamburger.is-open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.erp-hamburger.is-open span:nth-child(2) { opacity: 0; }
.erp-hamburger.is-open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.erp-mobile-menu {
  border-top: 1px solid var(--border-soft);
  background: var(--bg);
  padding: 18px 24px 24px;
}
.erp-mobile-menu nav { display: flex; flex-direction: column; gap: 4px; margin-bottom: 16px; }
.erp-mobile-menu nav a {
  padding: 10px 4px;
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
  text-decoration: none;
  border-bottom: 1px solid var(--border-soft);
}
.erp-mobile-menu-enter-active, .erp-mobile-menu-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.erp-mobile-menu-enter-from, .erp-mobile-menu-leave-to { opacity: 0; transform: translateY(-8px); }
@media (prefers-reduced-motion: reduce) {
  .erp-mobile-menu-enter-active, .erp-mobile-menu-leave-active { transition: none; }
}

/* ---------- Buttons ---------- */
.erp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 10px;
  font-family: inherit;
  font-size: 14px;
  font-weight: 650;
  text-decoration: none;
  cursor: pointer;
  border: 1px solid transparent;
  padding: 11px 20px;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}
.erp-btn-sm { padding: 9px 18px; font-size: 13px; }
.erp-btn-lg { padding: 14px 26px; font-size: 15.5px; }
.erp-btn-block { width: 100%; }
.erp-btn-primary {
  background: linear-gradient(135deg, var(--brand-500), var(--brand-700));
  color: #ffffff;
  box-shadow: 0 10px 24px rgba(79, 70, 229, 0.28);
}
.erp-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 14px 30px rgba(79, 70, 229, 0.36); }
.erp-btn-ghost {
  background: transparent;
  color: var(--text);
  border-color: var(--border);
}
.erp-btn-ghost:hover { background: var(--surface-2); border-color: var(--brand-500); }
@media (prefers-reduced-motion: reduce) {
  .erp-btn:hover { transform: none; }
}

.erp-eyebrow {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--brand-600);
  margin: 0 0 12px;
}
.erp-site.lang-hi .erp-eyebrow { text-transform: none; letter-spacing: 0.02em; }

/* ---------- Hero ---------- */
.erp-hero { position: relative; padding: 56px 0 48px; overflow: hidden; }
.erp-hero-decor { position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.erp-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(90px);
  opacity: 0.28;
}
.erp-blob-a { width: 480px; height: 480px; top: -180px; left: -140px; background: radial-gradient(circle, var(--brand-500), transparent 70%); }
.erp-blob-b { width: 420px; height: 420px; top: 20%; right: -160px; background: radial-gradient(circle, var(--violet-500), transparent 70%); }
.erp-site.dark .erp-blob { opacity: 0.18; }
.erp-dot-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(var(--border) 1px, transparent 1px);
  background-size: 26px 26px;
  mask-image: radial-gradient(circle at 50% 0%, #000 0%, transparent 65%);
  -webkit-mask-image: radial-gradient(circle at 50% 0%, #000 0%, transparent 65%);
  opacity: 0.7;
}

.erp-hero-grid {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr;
  gap: 40px;
  align-items: center;
}
.erp-hero-copy { opacity: 0; transform: translateY(14px); transition: opacity 0.55s ease, transform 0.55s ease; }
.erp-hero-copy.is-in { opacity: 1; transform: none; }
.erp-hero-brand {
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  font-size: clamp(2.15rem, 5.2vw, 3.15rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.05;
  margin: 0 0 8px;
  color: var(--brand-600);
}
.erp-site.dark .erp-hero-brand { color: var(--brand-500); }
.erp-site.lang-hi .erp-hero-brand { font-family: 'Noto Sans Devanagari', 'Plus Jakarta Sans', sans-serif; }
.erp-hero-kicker {
  margin: 0 0 18px;
  font-size: 0.8rem;
  font-weight: 650;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.erp-site.lang-hi .erp-hero-kicker { text-transform: none; letter-spacing: 0.02em; }
.erp-hero-title {
  font-size: clamp(1.45rem, 3.4vw, 2.05rem);
  font-weight: 650;
  line-height: 1.28;
  margin: 0 0 16px;
  color: var(--text);
  max-width: 18em;
}
.erp-brand-word { color: var(--brand-600); font-weight: 700; }
.erp-site.dark .erp-brand-word { color: var(--brand-500); }
.erp-hero-sub {
  font-size: clamp(0.98rem, 1.7vw, 1.08rem);
  line-height: 1.6;
  color: var(--text-muted);
  max-width: 32em;
  margin: 0 0 28px;
}
.erp-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 0; }

/* ---------- Hero product visual ---------- */
.erp-hero-visual {
  position: relative;
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 0.65s ease 0.08s, transform 0.65s ease 0.08s;
}
.erp-hero-visual.is-in { opacity: 1; transform: none; }
.erp-hero-visual::before {
  content: '';
  position: absolute;
  left: 12%;
  right: 12%;
  bottom: -6%;
  height: 28%;
  border-radius: 50%;
  background: radial-gradient(ellipse at center, color-mix(in srgb, var(--brand-500) 28%, transparent), transparent 70%);
  filter: blur(18px);
  pointer-events: none;
  z-index: 0;
}
@media (prefers-reduced-motion: reduce) {
  .erp-hero-copy,
  .erp-hero-visual { opacity: 1; transform: none; transition: none; }
  .erp-hero-visual.is-in .erp-hero-shot { animation: none; }
}
.erp-hero-shot {
  position: relative;
  z-index: 1;
  margin: 0;
  border-radius: 18px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 18%, var(--border));
  background: #0b1120;
  box-shadow:
    0 24px 48px rgba(15, 23, 42, 0.14),
    0 8px 20px rgba(79, 70, 229, 0.12);
  overflow: hidden;
}
.erp-hero-visual.is-in .erp-hero-shot {
  animation: erpHeroFloat 5.8s ease-in-out infinite;
}
@keyframes erpHeroFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-12px); }
}
.erp-hero-slides {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  background: #0b1120;
  overflow: hidden;
}
.erp-hero-shot-img {
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center top;
  background: #0b1120;
  opacity: 0;
  transform: translateX(18px) scale(1.02);
  transition: opacity 1.1s ease, transform 1.35s ease;
  pointer-events: none;
}
.erp-hero-shot-img.is-prev {
  transform: translateX(-14px) scale(1.01);
}
.erp-hero-shot-img.is-active {
  opacity: 1;
  transform: translateX(0) scale(1);
  z-index: 1;
}
.erp-hero-slide-meta {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 28px 16px 14px;
  background: linear-gradient(180deg, transparent, rgba(11, 17, 32, 0.72));
  pointer-events: none;
}
.erp-hero-slide-label {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.92);
}
.erp-hero-slide-dots {
  display: flex;
  align-items: center;
  gap: 6px;
  pointer-events: auto;
}
.erp-hero-slide-dot {
  width: 7px;
  height: 7px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.35);
  cursor: pointer;
  transition: width 0.35s ease, background 0.25s ease;
}
.erp-hero-slide-dot.is-active {
  width: 18px;
  background: #fff;
}
@media (prefers-reduced-motion: reduce) {
  .erp-hero-shot-img { transition: opacity 0.35s ease; transform: none; }
  .erp-hero-shot-img.is-prev { transform: none; }
  .erp-hero-shot-img.is-active { transform: none; }
}

/* Shared chrome bar used by module showcase */
.erp-mock-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-soft);
  background: var(--surface-2);
}
.erp-mock-bar span {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--text-dim) 55%, transparent);
}
.erp-mock-bar p { margin: 0 0 0 8px; font-size: 12px; font-weight: 600; color: var(--text-dim); flex: 1; }

/* ---------- Sections ---------- */
.erp-section { padding: var(--space-section) 0; }
.erp-section-head { max-width: 42em; margin: 0 auto 56px; text-align: center; }
.erp-section-title {
  font-size: clamp(1.7rem, 4vw, 2.3rem);
  font-weight: 800;
  line-height: 1.2;
  margin: 0 0 14px;
}
.erp-section-lead { font-size: 1.02rem; line-height: 1.65; color: var(--text-muted); margin: 0; }

/* ---------- Trust / proof bar ---------- */
.erp-trust-section {
  position: relative;
  padding: 32px 0;
  border-top: 1px solid var(--border-soft);
  border-bottom: 1px solid var(--border-soft);
  background:
    linear-gradient(90deg, color-mix(in srgb, var(--brand-500) 6%, transparent), transparent 28%, transparent 72%, color-mix(in srgb, var(--violet-500) 6%, transparent)),
    var(--bg-tint);
  overflow: hidden;
}
.erp-trust-section::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(110deg, transparent 35%, color-mix(in srgb, var(--brand-500) 8%, transparent) 50%, transparent 65%);
  background-size: 220% 100%;
  animation: erpTrustSheen 7.5s ease-in-out infinite;
  pointer-events: none;
}
@keyframes erpTrustSheen {
  0%, 100% { background-position: 120% 0; opacity: 0.35; }
  50% { background-position: -20% 0; opacity: 0.7; }
}
.erp-trust-bar {
  position: relative;
  z-index: 1;
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  text-align: center;
}
.erp-trust-bar li { list-style: none; margin: 0; padding: 0; }
.erp-trust-chip {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: 100%;
  padding: 16px 14px;
  border-radius: 14px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 12%, var(--border-soft));
  background: color-mix(in srgb, var(--surface) 88%, transparent);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
  animation: erpTrustFloat 4.8s ease-in-out infinite;
  animation-delay: calc(var(--trust-i, 0) * 0.35s);
}
@keyframes erpTrustFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}
.erp-trust-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-500), var(--violet-500));
  box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-500) 40%, transparent);
  animation: erpTrustPulse 2.8s ease-out infinite;
  animation-delay: calc(var(--trust-i, 0) * 0.25s);
}
@keyframes erpTrustPulse {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-500) 45%, transparent); }
  70% { box-shadow: 0 0 0 8px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
}
.erp-trust-label {
  font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif;
  font-size: 0.9rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--text);
  line-height: 1.3;
}
@media (prefers-reduced-motion: reduce) {
  .erp-trust-section::before,
  .erp-trust-chip,
  .erp-trust-dot { animation: none; }
}

/* ---------- Reveal ---------- */
.erp-reveal { opacity: 0; transform: translateY(22px); transition: opacity 0.6s ease, transform 0.6s ease; }
.erp-reveal.is-visible { opacity: 1; transform: none; }

/* ---------- Problem / solution compare ---------- */
.erp-problem-section {
  position: relative;
  padding-top: calc(var(--space-section) - 8px);
  overflow: hidden;
}
.erp-problem-section::before {
  content: '';
  position: absolute;
  width: 420px;
  height: 420px;
  top: 8%;
  left: -160px;
  border-radius: 50%;
  background: radial-gradient(circle, color-mix(in srgb, var(--brand-500) 14%, transparent), transparent 70%);
  filter: blur(20px);
  pointer-events: none;
  animation: erpProblemGlow 9s ease-in-out infinite;
}
.erp-problem-section::after {
  content: '';
  position: absolute;
  width: 380px;
  height: 380px;
  bottom: 4%;
  right: -140px;
  border-radius: 50%;
  background: radial-gradient(circle, color-mix(in srgb, var(--violet-500) 12%, transparent), transparent 70%);
  filter: blur(22px);
  pointer-events: none;
  animation: erpProblemGlow 11s ease-in-out infinite reverse;
}
@keyframes erpProblemGlow {
  0%, 100% { opacity: 0.45; transform: translate(0, 0); }
  50% { opacity: 0.85; transform: translate(12px, -10px); }
}
.erp-compare {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  align-items: stretch;
  max-width: 56em;
  margin: 0 auto;
}
.erp-compare-col {
  padding: 22px 20px;
  border-radius: 16px;
  border: 1px solid var(--border-soft);
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
}
.erp-compare-before {
  border-color: color-mix(in srgb, var(--red) 16%, var(--border-soft));
  background:
    linear-gradient(160deg, color-mix(in srgb, var(--red) 5%, transparent), transparent 55%),
    color-mix(in srgb, var(--surface) 92%, transparent);
}
.erp-compare-after {
  border-color: color-mix(in srgb, var(--brand-500) 22%, var(--border-soft));
  background:
    linear-gradient(160deg, color-mix(in srgb, var(--brand-500) 8%, transparent), transparent 55%),
    color-mix(in srgb, var(--surface) 92%, transparent);
}
.erp-compare-heading {
  margin: 0 0 16px;
  font-weight: 750;
  font-size: 0.84rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.erp-site.lang-hi .erp-compare-heading { text-transform: none; letter-spacing: 0.02em; }
.erp-compare-after .erp-compare-heading { color: var(--brand-600); }
.erp-site.dark .erp-compare-after .erp-compare-heading { color: var(--brand-500); }
.erp-compare-col ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
.erp-compare-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.94rem;
  line-height: 1.5;
  color: var(--text-muted);
  animation: erpCompareItemIn 0.55s ease both;
  animation-delay: calc(0.12s + var(--item-i, 0) * 0.08s);
}
.erp-compare-after .erp-compare-item { color: var(--text); }
.erp-compare-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-top: 0;
}
.erp-compare-before .erp-compare-icon {
  color: var(--red);
  background: color-mix(in srgb, var(--red) 10%, transparent);
}
.erp-compare-after .erp-compare-icon {
  color: var(--green);
  background: color-mix(in srgb, var(--green) 12%, transparent);
}
@keyframes erpCompareItemIn {
  from { opacity: 0; transform: translateX(-8px); }
  to { opacity: 1; transform: none; }
}
.erp-compare-after .erp-compare-item {
  animation-name: erpCompareItemInRight;
}
@keyframes erpCompareItemInRight {
  from { opacity: 0; transform: translateX(8px); }
  to { opacity: 1; transform: none; }
}
.erp-compare-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 2px auto;
}
.erp-compare-arrow-orb {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 28%, var(--border));
  background: linear-gradient(135deg, color-mix(in srgb, var(--brand-500) 16%, var(--surface)), var(--surface));
  color: var(--brand-600);
  box-shadow: 0 8px 20px rgba(79, 70, 229, 0.12);
  transform: rotate(90deg);
  animation: erpCompareArrow 2.8s ease-in-out infinite;
}
.erp-site.dark .erp-compare-arrow-orb { color: var(--brand-500); }
@keyframes erpCompareArrow {
  0%, 100% { transform: rotate(90deg) translateY(0); }
  50% { transform: rotate(90deg) translateY(-5px); }
}

/* ---------- Feature grid ---------- */
.erp-features {
  position: relative;
  overflow: hidden;
}
.erp-features::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 15% 20%, color-mix(in srgb, var(--brand-500) 8%, transparent), transparent 42%),
    radial-gradient(circle at 90% 70%, color-mix(in srgb, var(--violet-500) 8%, transparent), transparent 40%);
  pointer-events: none;
  animation: erpFeaturesGlow 10s ease-in-out infinite;
}
@keyframes erpFeaturesGlow {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}
.erp-features .erp-container { position: relative; z-index: 1; }
.erp-features-head { margin-bottom: 48px; }
.erp-feature-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
.erp-feature-card {
  border: none;
  border-radius: 0;
  padding: 0;
  background: transparent;
  box-shadow: none;
}
.erp-feature-chip {
  height: 100%;
  padding: 20px 18px;
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 12%, var(--border-soft));
  background: color-mix(in srgb, var(--surface) 90%, transparent);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
  transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
  animation: erpFeatureFloat 5.2s ease-in-out infinite;
  animation-delay: calc(var(--feat-i, 0) * 0.22s);
}
@keyframes erpFeatureFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-5px); }
}
.erp-feature-card:hover .erp-feature-chip {
  transform: translateY(-4px);
  border-color: color-mix(in srgb, var(--brand-500) 32%, var(--border));
  box-shadow: 0 14px 30px rgba(79, 70, 229, 0.12);
  animation-play-state: paused;
}
.erp-feature-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
  color: var(--brand-600);
  background: linear-gradient(145deg, var(--brand-50), color-mix(in srgb, var(--violet-500) 10%, var(--brand-50)));
  transition: transform 0.25s ease, background 0.25s ease;
}
.erp-site.dark .erp-feature-icon {
  background: color-mix(in srgb, var(--brand-500) 16%, transparent);
  color: var(--brand-500);
}
.erp-feature-card:hover .erp-feature-icon {
  transform: scale(1.06);
  background: color-mix(in srgb, var(--brand-500) 18%, var(--brand-50));
}
.erp-site.dark .erp-feature-card:hover .erp-feature-icon {
  background: color-mix(in srgb, var(--brand-500) 26%, transparent);
}
.erp-feature-icon svg { width: 20px; height: 20px; }
.erp-feature-chip h3 {
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 6px;
  letter-spacing: -0.01em;
}
.erp-feature-chip p {
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--text-muted);
  margin: 0;
  max-width: 22em;
}
@media (prefers-reduced-motion: reduce) {
  .erp-problem-section::before,
  .erp-problem-section::after,
  .erp-compare-arrow-orb,
  .erp-compare-item,
  .erp-features::before,
  .erp-feature-chip { animation: none; }
  .erp-feature-card:hover .erp-feature-chip,
  .erp-feature-card:hover .erp-feature-icon { transform: none; }
}

/* ---------- Why icon + hover ---------- */
.erp-why-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  color: var(--brand-600);
  background: var(--brand-50);
  transition: background 0.2s ease;
}
.erp-site.dark .erp-why-icon { background: color-mix(in srgb, var(--brand-500) 16%, transparent); color: var(--brand-500); }
.erp-why-icon svg { width: 19px; height: 19px; }
.erp-why-card:hover .erp-why-icon { background: color-mix(in srgb, var(--brand-500) 14%, var(--brand-50)); }
.erp-site.dark .erp-why-card:hover .erp-why-icon { background: color-mix(in srgb, var(--brand-500) 22%, transparent); }

/* ---------- Module showcase ---------- */
.erp-showcase { background: var(--bg-tint); position: relative; }
.erp-showcase::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(var(--border) 1px, transparent 1px);
  background-size: 28px 28px;
  mask-image: radial-gradient(circle at 85% 15%, #000 0%, transparent 55%);
  -webkit-mask-image: radial-gradient(circle at 85% 15%, #000 0%, transparent 55%);
  pointer-events: none;
  opacity: 0.55;
}
.erp-showcase .erp-container { position: relative; }
.erp-showcase-head { margin-bottom: 40px; }
.erp-showcase-layout { display: grid; grid-template-columns: 1fr; gap: 28px; align-items: start; }
.erp-showcase-nav { display: flex; flex-direction: column; gap: 8px; }
.erp-showcase-nav-item {
  position: relative;
  text-align: left;
  border: 1px solid var(--border-soft);
  border-left: 3px solid transparent;
  border-radius: 12px;
  padding: 12px 14px;
  background: color-mix(in srgb, var(--surface) 82%, transparent);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
}
.erp-showcase-nav-item:hover {
  background: var(--surface);
  border-color: color-mix(in srgb, var(--brand-500) 35%, var(--border));
  border-left-color: color-mix(in srgb, var(--brand-500) 55%, transparent);
  box-shadow: 0 8px 20px rgba(79, 70, 229, 0.12);
  transform: translateY(-1px);
}
.erp-showcase-nav-item:hover .erp-showcase-nav-title { color: var(--text); }
.erp-showcase-nav-item:hover .erp-showcase-nav-icon {
  color: var(--brand-600);
  background: var(--brand-50);
}
.erp-site.dark .erp-showcase-nav-item:hover .erp-showcase-nav-icon {
  color: var(--brand-500);
  background: color-mix(in srgb, var(--brand-500) 16%, transparent);
}
.erp-showcase-nav-item:active {
  transform: translateY(0);
  box-shadow: 0 2px 8px rgba(79, 70, 229, 0.1);
}
.erp-showcase-nav-item.is-active {
  background: var(--surface);
  border-color: color-mix(in srgb, var(--brand-500) 40%, var(--border));
  border-left-color: var(--brand-500);
  box-shadow: 0 6px 16px rgba(79, 70, 229, 0.1);
}
.erp-showcase-nav-item.is-active:hover {
  transform: none;
  box-shadow: 0 8px 20px rgba(79, 70, 229, 0.14);
}
.erp-showcase-nav-icon {
  flex-shrink: 0;
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dim);
  background: var(--surface-2);
  transition: color 0.18s ease, background 0.18s ease, transform 0.18s ease;
}
.erp-showcase-nav-item:hover .erp-showcase-nav-icon { transform: scale(1.05); }
.erp-showcase-nav-icon svg { width: 17px; height: 17px; }
.erp-showcase-nav-item.is-active .erp-showcase-nav-icon {
  color: var(--brand-600);
  background: var(--brand-50);
}
.erp-site.dark .erp-showcase-nav-item.is-active .erp-showcase-nav-icon {
  color: var(--brand-500);
  background: color-mix(in srgb, var(--brand-500) 16%, transparent);
}
.erp-showcase-nav-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.erp-showcase-nav-title { font-size: 0.92rem; font-weight: 650; color: var(--text-muted); transition: color 0.18s ease; }
.erp-showcase-nav-item.is-active .erp-showcase-nav-title { color: var(--text); font-weight: 700; }
.erp-showcase-nav-desc {
  font-size: 0.74rem;
  color: var(--text-dim);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  opacity: 0.85;
}
.erp-showcase-nav-item.is-active .erp-showcase-nav-desc { color: var(--text-muted); opacity: 1; }
@media (prefers-reduced-motion: reduce) {
  .erp-showcase-nav-item,
  .erp-showcase-nav-item:hover,
  .erp-showcase-nav-item:hover .erp-showcase-nav-icon { transform: none; }
}
.erp-showcase-nav-bar { display: block; height: 2px; margin-top: 7px; background: var(--border-soft); border-radius: 2px; overflow: hidden; }
.erp-showcase-nav-bar span { display: block; height: 100%; width: 0; background: var(--brand-500); animation: erpShowcaseProgress 4.6s linear; }
@keyframes erpShowcaseProgress { from { width: 0; } to { width: 100%; } }
@media (prefers-reduced-motion: reduce) { .erp-showcase-nav-bar span { animation: none; width: 100%; } }

.erp-showcase-frame {
  border-radius: 14px;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}
.erp-showcase-frame-bar p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.erp-showcase-viewport {
  position: relative;
  aspect-ratio: 16 / 10;
  min-height: 280px;
  background: #0b1120;
  overflow: hidden;
}
.erp-showcase-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top left;
  opacity: 0;
  transform: translateX(12px);
  transition: opacity 0.5s ease, transform 0.55s ease;
}
.erp-showcase-img.is-prev { transform: translateX(-12px); }
.erp-showcase-img.is-active { opacity: 1; transform: translateX(0); z-index: 1; }
.erp-showcase-caption {
  margin: 0;
  padding: 14px 18px 16px;
  font-size: 0.86rem;
  line-height: 1.5;
  color: var(--text-muted);
  border-top: 1px solid var(--border-soft);
}
.erp-caption-fade-enter-active, .erp-caption-fade-leave-active { transition: opacity 0.25s ease; }
.erp-caption-fade-enter-from, .erp-caption-fade-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) {
  .erp-caption-fade-enter-active, .erp-caption-fade-leave-active { transition: none; }
  .erp-showcase-img { transition: opacity 0.3s ease; transform: none; }
}

/* ---------- UDISE+ ---------- */
.erp-udise-grid { display: grid; grid-template-columns: 1fr; gap: 40px; align-items: center; }
.erp-udise-copy .erp-section-title { text-align: left; margin-bottom: 12px; }
.erp-udise-copy .erp-section-lead { text-align: left; max-width: 34em; }
.erp-udise-caps {
  margin-top: 28px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.erp-udise-cap {
  padding: 0 0 0 14px;
  border-left: 2px solid color-mix(in srgb, var(--brand-500) 55%, var(--border));
}
.erp-udise-cap h3 {
  margin: 0 0 4px;
  font-size: 0.98rem;
  font-weight: 750;
  letter-spacing: -0.01em;
  color: var(--text);
}
.erp-udise-cap p {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--text-muted);
  max-width: 32em;
}
.erp-udise-proof {
  margin: 22px 0 0;
  font-size: 0.82rem;
  font-weight: 650;
  color: var(--brand-600);
  letter-spacing: 0.01em;
}
.erp-site.dark .erp-udise-proof { color: var(--brand-500); }
.erp-udise-visual { margin: 0; }
.erp-udise-shot {
  margin: 0;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}
.erp-udise-shot-chrome {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 11px 14px;
  border-bottom: 1px solid var(--border-soft);
  background: var(--surface-2);
}
.erp-udise-shot-chrome span {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--text-dim) 55%, transparent);
}
.erp-udise-shot-chrome p {
  margin: 0 0 0 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-dim);
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.erp-udise-shot-img {
  display: block;
  width: 100%;
  height: auto;
  aspect-ratio: 16 / 10;
  object-fit: cover;
  object-position: top left;
  background: #0b1120;
}

/* ---------- Why ---------- */
.erp-why-grid { display: grid; grid-template-columns: 1fr; gap: 28px 24px; }
.erp-why-card {
  border: none;
  border-radius: 0;
  padding: 4px 2px 8px;
  background: transparent;
  box-shadow: none;
  transition: none;
}
.erp-why-card:hover { transform: none; border-color: transparent; box-shadow: none; }
.erp-why-card h3 {
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 6px;
  letter-spacing: -0.01em;
}
.erp-why-card p {
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--text-muted);
  margin: 0;
  max-width: 24em;
}

/* ---------- Steps ---------- */
.erp-steps-row { position: relative; display: grid; grid-template-columns: 1fr; gap: 36px; }
.erp-steps-line {
  display: none;
  position: absolute;
  top: 14px;
  left: calc(100% / 6);
  right: calc(100% / 6);
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border), var(--brand-500), var(--border), transparent);
  z-index: 0;
}
.erp-step { position: relative; z-index: 1; }
.erp-step-num {
  display: block;
  font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif;
  font-weight: 800;
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  color: var(--brand-600);
  margin-bottom: 10px;
}
.erp-site.dark .erp-step-num { color: var(--brand-500); }
.erp-step h3 { font-size: 1.12rem; font-weight: 750; margin: 0 0 8px; letter-spacing: -0.01em; }
.erp-step p { font-size: 0.9rem; line-height: 1.55; color: var(--text-muted); margin: 0; max-width: 22em; }

/* ---------- About ---------- */
.erp-about { background: var(--bg-tint); }
.erp-about-inner { max-width: 40em; margin: 0 auto; text-align: center; }
.erp-about-text {
  font-size: 1.02rem;
  line-height: 1.7;
  color: var(--text-muted);
  margin: 0 0 26px;
}
.erp-about-chips {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px 20px;
  padding: 0;
  margin: 0;
}
.erp-about-chips li {
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--text-dim);
  position: relative;
  padding-left: 14px;
}
.erp-about-chips li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 7px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--brand-500);
}

/* ---------- FAQ ---------- */
.erp-faq {
  max-width: 44em;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 0;
  border-top: 1px solid var(--border-soft);
}
.erp-faq-item {
  border: none;
  border-bottom: 1px solid var(--border-soft);
  border-radius: 0;
  background: transparent;
  overflow: hidden;
  box-shadow: none;
  transition: none;
}
.erp-faq-item.is-open { border-color: var(--border-soft); box-shadow: none; }
.erp-faq-q {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 4px;
  background: transparent;
  border: 0;
  cursor: pointer;
  font-family: inherit;
  font-size: 0.97rem;
  font-weight: 650;
  color: var(--text);
  text-align: left;
  transition: color 0.15s ease;
}
.erp-faq-item.is-open .erp-faq-q { background: transparent; color: var(--brand-700); }
.erp-site.dark .erp-faq-item.is-open .erp-faq-q { background: transparent; color: var(--brand-500); }
.erp-faq-chevron { color: var(--text-dim); flex-shrink: 0; transition: transform 0.25s ease, color 0.15s ease; }
.erp-faq-item.is-open .erp-faq-chevron { transform: rotate(180deg); color: var(--brand-600); }
.erp-faq-a-wrap {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.28s ease;
}
.erp-faq-item.is-open .erp-faq-a-wrap { grid-template-rows: 1fr; }
.erp-faq-a { overflow: hidden; }
.erp-faq-a p { margin: 0; padding: 0 4px 18px; font-size: 0.9rem; line-height: 1.6; color: var(--text-muted); }
@media (prefers-reduced-motion: reduce) {
  .erp-faq-a-wrap { transition: none; }
}

/* ---------- Final CTA ---------- */
.erp-cta { padding: 20px 0 96px; }
.erp-cta-inner {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 28px;
  border-radius: 20px;
  padding: 44px 40px;
  background: linear-gradient(135deg, var(--brand-600), var(--violet-500));
  color: #ffffff;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}
.erp-cta-inner::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 22px 22px;
  mask-image: radial-gradient(circle at 100% 0%, #000 0%, transparent 55%);
  -webkit-mask-image: radial-gradient(circle at 100% 0%, #000 0%, transparent 55%);
  pointer-events: none;
  opacity: 0.7;
}
.erp-cta-inner > * { position: relative; z-index: 1; }
.erp-cta-copy { max-width: 34em; }
.erp-cta-title {
  font-size: clamp(1.7rem, 3.6vw, 2.25rem);
  font-weight: 800;
  margin: 0 0 12px;
  color: #fff;
  letter-spacing: -0.02em;
  line-height: 1.15;
}
.erp-cta-sub {
  font-size: 1.02rem;
  color: rgba(255, 255, 255, 0.88);
  margin: 0;
  line-height: 1.55;
}
.erp-cta-actions { display: flex; flex-wrap: wrap; gap: 12px; }
.erp-cta-inner .erp-btn-primary { background: #ffffff; color: var(--brand-700); box-shadow: none; }
.erp-cta-inner .erp-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2); }
.erp-cta-inner .erp-btn-ghost { color: #fff; border-color: rgba(255, 255, 255, 0.45); }
.erp-cta-inner .erp-btn-ghost:hover { background: rgba(255, 255, 255, 0.12); border-color: #fff; }

/* ---------- Footer ---------- */
.erp-footer { border-top: 1px solid var(--border-soft); padding: 56px 0 28px; background: var(--bg-tint); }
.erp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 34px; padding-bottom: 34px; }
.erp-footer-brand .erp-brand-logo { height: 34px; margin-bottom: 12px; }
.erp-footer-note { font-size: 0.88rem; color: var(--text-muted); margin: 0; max-width: 26em; }
.erp-footer-col { display: flex; flex-direction: column; gap: 10px; }
.erp-footer-col h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-dim); margin: 0 0 2px; }
.erp-site.lang-hi .erp-footer-col h4 { text-transform: none; }
.erp-footer-col a { font-size: 0.88rem; color: var(--text-muted); text-decoration: none; transition: color 0.15s ease; }
.erp-footer-col a:hover { color: var(--brand-600); }
.erp-footer-fineprint { font-size: 0.78rem; color: var(--text-dim); margin: 2px 0 0; }
.erp-footer-contact-name { margin: 0; font-size: 0.88rem; font-weight: 650; color: var(--text); }
.erp-footer-contact-person { margin: 0; font-size: 0.85rem; color: var(--text-muted); }
.erp-footer-whatsapp { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
.erp-footer-phone { white-space: nowrap; }
.erp-footer-bottom { display: flex; flex-direction: column; gap: 8px; padding-top: 22px; border-top: 1px solid var(--border-soft); font-size: 0.8rem; color: var(--text-dim); }

/* ---------- WhatsApp floating button ---------- */
.erp-whatsapp-fab {
  position: fixed;
  right: calc(18px + env(safe-area-inset-right, 0px));
  bottom: calc(18px + env(safe-area-inset-bottom, 0px));
  z-index: 70;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #25d366;
  color: #ffffff;
  box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3);
  text-decoration: none;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.erp-whatsapp-fab:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 14px 30px rgba(37, 211, 102, 0.46); }
.erp-whatsapp-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 2px solid rgba(37, 211, 102, 0.5);
  animation: erpWhatsappPulse 2.4s ease-out infinite;
  pointer-events: none;
}
@keyframes erpWhatsappPulse {
  0% { transform: scale(1); opacity: 0.6; }
  100% { transform: scale(1.6); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .erp-whatsapp-fab, .erp-whatsapp-ring { animation: none; transition: none; }
}
.erp-whatsapp-tooltip {
  position: absolute;
  right: calc(100% + 12px);
  top: 50%;
  transform: translateY(-50%) translateX(6px);
  white-space: nowrap;
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  padding: 7px 12px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 600;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease, transform 0.2s ease;
  box-shadow: var(--shadow-md);
}
.erp-whatsapp-fab:hover .erp-whatsapp-tooltip,
.erp-whatsapp-fab:focus-visible .erp-whatsapp-tooltip { opacity: 1; transform: translateY(-50%) translateX(0); }

/* ---------- Responsive ---------- */
@media (min-width: 640px) {
  .erp-trust-bar { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
  .erp-feature-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .erp-why-grid { grid-template-columns: repeat(2, 1fr); }
  .erp-steps-row { grid-template-columns: repeat(3, 1fr); }
  .erp-steps-line { display: block; }
  .erp-compare { grid-template-columns: 1fr auto 1fr; gap: 18px; align-items: center; }
  .erp-compare-arrow { margin: 0; }
  .erp-compare-arrow-orb {
    transform: none;
    animation-name: erpCompareArrowH;
  }
  @keyframes erpCompareArrowH {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(5px); }
  }
  .erp-cta-inner { flex-direction: row; align-items: center; justify-content: space-between; gap: 32px; }
  .erp-footer-bottom { flex-direction: row; justify-content: space-between; }
  .erp-footer-grid { grid-template-columns: 1.6fr repeat(4, 1fr); }
}

@media (min-width: 900px) {
  .erp-nav-links { display: flex; }
  .erp-nav-actions { display: flex; }
  .erp-hamburger { display: none; }
  .erp-hero-grid { grid-template-columns: 1fr 1.05fr; gap: 56px; }
  .erp-hero { padding: 88px 0 64px; }
  .erp-feature-grid { grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .erp-why-grid { grid-template-columns: repeat(4, 1fr); gap: 36px 28px; }
  .erp-showcase-layout { grid-template-columns: minmax(200px, 0.72fr) minmax(0, 1.45fr); gap: 36px; align-items: center; }
  .erp-showcase-viewport { min-height: 360px; aspect-ratio: 16 / 9.5; }
  .erp-udise-grid { grid-template-columns: 1fr 1fr; }
}

@media (min-width: 1100px) {
  .erp-feature-grid { gap: 18px; }
}
</style>
