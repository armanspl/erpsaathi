<template>
  <div class="erp-site" :class="{ dark: isDark, 'lang-hi': locale === 'hi' }" :lang="locale" id="top">
    <div class="erp-progress-bar" :style="{ width: scrollProgress + '%' }" aria-hidden="true"></div>
    <a class="erp-skip" href="#hero-content">{{ c.skipLink }}</a>

    <!-- ============ Navbar ============ -->
    <header class="erp-nav-bar" :class="{ 'is-scrolled': isScrolled, 'is-hidden': headerHidden }">
      <div class="erp-container erp-nav-inner">
        <a href="#top" class="erp-brand" aria-label="ERPSaathi home">
          <img :src="brandLogoUrl" alt="ERPSaathi logo" class="erp-brand-logo" />
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
        <div class="erp-container erp-hero-grid">
          <div class="erp-hero-copy" :class="{ 'is-in': heroIn }">
            <p class="erp-hero-eyebrow">ERPSaathi · {{ c.heroPillSuffix }}</p>
            <h1 class="erp-hero-title">
              <!-- Spaces are emitted as string expressions: a literal trailing space inside <template> is trimmed by the compiler ("forevery"). -->
              {{ c.heroTitleBefore }}{{ c.heroTitleBefore ? ' ' : '' }}<span class="erp-brand-word">{{ c.heroTitleHighlight }}</span>{{ c.heroTitleAfter ? ' ' + c.heroTitleAfter : '' }}
            </h1>
            <p class="erp-hero-sub">{{ c.heroSub }}</p>
            <div class="erp-hero-actions">
              <a :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-lg">
                {{ c.heroCtaPrimary }}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </a>
              <a href="#roles" class="erp-btn erp-btn-ghost erp-btn-lg">{{ c.heroCtaSecondary }}</a>
            </div>
            <ul class="erp-hero-proof">
              <li v-for="p in c.heroProof" :key="p.key">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path v-for="(d, pi) in heroProofIcons[p.key]" :key="pi" :d="d" />
                </svg>
                {{ p.text }}
              </li>
            </ul>
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
                  width="1600"
                  height="900"
                  :loading="i === 0 ? 'eager' : 'lazy'"
                  :fetchpriority="i === 0 ? 'high' : 'low'"
                  decoding="async"
                />
              </div>
            </figure>
            <div class="erp-hero-slide-meta">
              <span class="erp-hero-slide-label" aria-live="polite">{{ activeHeroSlide.label }}</span>
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
          </div>
        </div>
      </section>

      <!-- ============ Trust / Proof ============ -->
      <section class="erp-section erp-trust-section">
        <div class="erp-container">
          <ul class="erp-trust-bar" aria-label="ERPSaathi capabilities">
            <li
              v-for="(item, i) in c.trustItems"
              :key="item.key"
              v-reveal
              :style="{ transitionDelay: (i * 90) + 'ms' }"
            >
              <a :href="trustLinks[item.key]" class="erp-trust-chip" :class="'is-' + item.key" :style="{ '--trust-i': i }">
                <span class="erp-trust-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path v-for="(d, pi) in trustIcons[item.key]" :key="pi" :d="d" />
                  </svg>
                </span>
                <span class="erp-trust-copy">
                  <span class="erp-trust-label">{{ item.label }}</span>
                  <span class="erp-trust-hint">{{ item.hint }}</span>
                </span>
              </a>
            </li>
          </ul>
        </div>
      </section>

      <!-- ============ Problem → Solution ============ -->
      <section class="erp-section erp-problem-section">
        <div class="erp-container">
          <div class="erp-section-head" v-reveal>
            <p class="erp-eyebrow">{{ c.problemEyebrow }}</p>
            <h2 class="erp-section-title">
              <template v-if="c.problemTitleLead">{{ c.problemTitleLead }} <span class="erp-nowrap">{{ c.problemTitleTail }}</span></template>
              <template v-else>{{ c.problemTitle }}</template>
            </h2>
            <p class="erp-section-lead">{{ c.problemLead }}</p>
          </div>

          <div class="erp-problem-scene" v-reveal>
            <div class="erp-problem-board is-before">
              <p class="erp-problem-board-label">{{ c.compareBeforeHeading }}</p>
              <div class="erp-problem-scatter" aria-hidden="true">
                <span
                  v-for="(chip, i) in c.problemScatter"
                  :key="chip"
                  class="erp-problem-scatter-chip"
                  :style="{ '--chip-i': i }"
                >{{ chip }}</span>
              </div>
              <p class="erp-problem-board-note">{{ c.problemSceneBefore }}</p>
            </div>

            <div class="erp-problem-transform" aria-hidden="true">
              <span class="erp-problem-transform-orb">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
              </span>
            </div>

            <div class="erp-problem-board is-after">
              <p class="erp-problem-board-label">{{ c.compareAfterHeading }}</p>
              <div class="erp-problem-hub" aria-hidden="true">
                <svg class="erp-problem-hub-spokes" viewBox="0 0 280 280" fill="none">
                  <line
                    v-for="(mod, i) in c.problemHub"
                    :key="'spoke-' + mod"
                    x1="140"
                    y1="140"
                    :x2="hubSpokeXY(i).x"
                    :y2="hubSpokeXY(i).y"
                    stroke="#BBF7D0"
                    stroke-width="2"
                  />
                </svg>
                <span class="erp-problem-hub-core">ERPSaathi</span>
                <span
                  v-for="(mod, i) in c.problemHub"
                  :key="mod"
                  class="erp-problem-hub-node"
                  :style="{ '--angle': (i * 60) + 'deg', '--node-i': i }"
                >{{ mod }}</span>
              </div>
              <p class="erp-problem-board-note">{{ c.problemSceneAfter }}</p>
            </div>
          </div>

          <ol class="erp-problem-stories">
            <li
              v-for="(story, i) in c.problemStories"
              :key="story.key"
              v-reveal
              :style="{ transitionDelay: (i * 110) + 'ms' }"
            >
              <article class="erp-problem-story" :style="{ '--story-i': i }">
                <div class="erp-problem-side is-pain">
                  <span class="erp-problem-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                      <path v-for="(d, pi) in problemIcons[story.key].pain" :key="'p' + pi" :d="d" />
                    </svg>
                  </span>
                  <div class="erp-problem-text">
                    <h3>{{ story.pain.title }}</h3>
                    <p>{{ story.pain.desc }}</p>
                  </div>
                </div>

                <span class="erp-problem-bridge" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
                </span>

                <div class="erp-problem-side is-fix">
                  <span class="erp-problem-ico" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                      <path v-for="(d, pi) in problemIcons[story.key].fix" :key="'f' + pi" :d="d" />
                    </svg>
                  </span>
                  <div class="erp-problem-text">
                    <h3>{{ story.fix.title }}</h3>
                    <p>{{ story.fix.desc }}</p>
                  </div>
                </div>
              </article>
            </li>
          </ol>
        </div>
      </section>

      <!-- ============ UDISE+ ============ -->
      <section class="erp-section erp-udise" id="udise">
        <div class="erp-container erp-udise-grid">
          <div class="erp-udise-copy" v-reveal>
            <p class="erp-eyebrow">{{ c.udiseEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.udiseTitle }}</h2>
            <p class="erp-udise-badge">{{ c.udiseProof }}</p>
            <p class="erp-section-lead">{{ c.udiseLead }}</p>
            <div class="erp-udise-caps">
              <article v-for="cap in c.udiseCaps" :key="cap.title" class="erp-udise-cap">
                <h3>{{ cap.title }}</h3>
                <p>{{ cap.desc }}</p>
              </article>
            </div>
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
                width="1600"
                height="900"
                loading="lazy"
                decoding="async"
              />
            </figure>
          </div>
        </div>
      </section>

      <!-- ============ Roles / who it's for ============ -->
      <section class="erp-section erp-roles" id="roles">
        <div class="erp-container">
          <div class="erp-section-head erp-roles-head" v-reveal>
            <p class="erp-eyebrow">{{ c.rolesEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.rolesTitle }}</h2>
            <p class="erp-section-lead">{{ c.rolesLead }}</p>
          </div>

          <div class="erp-roles-grid">
            <article
              v-for="(role, idx) in c.roles"
              :key="role.key"
              class="erp-role-card"
              v-reveal
              :style="{ '--role-i': idx }"
            >
              <div class="erp-role-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                  <path v-for="(d, i) in roleIcons[role.key]" :key="i" :d="d" />
                </svg>
              </div>
              <p class="erp-role-tag">{{ role.tag }}</p>
              <h3>{{ role.title }}</h3>
              <p>{{ role.desc }}</p>
              <ul class="erp-role-points">
                <li v-for="point in role.points" :key="point">{{ point }}</li>
              </ul>
            </article>
          </div>

          <div class="erp-roles-cta" v-reveal>
            <p>{{ c.rolesCtaLead }}</p>
            <a href="#modules" class="erp-btn erp-btn-primary">
              {{ c.rolesCta }}
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7" /></svg>
            </a>
          </div>
        </div>
      </section>

      <!-- ============ Module scroll tour (paired text + image per step) ============ -->
      <section class="erp-section erp-showcase erp-tour" id="modules">
        <div class="erp-container">
          <div class="erp-section-head erp-showcase-head" v-reveal>
            <p class="erp-eyebrow">{{ c.showcaseEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.showcaseTitle }}</h2>
            <p class="erp-section-lead">{{ c.showcaseLead }}</p>
          </div>

          <nav class="erp-tour-nav" :aria-label="c.showcaseNavLabel">
            <button
              v-for="(meta, i) in showcaseMeta"
              :key="'nav-' + meta.key"
              type="button"
              class="erp-tour-nav-btn"
              :class="{ 'is-active': i === slideIndex }"
              @click="jumpTour(i)"
            >{{ meta.nav || c.showcaseModules[i].title }}</button>
          </nav>

          <div class="erp-tour-rail" role="list" aria-label="Modules">
            <div class="erp-tour-track" aria-hidden="true">
              <span class="erp-tour-track-fill" :style="{ height: tourProgress + '%' }"></span>
            </div>

            <article
              v-for="(mod, i) in c.showcaseModules"
              :id="'tour-' + showcaseMeta[i].key"
              :key="showcaseMeta[i].key"
              :ref="(el) => setTourStepRef(el, i)"
              class="erp-tour-step"
              :class="{ 'is-active': i === slideIndex, 'is-done': i < slideIndex }"
              role="listitem"
              :data-tour-index="i"
            >
              <span class="erp-tour-node" aria-hidden="true">
                <span class="erp-tour-node-ring"></span>
                <span class="erp-tour-node-core">{{ String(i + 1).padStart(2, '0') }}</span>
              </span>

              <div class="erp-tour-pair">
                <div class="erp-tour-card">
                  <div class="erp-tour-card-top">
                    <span class="erp-tour-step-icon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path v-for="(d, di) in showcaseIcons[i]" :key="di" :d="d" />
                      </svg>
                    </span>
                    <span class="erp-tour-pill" v-if="i === slideIndex">{{ c.showcaseNow }}</span>
                  </div>
                  <p class="erp-tour-step-kicker">{{ mod.short }}</p>
                  <h3>{{ mod.title }}</h3>
                  <p class="erp-tour-step-desc">{{ mod.desc }}</p>
                  <ul v-if="mod.bullets && mod.bullets.length" class="erp-tour-bullets">
                    <li v-for="bullet in mod.bullets" :key="bullet">{{ bullet }}</li>
                  </ul>
                  <button type="button" class="erp-tour-step-cta" @click="openTourLightbox(i)">
                    <span>{{ c.showcaseView }}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M14 10l7-7M10 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5" /></svg>
                  </button>
                </div>

                <figure class="erp-tour-shot">
                  <div class="erp-tour-frame">
                    <div class="erp-tour-frame-head">
                      <div class="erp-mock-bar erp-showcase-frame-bar">
                        <span></span><span></span><span></span>
                        <p>{{ mod.title }}</p>
                      </div>
                      <div class="erp-tour-counter">
                        <strong>{{ String(i + 1).padStart(2, '0') }}</strong>
                        <span>/ {{ String(c.showcaseModules.length).padStart(2, '0') }}</span>
                      </div>
                    </div>
                    <button
                      type="button"
                      class="erp-tour-viewport"
                      :aria-label="c.showcaseView + ': ' + mod.title"
                      @click="openTourLightbox(i)"
                    >
                      <img
                        :src="showcaseMeta[i].image"
                        :alt="mod.title"
                        class="erp-tour-img"
                        width="1600"
                        height="900"
                        loading="eager"
                        decoding="async"
                      />
                    </button>
                  </div>
                </figure>
              </div>
            </article>
          </div>
        </div>
      </section>

      <div
        v-if="tourLightboxIndex !== null"
        class="erp-tour-lightbox"
        role="dialog"
        aria-modal="true"
        :aria-label="c.showcaseView"
        @click.self="closeTourLightbox"
      >
        <button type="button" class="erp-tour-lightbox-close" :aria-label="c.showcaseLightboxClose" @click="closeTourLightbox">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
        <img
          v-if="showcaseMeta[tourLightboxIndex]"
          :src="showcaseMeta[tourLightboxIndex].image"
          :alt="(c.showcaseModules[tourLightboxIndex] && c.showcaseModules[tourLightboxIndex].title) || ''"
          class="erp-tour-lightbox-img"
        />
      </div>

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
      <section class="erp-section erp-steps" id="how">
        <div class="erp-container">
          <div class="erp-section-head">
            <p class="erp-eyebrow">{{ c.stepsEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.stepsTitle }}</h2>
            <p class="erp-section-lead">{{ c.stepsLead }}</p>
          </div>
          <div class="erp-steps-row">
            <span class="erp-steps-line" aria-hidden="true"></span>
            <div class="erp-step" v-for="(step, idx) in c.steps" :key="step.title" v-reveal :style="{ transitionDelay: idx * 100 + 'ms' }">
              <span class="erp-step-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                  <path v-for="(d, i) in stepIcons[idx]" :key="i" :d="d" />
                </svg>
              </span>
              <span class="erp-step-num">0{{ idx + 1 }}</span>
              <h3>{{ step.title }}</h3>
              <p>{{ step.desc }}</p>
            </div>
          </div>
          <div class="erp-steps-cta" v-reveal>
            <a :href="tryDemoUrl" class="erp-btn erp-btn-primary erp-btn-lg">{{ c.stepsCta }}</a>
          </div>
        </div>
      </section>

      <!-- ============ Testimonials ============ -->
      <section class="erp-section erp-testimonials" id="testimonials">
        <div class="erp-container">
          <div class="erp-section-head" v-reveal>
            <p class="erp-eyebrow">{{ c.testimonialsEyebrow }}</p>
            <h2 class="erp-section-title">{{ c.testimonialsTitle }}</h2>
          </div>
          <div class="erp-testimonials-grid">
            <blockquote
              v-for="(item, idx) in c.testimonials"
              :key="item.name"
              class="erp-testimonial"
              v-reveal
              :style="{ transitionDelay: (idx * 80) + 'ms' }"
            >
              <p class="erp-testimonial-quote">“{{ item.quote }}”</p>
              <footer>
                <strong>{{ item.name }}</strong>
                <span>{{ item.role }} · {{ item.school }}, {{ item.city }}</span>
              </footer>
            </blockquote>
          </div>
        </div>
      </section>

      <!-- ============ FAQ ============ -->
      <section class="erp-section erp-faq-section" id="faq">
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
            <a :href="whatsappUrl" target="_blank" rel="noopener" class="erp-btn erp-btn-outline-light erp-btn-lg">{{ c.ctaSecondary }}</a>
          </div>
        </div>
      </section>
    </main>

    <!-- ============ Footer ============ -->
    <footer class="erp-footer">
      <div class="erp-container erp-footer-grid">
        <div class="erp-footer-brand">
          <img :src="brandLogoUrl" alt="ERPSaathi logo" class="erp-brand-logo" />
          <p class="erp-footer-note">{{ c.footerTagline }}</p>
          <p class="erp-footer-address">{{ c.footerAddress }}</p>
          <p class="erp-footer-contact-lines">
            <a :href="'tel:' + c.footerPhoneTel">{{ c.footerPhone }}</a>
            <a :href="'mailto:' + c.footerEmail">{{ c.footerEmail }}</a>
          </p>
        </div>

        <nav class="erp-footer-col" aria-label="Site">
          <h4>{{ c.footerLinksHeading }}</h4>
          <a href="#top">{{ c.footerLinks.home }}</a>
          <a href="#roles">{{ c.footerLinks.features }}</a>
          <a href="#modules">{{ c.footerLinks.modules }}</a>
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
            <span>WhatsApp:</span> <span class="erp-footer-phone">{{ c.footerPhone }}</span>
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
  logoUrl: '/assets/img/logo/new-logo2.png?v=1',
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
  { key: 'academics', image: '/assets/img/modules/tour/academics.webp?v=1', nav: 'Academics' },
  { key: 'admissions', image: '/assets/img/modules/tour/admissions.webp?v=1', nav: 'Admissions' },
  { key: 'attendance', image: '/assets/img/modules/tour/attendance.webp?v=1', nav: 'Attendance' },
  { key: 'fees', image: '/assets/img/modules/tour/fees.webp?v=1', nav: 'Fees' },
  { key: 'finance', image: '/assets/img/modules/tour/finance.webp?v=1', nav: 'Finance' },
  { key: 'exams', image: '/assets/img/modules/tour/exams.webp?v=1', nav: 'Exams' },
  { key: 'transport', image: '/assets/img/modules/tour/transport.webp?v=1', nav: 'Transport' },
  { key: 'reports', image: '/assets/img/modules/tour/reports.jpg?v=1', nav: 'Reports' },
];

// Green-theme product screens with sample school data, 1600x900 (2x of an 800x450 layout).
const HERO_SLIDES = [
  { key: 'dashboard', image: '/assets/img/dashboard/hero/dashboard.webp', label: 'Dashboard' },
  { key: 'academics', image: '/assets/img/dashboard/hero/academics.webp', label: 'Academics' },
  { key: 'admissions', image: '/assets/img/dashboard/hero/admissions.webp', label: 'Admissions' },
  { key: 'attendance', image: '/assets/img/dashboard/hero/attendance.webp', label: 'Attendance' },
  { key: 'fees', image: '/assets/img/dashboard/hero/fees.webp', label: 'Fees' },
  { key: 'finance', image: '/assets/img/dashboard/hero/finance.webp', label: 'Finance' },
  { key: 'exams', image: '/assets/img/dashboard/hero/exams.webp', label: 'Examination' },
  { key: 'transport', image: '/assets/img/dashboard/hero/transport.webp', label: 'Transport' },
];

const HERO_PROOF_ICONS = {
  check: ['M20 6 9 17l-5-5'],
  star: ['m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z'],
  lock: ['M5 11h14v10H5z', 'M8 11V7a4 4 0 0 1 8 0v4'],
};

/* Where each hero feature card links to. */
const TRUST_LINKS = {
  modules: '#modules',
  database: '#why',
  audit: '#why',
  roles: '#roles',
};

const SHOWCASE_ICON_MAP = [7, 3, 4, 5, 6, 8, 9, 10];
const SHOWCASE_ICONS = SHOWCASE_ICON_MAP.map((i) => FEATURE_ICONS[i]);
const WHY_ICONS = [
  ['M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4', 'M17 8l-5-5-5 5', 'M12 3v12'],
  ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 11h-6', 'm19 8 3 3-3 3'],
  ['M5 8h14M5 12h14M5 16h8', 'M19 16v4'],
  ['M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z'],
];

const STEP_ICONS = [
  ['M8 7V3m8 4V3M4 11h16', 'M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z', 'm9 16 2 2 4-4'],
  ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'M12 18v-6', 'M9 15h6'],
  ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 10h-6', 'M19 7v6'],
  ['M13 2 3 14h9l-1 8 10-12h-9l1-8Z'],
];

const TRUST_ICONS = {
  modules: ['M4 4h6v6H4z', 'M14 4h6v6h-6z', 'M4 14h6v6H4z', 'M14 14h6v6h-6z'],
  database: ['M12 3c4.4 0 8 1.3 8 3s-3.6 3-8 3-8-1.3-8-3 3.6-3 8-3Z', 'M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6', 'M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6'],
  audit: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'm9 15 2 2 4-4'],
  roles: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
};

const ROLE_ICONS = {
  principal: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
  fees: ['M12 2v20', 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
  teacher: ['M22 10v6M2 10l10-5 10 5-10 5z', 'M6 12v5c3 3 9 3 12 0v-5'],
  office: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
};

const PROBLEM_ICONS = {
  systems: {
    pain: ['M10.3 3.2a2 2 0 0 1 3.4 0l7.4 12.8A2 2 0 0 1 19.4 19H4.6a2 2 0 0 1-1.7-3l7.4-12.8Z', 'M12 9v4', 'M12 17h.01'],
    fix: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z', 'm9 12 2 2 4-4'],
  },
  fees: {
    pain: ['M12 1v22', 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', 'M4 4l16 16'],
    fix: ['M12 2v20', 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
  },
  records: {
    pain: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z', 'M14 2v6h6', 'M8 13h2', 'M14 13h2', 'M8 17h8'],
    fix: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M22 11h-6', 'm19 8 3 3-3 3'],
  },
  reports: {
    pain: ['M3 3v18h18', 'M7 16v-3', 'M12 16V9', 'M17 16v-5', 'M4 4l16 16'],
    fix: ['M3 3v18h18', 'M7 14v4', 'M12 10v8', 'M17 6v12'],
  },
};

const CONTENT_EN = {
  skipLink: 'Skip to content',
  navLinks: [
    { label: 'Home', href: '#top' },
    { label: 'For your team', href: '#roles' },
    { label: 'Modules', href: '#modules' },
    { label: 'Why ERPSaathi', href: '#why' },
    { label: 'Contact', href: '#contact' },
  ],
  navRequestDemo: 'Request a Demo',

  heroPillSuffix: 'School ERP for India',
  heroTitleBefore: 'One system for',
  heroTitleHighlight: 'every school department',
  heroTitleAfter: '',
  heroSub: 'Students, academics, attendance, fees, finance, transport, exams and reports — all from one secure school ERP.',
  heroCtaPrimary: 'Request a Demo',
  heroCtaSecondary: 'See who it’s for',
  // TODO(confirm figures): school count and rating are placeholders until confirmed.
  heroProof: [
    { key: 'check', text: 'Trusted by 50+ schools' },
    { key: 'star', text: '4.8 rating' },
    { key: 'lock', text: 'Data stored securely in India' },
  ],

  trustItems: [
    { key: 'modules', label: '13+ Modules', hint: 'Every school department' },
    { key: 'database', label: 'One Database', hint: 'Single source of truth' },
    { key: 'audit', label: 'Audit Logging', hint: 'Track every change' },
    { key: 'roles', label: 'Role-Based Access', hint: 'Secure staff permissions' },
  ],

  problemEyebrow: 'The problem',
  problemTitle: 'Schools still run on disconnected tools',
  problemTitleLead: 'Schools still run on',
  problemTitleTail: 'disconnected tools',
  problemLead: 'See the everyday school chaos, and how ERPSaathi turns it into one clear system.',
  compareBeforeHeading: 'Before ERPSaathi',
  compareAfterHeading: 'With ERPSaathi',
  problemSceneBefore: 'Spreadsheets, registers, WhatsApp and fee software that never talk to each other.',
  problemSceneAfter: 'One secure ERP where every department shares the same student record.',
  problemScatter: ['Excel fees', 'Paper registers', 'WhatsApp updates', 'Separate exam softwares', 'Manual attendance'],
  problemHub: ['Fees', 'Reports', 'Attendance', 'Admission', 'Transport', 'Exams'],
  problemStories: [
    {
      key: 'systems',
      pain: { title: 'Tools that do not talk', desc: 'Admissions, fees and academics live in different places, so staff re-enter the same data.' },
      fix: { title: 'One connected platform', desc: 'Every department works in the same ERP — update once, and it is available everywhere.' },
    },
    {
      key: 'fees',
      pain: { title: 'Fee dues get missed', desc: 'Pending and partial payments hide in notebooks, so follow-ups are late and collections slip.' },
      fix: { title: 'Live fee visibility', desc: 'See dues, receipts and collection reports in real time — and print them instantly.' },
    },
    {
      key: 'records',
      pain: { title: 'Student data is scattered', desc: 'Profiles sit across registers and files, so nobody trusts a single version of the truth.' },
      fix: { title: 'One student record', desc: 'A shared profile for academics, fees, attendance and documents — always up to date.' },
    },
    {
      key: 'reports',
      pain: { title: 'Reports take hours', desc: 'Staff compile numbers by hand before board meetings, inspections or parent queries.' },
      fix: { title: 'Instant school reports', desc: 'Filter and download the reports you need in minutes — ready for principals and parents.' },
    },
  ],

  rolesEyebrow: 'For your team',
  rolesTitle: 'One ERP, every school role',
  rolesLead: 'Principals, fee staff, teachers and office teams work from the same records — without switching tools.',
  rolesCtaLead: 'Every role, one login, same data',
  rolesCta: 'Take the product tour',
  roles: [
    {
      key: 'principal',
      tag: 'Leadership',
      title: 'Principal / Management',
      desc: 'See the whole school picture without waiting for spreadsheet reports.',
      points: ['Live fee & attendance overview', 'Role-based staff access', 'Export-ready school reports'],
    },
    {
      key: 'fees',
      tag: 'Accounts',
      title: 'Fee & Finance Office',
      desc: 'Collect fees, track dues and keep cash/bank entries clean — every day.',
      points: ['Dues & partial payments', 'Printable receipts', 'Expense & payroll control'],
    },
    {
      key: 'teacher',
      tag: 'Academics',
      title: 'Teachers & Class Teachers',
      desc: 'Mark attendance, manage homework and publish results from one place.',
      points: ['Daily attendance', 'Homework & class work', 'Marks and report cards'],
    },
    {
      key: 'office',
      tag: 'Front office',
      title: 'Admissions & Admin',
      desc: 'Move enquiries to admission, keep student files complete, and stay UDISE-ready.',
      points: ['Enquiry to admission flow', 'Student documents', 'UDISE+ form prep'],
    },
  ],

  showcaseEyebrow: 'Product tour',
  showcaseTitle: 'See every module in action',
  showcaseLead: 'Real screens from ERPSaathi, one module at a time.',
  showcaseNow: 'Now viewing',
  showcaseView: 'Live module screen',
  showcaseWatching: 'Watching module',
  showcaseScrollHint: 'Scroll to continue',
  showcaseNavLabel: 'Jump to module',
  showcaseLightboxClose: 'Close screenshot',
  showcaseModules: [
    {
      title: 'Academics',
      short: 'Classes & subjects',
      desc: 'Classes, sections, subjects and homework in one structured hierarchy.',
      bullets: ['Session 2026-27 class & section tree', 'Subject teachers and weekly timetable', 'Syllabus progress by class'],
    },
    {
      title: 'Admissions',
      short: 'Enquiry to admit',
      desc: 'Enquiry to registration to admission — one pipeline with shared student records.',
      bullets: ['Kanban pipeline from enquiry to admit', 'Follow-ups with parent contacts', 'Convert to student without re-entry'],
    },
    {
      title: 'Attendance',
      short: 'Daily tracking',
      desc: 'Daily attendance for students and staff, with leave and monthly summaries.',
      bullets: ['Mark class attendance in seconds', 'Leave and late tracking', 'Monthly % summaries for parents'],
    },
    {
      title: 'Fee Management',
      short: 'Dues & receipts',
      desc: 'Structures, dues, collection and accounting-ready fee history.',
      bullets: ['Collect fee with UPI, cash or bank', 'Live dues by class and fee head', 'Printable receipts with ₹ amounts'],
    },
    {
      title: 'Finance & Payroll',
      short: 'Expenses & salary',
      desc: 'Office expenses, salary slips, bank accounts and cash control.',
      bullets: ['Expense entries with ledger heads', 'Staff salary slips for 2026-27', 'Cash and bank balance overview'],
    },
    {
      title: 'Examination',
      short: 'Marks & results',
      desc: 'Schedules, marks, results, admit cards and report cards.',
      bullets: ['Exam schedules by class', 'Marks entry with subject teachers', 'Report cards ready to print'],
    },
    {
      title: 'Transport',
      short: 'Routes & vehicles',
      desc: 'Routes, stops, vehicles and drivers linked to student transport.',
      bullets: ['Routes and stop-wise students', 'Vehicle and driver assignment', 'Transport fee link to fee module'],
    },
    {
      title: 'Reports',
      short: 'Export ready',
      desc: 'Class-wise, area-wise and finance reports with controlled export.',
      bullets: ['Attendance, fee and mark reports', 'PDF / XLSX export controls', 'Filter by class, date and branch'],
    },
  ],

  udiseEyebrow: 'Built for Indian schools',
  udiseTitle: 'UDISE+ compliance without duplicate work',
  udiseLead: 'Prepare forms and maintain registry status from the same student records already in ERPSaathi.',
  udiseCaps: [
    { title: 'UDISE+ S02', desc: 'Prepare and print forms for students not yet enrolled on UDISE.' },
    { title: 'UDISE+ S03', desc: 'Maintain the student registry, PEN and enrolment status.' },
  ],
  udiseProof: 'No separate spreadsheet.',

  whyEyebrow: 'Why ERPSaathi',
  whyTitle: 'Support that schools actually need',
  whyItems: [
    { title: 'Free data migration', desc: 'We import your Excel sheets and register data into ERPSaathi.' },
    { title: 'Staff training included', desc: 'Hands-on training for office, accounts and teaching staff.' },
    { title: 'Hindi + English interface', desc: 'Switch language any time — staff work in the language they prefer.' },
    { title: 'WhatsApp & phone support', desc: 'Reach us on WhatsApp or phone when your school needs help.' },
  ],

  stepsEyebrow: 'Getting started',
  stepsTitle: 'How it works',
  stepsLead: 'From first call to go-live — we handle the heavy lifting.',
  steps: [
    { title: 'Book a demo', desc: 'See ERPSaathi with your school’s workflows in a live walkthrough.' },
    { title: 'We import your data', desc: 'Students, fees and class structure move from Excel into the ERP.' },
    { title: 'We train your staff', desc: 'Accounts, office and teachers learn the modules they will use daily.' },
    { title: 'Go live in 7 days', desc: 'Most schools start daily operations within a week of kickoff.' },
  ],
  stepsCta: 'Book a demo',

  testimonialsEyebrow: 'Schools like yours',
  testimonialsTitle: 'What principals say',
  testimonials: [
    {
      quote: 'Fee follow-ups used to take days. Now the office sees dues the same afternoon and collections are clearer.',
      name: 'Mrs. Sunita Mehra',
      role: 'Principal',
      school: 'Green Valley Public School',
      city: 'Jaipur',
    },
    {
      quote: 'Attendance, fees and report cards finally sit in one place. Teachers stopped re-entering the same student data.',
      name: 'Mr. Rakesh Verma',
      role: 'Principal',
      school: 'St. Mary’s Academy',
      city: 'Indore',
    },
    {
      quote: 'UDISE forms no longer need a separate spreadsheet. Our office prepares S02 from the same student list.',
      name: 'Mrs. Anjali Nair',
      role: 'Principal',
      school: 'Horizon English Medium School',
      city: 'Pune',
    },
  ],

  faqEyebrow: 'FAQ',
  faqTitle: 'Questions school owners ask',
  faqs: [
    { q: 'What is ERPSaathi?', a: 'A school ERP that connects admissions, academics, attendance, fees, exams, finance, transport and UDISE+ in one system.' },
    { q: 'How much does it cost?', a: 'Plans start from ₹120 per student per year. We share a clear quote after a short demo based on your modules and strength.' },
    { q: 'Can you migrate data from Excel?', a: 'Yes. We import students, classes, fees and related registers from Excel or CSV as part of onboarding — at no extra charge.' },
    { q: 'Where is school data stored, and is it safe?', a: 'Data is stored securely in India on access-controlled servers, with role-based permissions and audit logging for staff actions.' },
    { q: 'Is there a mobile or parent app?', a: 'Staff use the web ERP on desktop and mobile browsers. Parent-facing updates (fees, attendance) can be shared as your school rolls them out — ask us on the demo.' },
    { q: 'How long do setup and training take?', a: 'Most schools go live in about 7 days after kickoff, including data import and staff training for office and accounts teams.' },
    { q: 'Do you support Hindi?', a: 'Yes. ERPSaathi supports Hindi and English, so staff can work in the language they are comfortable with.' },
    { q: 'Does it support UDISE+?', a: 'Yes. S02 and S03 workflows, PEN tracking and enrolment status stay synced with the same student records.' },
  ],

  ctaTitle: 'Ready to run your school on one system?',
  ctaSub: 'Explore a live demo school, or chat with us on WhatsApp about getting ERPSaathi set up.',
  ctaPrimary: 'Request a Demo',
  ctaSecondary: 'Chat on WhatsApp',

  footerTagline: 'One system for every school department.',
  footerAddress: 'Jaipur, Rajasthan, India',
  footerPhone: '+91 99428 82661',
  footerPhoneTel: '+919942882661',
  footerEmail: 'hello@erpsaathi.com',
  footerLinksHeading: 'Quick links',
  footerLinks: { home: 'Home', features: 'For your team', modules: 'Modules', contact: 'Contact', requestDemo: 'Request Demo' },
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
    { label: 'आपकी टीम के लिए', href: '#roles' },
    { label: 'मॉड्यूल', href: '#modules' },
    { label: 'ERPSaathi क्यों', href: '#why' },
    { label: 'संपर्क करें', href: '#contact' },
  ],
  navRequestDemo: 'डेमो के लिए अनुरोध करें',

  heroPillSuffix: 'भारत के लिए स्कूल ERP',
  heroTitleBefore: '',
  heroTitleHighlight: 'हर स्कूल विभाग',
  heroTitleAfter: 'के लिए एकीकृत सिस्टम',
  heroSub: 'छात्र, शैक्षणिक कार्य, उपस्थिति, फीस, वित्त, परिवहन, परीक्षा और रिपोर्ट — एक सुरक्षित स्कूल ERP से।',
  heroCtaPrimary: 'डेमो के लिए अनुरोध करें',
  heroCtaSecondary: 'किसके लिए है देखें',
  heroProof: [
    { key: 'check', text: '50+ स्कूलों का भरोसा' },
    { key: 'star', text: '4.8 रेटिंग' },
    { key: 'lock', text: 'डेटा भारत में सुरक्षित' },
  ],

  trustItems: [
    { key: 'modules', label: '13+ मॉड्यूल', hint: 'हर स्कूल विभाग' },
    { key: 'database', label: 'एक डेटाबेस', hint: 'एक सत्य स्रोत' },
    { key: 'audit', label: 'ऑडिट लॉगिंग', hint: 'हर बदलाव दर्ज' },
    { key: 'roles', label: 'भूमिका-आधारित पहुँच', hint: 'सुरक्षित स्टाफ़ अनुमति' },
  ],

  problemEyebrow: 'समस्या',
  problemTitle: 'स्कूल अभी भी बिखरे हुए टूल पर चल रहे हैं',
  problemLead: 'रोज़ का स्कूल अस्त-व्यस्तपन देखें, और देखें कि ERPSaathi उसे एक साफ़ सिस्टम कैसे बनाता है।',
  compareBeforeHeading: 'ERPSaathi से पहले',
  compareAfterHeading: 'ERPSaathi के साथ',
  problemSceneBefore: 'एक्सेल, रजिस्टर, व्हाट्सऐप और फीस सॉफ़्टवेयर — जो एक-दूसरे से जुड़ते ही नहीं।',
  problemSceneAfter: 'एक सुरक्षित ERP जहाँ हर विभाग एक ही छात्र रिकॉर्ड साझा करता है।',
  problemScatter: ['एक्सेल फीस', 'पेपर रजिस्टर', 'व्हाट्सऐप अपडेट', 'अलग परीक्षा सॉफ़्टवेयर', 'मैन्युअल उपस्थिति'],
  problemHub: ['फीस', 'रिपोर्ट', 'उपस्थिति', 'प्रवेश', 'परिवहन', 'परीक्षा'],
  problemStories: [
    {
      key: 'systems',
      pain: { title: 'टूल एक-दूसरे से नहीं जुड़ते', desc: 'प्रवेश, फीस और शैक्षणिक काम अलग जगहों पर होते हैं, इसलिए स्टाफ़ वही डेटा बार-बार भरता है।' },
      fix: { title: 'एक जुड़ा प्लेटफ़ॉर्म', desc: 'हर विभाग एक ही ERP में काम करता है — एक बार अपडेट करें, हर जगह उपलब्ध।' },
    },
    {
      key: 'fees',
      pain: { title: 'फीस बकाया छूट जाता है', desc: 'बकाया और आंशिक भुगतान कॉपियों में छिपे रहते हैं, इसलिए फॉलो-अप देर से होता है।' },
      fix: { title: 'लाइव फीस विज़िबिलिटी', desc: 'बकाया, रसीदें और वसूली रिपोर्ट रीयल-टाइम में देखें — और तुरंत प्रिंट करें।' },
    },
    {
      key: 'records',
      pain: { title: 'छात्र डेटा बिखरा है', desc: 'प्रोफ़ाइल रजिस्टरों और फ़ाइलों में फैली रहती हैं, इसलिए कोई एक सही संस्करण पर भरोसा नहीं करता।' },
      fix: { title: 'एक छात्र रिकॉर्ड', desc: 'शैक्षणिक, फीस, उपस्थिति और दस्तावेज़ों के लिए साझा प्रोफ़ाइल — हमेशा अपडेटेड।' },
    },
    {
      key: 'reports',
      pain: { title: 'रिपोर्ट में घंटे लगते हैं', desc: 'मीटिंग, इंस्पेक्शन या पेरेंट क्वेरी से पहले स्टाफ़ हाथ से आँकड़े जोड़ता है।' },
      fix: { title: 'तुरंत स्कूल रिपोर्ट', desc: 'ज़रूरत के अनुसार फ़िल्टर कर मिनटों में रिपोर्ट डाउनलोड करें — प्रिंसिपल और अभिभावकों के लिए तैयार।' },
    },
  ],

  rolesEyebrow: 'आपकी टीम के लिए',
  rolesTitle: 'एक ERP, हर स्कूल भूमिका',
  rolesLead: 'प्रिंसिपल, फीस स्टाफ़, शिक्षक और ऑफिस टीम एक ही रिकॉर्ड से काम करते हैं — अलग-अलग टूल के बिना।',
  rolesCtaLead: 'हर भूमिका, एक लॉगिन, वही डेटा',
  rolesCta: 'प्रोडक्ट टूर देखें',
  roles: [
    {
      key: 'principal',
      tag: 'लीडरशिप',
      title: 'प्रिंसिपल / प्रबंधन',
      desc: 'स्प्रेडशीट रिपोर्ट का इंतज़ार किए बिना पूरे स्कूल की तस्वीर देखें।',
      points: ['लाइव फीस और उपस्थिति ओवरव्यू', 'भूमिका-आधारित स्टाफ़ पहुँच', 'एक्सपोर्ट-तैयार स्कूल रिपोर्ट'],
    },
    {
      key: 'fees',
      tag: 'अकाउंट्स',
      title: 'फीस और वित्त ऑफिस',
      desc: 'फ़ीस वसूली, बकाया ट्रैकिंग और नकद/बैंक एंट्री रोज़ साफ़ रखें।',
      points: ['बकाया और आंशिक भुगतान', 'प्रिंट योग्य रसीदें', 'व्यय और पेरोल नियंत्रण'],
    },
    {
      key: 'teacher',
      tag: 'शैक्षणिक',
      title: 'शिक्षक और क्लास टीचर',
      desc: 'उपस्थिति, होमवर्क और परिणाम एक ही जगह से संभालें।',
      points: ['दैनिक उपस्थिति', 'होमवर्क और क्लास वर्क', 'अंक और रिपोर्ट कार्ड'],
    },
    {
      key: 'office',
      tag: 'फ्रंट ऑफिस',
      title: 'प्रवेश और एडमिन',
      desc: 'पूछताछ से प्रवेश तक पहुँचाएँ, छात्र फ़ाइलें पूरी रखें, और UDISE-तैयार रहें।',
      points: ['पूछताछ से प्रवेश फ्लो', 'छात्र दस्तावेज़', 'UDISE+ फॉर्म तैयारी'],
    },
  ],

  showcaseEyebrow: 'प्रोडक्ट टूर',
  showcaseTitle: 'हर मॉड्यूल को काम करते देखें',
  showcaseLead: 'ERPSaathi की असली स्क्रीन, एक मॉड्यूल एक समय।',
  showcaseNow: 'अभी देख रहे हैं',
  showcaseView: 'लाइव मॉड्यूल स्क्रीन',
  showcaseWatching: 'मॉड्यूल',
  showcaseScrollHint: 'आगे स्क्रॉल करें',
  showcaseNavLabel: 'मॉड्यूल पर जाएँ',
  showcaseLightboxClose: 'स्क्रीनशॉट बंद करें',
  showcaseModules: [
    {
      title: 'शैक्षणिक',
      short: 'कक्षाएँ और विषय',
      desc: 'कक्षाएँ, सेक्शन, विषय और होमवर्क एक संरचित पदानुक्रम में।',
      bullets: ['सत्र 2026-27 कक्षा और सेक्शन', 'विषय शिक्षक और साप्ताहिक टाइमटेबल', 'कक्षा-वार सिलेबस प्रगति'],
    },
    {
      title: 'प्रवेश',
      short: 'पूछताछ से प्रवेश',
      desc: 'पूछताछ से पंजीकरण से प्रवेश तक — साझा छात्र रिकॉर्ड के साथ एक प्रक्रिया।',
      bullets: ['पूछताछ से प्रवेश तक पाइपलाइन', 'अभिभावक फॉलो-अप', 'बिना दोबारा भरे छात्र बनाएँ'],
    },
    {
      title: 'उपस्थिति',
      short: 'दैनिक ट्रैकिंग',
      desc: 'छात्रों और स्टाफ़ की दैनिक उपस्थिति, अवकाश और मासिक सारांश।',
      bullets: ['कक्षा उपस्थिति सेकंडों में', 'अवकाश और लेट ट्रैकिंग', 'अभिभावकों के लिए मासिक %'],
    },
    {
      title: 'फीस प्रबंधन',
      short: 'बकाया और रसीदें',
      desc: 'संरचनाएँ, बकाया, वसूली और खाता-तैयार फीस इतिहास।',
      bullets: ['UPI, नकद या बैंक से वसूली', 'कक्षा और फीस हेड से बकाया', '₹ राशि के साथ प्रिंटेबल रसीद'],
    },
    {
      title: 'वित्त और पेरोल',
      short: 'व्यय और वेतन',
      desc: 'कार्यालय व्यय, वेतन पर्ची, बैंक खाते और नकद नियंत्रण।',
      bullets: ['लेजर हेड के साथ व्यय', '2026-27 वेतन पर्ची', 'नकद और बैंक बैलेंस'],
    },
    {
      title: 'परीक्षा',
      short: 'अंक और परिणाम',
      desc: 'समय सारिणी, अंक, परिणाम, प्रवेश पत्र और रिपोर्ट कार्ड।',
      bullets: ['कक्षा-वार परीक्षा शेड्यूल', 'विषय शिक्षकों से अंक एंट्री', 'प्रिंट-तैयार रिपोर्ट कार्ड'],
    },
    {
      title: 'परिवहन',
      short: 'रूट और वाहन',
      desc: 'रूट, स्टॉप, वाहन और ड्राइवर छात्र परिवहन से जुड़े।',
      bullets: ['रूट और स्टॉप-वार छात्र', 'वाहन और ड्राइवर असाइनमेंट', 'फीस मॉड्यूल से लिंक'],
    },
    {
      title: 'रिपोर्ट',
      short: 'एक्सपोर्ट तैयार',
      desc: 'नियंत्रित एक्सपोर्ट के साथ कक्षा-वार, क्षेत्र-वार और वित्तीय रिपोर्ट।',
      bullets: ['उपस्थिति, फीस और अंक रिपोर्ट', 'PDF / XLSX एक्सपोर्ट', 'कक्षा, तिथि और शाखा फ़िल्टर'],
    },
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
  whyTitle: 'स्कूलों को असल में चाहिए वैसा सपोर्ट',
  whyItems: [
    { title: 'मुफ़्त डेटा माइग्रेशन', desc: 'हम आपके एक्सेल और रजिस्टर डेटा को ERPSaathi में इम्पोर्ट करते हैं।' },
    { title: 'स्टाफ़ ट्रेनिंग शामिल', desc: 'ऑफिस, अकाउंट्स और शिक्षकों के लिए हैंड्स-ऑन ट्रेनिंग।' },
    { title: 'हिंदी + अंग्रेज़ी इंटरफ़ेस', desc: 'जब चाहें भाषा बदलें — स्टाफ़ अपनी सुविधा की भाषा में काम करे।' },
    { title: 'व्हाट्सऐप और फ़ोन सपोर्ट', desc: 'ज़रूरत पड़ने पर व्हाट्सऐप या फ़ोन पर हमसे संपर्क करें।' },
  ],

  stepsEyebrow: 'शुरुआत करें',
  stepsTitle: 'यह कैसे काम करता है',
  stepsLead: 'पहली कॉल से गो-लाइव तक — भारी काम हम संभालते हैं।',
  steps: [
    { title: 'डेमो बुक करें', desc: 'लाइव वॉकथ्रू में अपने स्कूल के वर्कफ़्लो के साथ ERPSaathi देखें।' },
    { title: 'हम डेटा इम्पोर्ट करते हैं', desc: 'छात्र, फीस और कक्षा संरचना एक्सेल से ERP में आती है।' },
    { title: 'हम स्टाफ़ को ट्रेन करते हैं', desc: 'अकाउंट्स, ऑफिस और शिक्षक रोज़ाना वाले मॉड्यूल सीखते हैं।' },
    { title: '7 दिनों में गो-लाइव', desc: 'ज़्यादातर स्कूल किकऑफ़ के एक सप्ताह में दैनिक संचालन शुरू करते हैं।' },
  ],
  stepsCta: 'डेमो बुक करें',

  testimonialsEyebrow: 'आप जैसे स्कूल',
  testimonialsTitle: 'प्रिंसिपल क्या कहते हैं',
  testimonials: [
    {
      quote: 'फीस फॉलो-अप में पहले दिन लगते थे। अब ऑफिस उसी दोपहर बकाया देख लेता है और वसूली साफ़ रहती है।',
      name: 'श्रीमती सुनीता मेहरा',
      role: 'प्रिंसिपल',
      school: 'ग्रीन वैली पब्लिक स्कूल',
      city: 'जयपुर',
    },
    {
      quote: 'उपस्थिति, फीस और रिपोर्ट कार्ड अब एक जगह हैं। शिक्षकों को वही छात्र डेटा दोबारा नहीं भरना पड़ता।',
      name: 'श्री राकेश वर्मा',
      role: 'प्रिंसिपल',
      school: 'सेंट मैरी अकादमी',
      city: 'इंदौर',
    },
    {
      quote: 'UDISE फॉर्म के लिए अलग स्प्रेडशीट नहीं चाहिए। ऑफिस उसी छात्र सूची से S02 तैयार करता है।',
      name: 'श्रीमती अंजलि नायर',
      role: 'प्रिंसिपल',
      school: 'होराइज़न इंग्लिश मीडियम स्कूल',
      city: 'पुणे',
    },
  ],

  faqEyebrow: 'FAQ',
  faqTitle: 'स्कूल मालिक अक्सर जो सवाल पूछते हैं',
  faqs: [
    { q: 'ERPSaathi क्या है?', a: 'एक स्कूल ERP जो प्रवेश, शैक्षणिक, उपस्थिति, फीस, परीक्षा, वित्त, परिवहन और UDISE+ को एक सिस्टम में जोड़ता है।' },
    { q: 'कीमत कितनी है?', a: 'प्लान ₹120 प्रति छात्र प्रति वर्ष से शुरू होते हैं। डेमो के बाद आपके मॉड्यूल और स्ट्रेंथ के आधार पर स्पष्ट कोट देते हैं।' },
    { q: 'क्या एक्सेल से डेटा माइग्रेट कर सकते हैं?', a: 'हाँ। ऑनबोर्डिंग में हम छात्र, कक्षा, फीस और संबंधित रजिस्टर एक्सेल/CSV से इम्पोर्ट करते हैं — बिना अतिरिक्त शुल्क।' },
    { q: 'डेटा कहाँ सुरक्षित रहता है?', a: 'डेटा भारत में एक्सेस-कंट्रोल्ड सर्वर पर सुरक्षित रखा जाता है, भूमिका-आधारित अनुमति और ऑडिट लॉगिंग के साथ।' },
    { q: 'क्या मोबाइल या पेरेंट ऐप है?', a: 'स्टाफ़ वेब ERP डेस्कटॉप और मोबाइल ब्राउज़र पर उपयोग करते हैं। पेरेंट अपडेट (फीस, उपस्थिति) स्कूल की रोलआउट योजना के अनुसार — डेमो में पूछें।' },
    { q: 'सेटअप और ट्रेनिंग में कितना समय लगता है?', a: 'ज़्यादातर स्कूल किकऑफ़ के लगभग 7 दिनों में गो-लाइव हो जाते हैं, जिसमें डेटा इम्पोर्ट और स्टाफ़ ट्रेनिंग शामिल है।' },
    { q: 'क्या हिंदी सपोर्ट है?', a: 'हाँ। ERPSaathi हिंदी और अंग्रेज़ी दोनों सपोर्ट करता है।' },
    { q: 'क्या यह UDISE+ सपोर्ट करता है?', a: 'हाँ। S02 और S03 वर्कफ़्लो, PEN ट्रैकिंग और नामांकन स्थिति उसी छात्र रिकॉर्ड से सिंक रहती हैं।' },
  ],

  ctaTitle: 'अपने स्कूल को एक सिस्टम पर चलाने के लिए तैयार हैं?',
  ctaSub: 'लाइव डेमो स्कूल देखें, या व्हाट्सऐप पर ERPSaathi सेटअप के बारे में बात करें।',
  ctaPrimary: 'डेमो के लिए अनुरोध करें',
  ctaSecondary: 'व्हाट्सऐप पर चैट करें',

  footerTagline: 'हर स्कूल विभाग के लिए एक सिस्टम।',
  footerAddress: 'जयपुर, राजस्थान, भारत',
  footerPhone: '+91 99428 82661',
  footerPhoneTel: '+919942882661',
  footerEmail: 'hello@erpsaathi.com',
  footerLinksHeading: 'क्विक लिंक',
  footerLinks: { home: 'होम', features: 'आपकी टीम के लिए', modules: 'मॉड्यूल', contact: 'संपर्क करें', requestDemo: 'डेमो का अनुरोध करें' },
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
      faqOpen: 0,
      heroSlides: HERO_SLIDES,
      heroSlideIndex: 0,
      heroPrevSlideIndex: -1,
      heroSliderPaused: false,
      heroSliderTimer: null,
      heroSlideMs: 5200,

      contentEn: CONTENT_EN,
      contentHi: CONTENT_HI,
      showcaseMeta: SHOWCASE_META,
      showcaseIcons: SHOWCASE_ICONS,
      whyIcons: WHY_ICONS,
      stepIcons: STEP_ICONS,
      trustIcons: TRUST_ICONS,
      trustLinks: TRUST_LINKS,
      heroProofIcons: HERO_PROOF_ICONS,
      problemIcons: PROBLEM_ICONS,
      roleIcons: ROLE_ICONS,

      slideIndex: 0,
      prevSlideIndex: -1,
      tourStepEls: [],
      tourObserver: null,
      tourScrollLock: false,
      tourLightboxIndex: null,

      udiseImage: '/assets/img/modules/module-udise-ui.jpg?v=1',
    };
  },
  computed: {
    c() {
      return this.locale === 'hi' ? this.contentHi : this.contentEn;
    },
    brandLogoUrl() {
      const url = this.logoUrl || DEFAULTS.logoUrl;
      const isBrandDefault =
        !url ||
        url.includes('erpsaathi-logo') ||
        url.includes('new-logo.png') ||
        url.includes('new-logo2.png');
      if (!isBrandDefault) return url;
      return '/assets/img/logo/new-logo2.png?v=1';
    },
    primaryHref() {
      return this.authenticated ? this.dashboardUrl : this.loginUrl;
    },
    primaryLabel() {
      if (this.authenticated) return this.locale === 'hi' ? 'डैशबोर्ड' : 'Dashboard';
      return this.locale === 'hi' ? 'लॉगिन' : 'Login';
    },
    tourProgress() {
      const total = this.c.showcaseModules.length;
      if (total <= 1) return 0;
      return (this.slideIndex / (total - 1)) * 100;
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
    this.onTourKeydown = (e) => {
      if (e.key === 'Escape' && this.tourLightboxIndex !== null) this.closeTourLightbox();
    };
    window.addEventListener('keydown', this.onTourKeydown);
    this.startHeroSlider();

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        this.heroIn = true;
      });
    });
    this.$nextTick(() => {
      this.preloadTourImages();
      this.updateTourFromScroll();
    });
  },
  beforeUnmount() {
    const root = document.documentElement;
    root.style.removeProperty('background-color');
    document.body.style.overflow = '';
    window.removeEventListener('scroll', this.onScroll);
    window.removeEventListener('keydown', this.onTourKeydown);
    this.unbindTourObserver();
    this.stopHeroSlider();
  },
  methods: {
    hubSpokeXY(i) {
      const r = 78;
      const rad = (i * 60 - 90) * (Math.PI / 180);
      return {
        x: Number((140 + r * Math.cos(rad)).toFixed(1)),
        y: Number((140 + r * Math.sin(rad)).toFixed(1)),
      };
    },
    setTourStepRef(el, index) {
      if (el) this.tourStepEls[index] = el;
      else if (this.tourStepEls[index]) this.tourStepEls[index] = null;
    },
    jumpTour(index) {
      if (index < 0 || index >= this.showcaseMeta.length) return;
      this.prevSlideIndex = this.slideIndex;
      this.slideIndex = index;
      this.tourScrollLock = true;
      const el = document.getElementById('tour-' + this.showcaseMeta[index].key);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.setTimeout(() => { this.tourScrollLock = false; }, 700);
    },
    openTourLightbox(index) {
      this.tourLightboxIndex = index;
      document.body.style.overflow = 'hidden';
    },
    closeTourLightbox() {
      this.tourLightboxIndex = null;
      document.body.style.overflow = '';
    },
    preloadTourImages() {
      (this.showcaseMeta || []).forEach((meta) => {
        if (!meta || !meta.image) return;
        const img = new Image();
        img.decoding = 'async';
        img.src = meta.image;
      });
    },
    bindTourObserver() {
      /* Scroll-sync is handled in updateTourFromScroll for reliability with sticky layout. */
    },
    unbindTourObserver() {
      if (this.tourObserver) {
        this.tourObserver.disconnect();
        this.tourObserver = null;
      }
    },
    updateTourFromScroll() {
      if (this.tourScrollLock) return;
      const steps = (this.tourStepEls || []).filter(Boolean);
      if (!steps.length) return;

      // Pick the step whose content is closest to the upper-middle of the viewport
      // (aligned with the sticky preview's visual focus).
      const marker = Math.min(220, window.innerHeight * 0.28);
      let bestIdx = this.slideIndex;
      let bestDist = Infinity;

      steps.forEach((el) => {
        const idx = Number(el.getAttribute('data-tour-index'));
        if (Number.isNaN(idx)) return;
        const rect = el.getBoundingClientRect();
        // Ignore steps that are fully off-screen
        if (rect.bottom < 40 || rect.top > window.innerHeight - 40) return;
        const focusY = rect.top + Math.min(120, rect.height * 0.25);
        const dist = Math.abs(focusY - marker);
        if (dist < bestDist) {
          bestDist = dist;
          bestIdx = idx;
        }
      });

      if (bestIdx !== this.slideIndex) {
        this.prevSlideIndex = this.slideIndex;
        this.slideIndex = bestIdx;
      }
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
        this.updateTourFromScroll();

        this.scrollTicking = false;
      });
    },
    applyTheme(dark) {
      const bg = dark ? 'var(--rng-navy-deep)' : 'var(--rng-surface)';
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
  /* School Green — tokens from erp-theme-school-green.css (:root).
     brand-500 = links/icons; brand-600 = CTA buttons (AA); brand-950 = dark bands. */
  --brand-50: var(--rng-primary-light);
  --brand-100: var(--rng-primary-light);
  --brand-500: var(--rng-brand-500);
  --brand-600: var(--rng-primary);
  --brand-700: var(--rng-primary-hover);
  --brand-950: var(--rng-navy-deep);
  --violet-500: var(--rng-brand-500);
  --green: var(--rng-success);
  --red: var(--rng-danger);

  --bg: var(--rng-surface);
  --bg-tint: var(--rng-bg);
  --surface: var(--rng-surface);
  --surface-2: var(--rng-bg);
  --border: var(--rng-border);
  --border-soft: rgba(15, 23, 42, 0.06);
  --text: var(--rng-text);
  --text-muted: var(--rng-text-muted);
  --text-dim: var(--rng-placeholder);
  --shadow-sm: var(--rng-shadow);
  --shadow-md: 0 8px 24px rgba(15, 23, 42, 0.06);
  --shadow-lg: 0 16px 40px rgba(15, 23, 42, 0.08);
  --glow: rgba(21, 128, 61, 0.18);
  --hero-bg: #F0FDF4;

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
  /* Dark theme: deep navy surfaces; gold accent text (7:1 on navy) replaces navy accents. */
  --brand-500: #4ADE80;
  --brand-600: #86EFAC;
  --hero-bg: var(--bg);
  --brand-700: #BBF7D0;
  --brand-50: rgb(22 163 74 / 0.18);
  --bg: var(--rng-navy-deep);
  --bg-tint: var(--rng-navy-mid);
  --surface: var(--rng-navy-mid);
  --surface-2: var(--rng-navy-deep);
  --border: rgb(255 255 255 / 0.12);
  --border-soft: rgb(255 255 255 / 0.07);
  --text: var(--rng-dark-text);
  --text-muted: var(--rng-dark-text-muted);
  --text-dim: var(--rng-dark-text-dim);
  --glow: rgba(74, 222, 128, 0.22);
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 12px 32px rgba(0, 0, 0, 0.35);
  --shadow-lg: 0 24px 60px rgba(0, 0, 0, 0.45);
}
.erp-site.dark .erp-eyebrow {
  color: var(--brand-500);
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
.erp-nav-spacer { height: 96px; }
.erp-nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: 96px;
  transition: height 0.2s ease;
}
.erp-nav-bar.is-scrolled .erp-nav-inner { height: 84px; }
.erp-brand { display: flex; align-items: center; text-decoration: none; }
.erp-brand-logo { height: 68px; width: auto; max-width: 300px; object-fit: contain; }
.erp-nav-bar .erp-brand-logo { height: 72px; max-width: 320px; }
.erp-nav-bar.is-scrolled .erp-brand-logo { height: 64px; }
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
  width: 18px;
  height: 2px;
  margin: 0 auto;
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
  background: var(--brand-600);
  color: var(--rng-text-on-dark);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
.erp-btn-primary:hover {
  background: var(--brand-700);
  transform: translateY(-1px);
  box-shadow: 0 8px 20px var(--glow);
}
.erp-btn-ghost {
  background: var(--surface);
  color: var(--brand-600);
  border-color: var(--brand-600);
}
.erp-btn-ghost:hover { background: var(--brand-50); border-color: var(--brand-600); }
.erp-btn-outline-light {
  background: transparent;
  color: #fff;
  border-color: rgba(255, 255, 255, 0.85);
}
.erp-btn-outline-light:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: #fff;
  color: #fff;
}
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
.erp-hero { position: relative; padding: 56px 0 48px; overflow: hidden; background: var(--hero-bg); }

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
.erp-hero-eyebrow {
  margin: 0 0 14px;
  font-size: 13.5px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--brand-600);
}
.erp-site.dark .erp-hero-eyebrow { color: var(--brand-500); }
.erp-site.lang-hi .erp-hero-eyebrow { text-transform: none; letter-spacing: 0.02em; }
.erp-hero-title {
  font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
  font-size: clamp(2rem, 4.6vw, 3.1rem);
  font-weight: 800;
  letter-spacing: -0.025em;
  line-height: 1.12;
  margin: 0 0 16px;
  color: var(--text);
  max-width: 15em;
}
.erp-site.lang-hi .erp-hero-title { font-family: 'Noto Sans Devanagari', 'Plus Jakarta Sans', sans-serif; line-height: 1.3; }
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
.erp-hero-proof {
  list-style: none;
  margin: 22px 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  font-size: 14px;
  font-weight: 500;
  color: var(--text-muted);
}
.erp-hero-proof li { display: inline-flex; align-items: center; gap: 7px; }
.erp-hero-proof svg { width: 16px; height: 16px; flex: none; color: var(--brand-500); }

/* ---------- Hero product visual ---------- */
.erp-hero-visual {
  position: relative;
  opacity: 0;
  transform: translateY(18px);
  transition: opacity 0.65s ease 0.08s, transform 0.65s ease 0.08s;
}
.erp-hero-visual.is-in { opacity: 1; transform: none; }
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
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: 0 20px 44px rgba(15, 23, 42, 0.1);
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
  background: var(--surface);
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
  background: var(--surface);
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
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 4px 0;
}
.erp-hero-slide-label {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--text-muted);
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
  background: color-mix(in srgb, var(--text-dim) 55%, transparent);
  cursor: pointer;
  transition: width 0.35s ease, background 0.25s ease;
}
.erp-hero-slide-dot.is-active {
  width: 18px;
  background: var(--brand-600);
}
.erp-hero-slide-dot:focus-visible { outline: 2px solid var(--brand-600); outline-offset: 2px; }

/* Phones: crop the mock's sidebar so the KPI numbers render larger. */
@media (max-width: 640px) {
  .erp-hero { padding: 36px 0 32px; }
  .erp-hero-slides { aspect-ratio: 650 / 450; }
  .erp-hero-shot-img { object-position: right top; }
}
/* 375px-class screens: keep logo + language + theme + menu inside the viewport. */
@media (max-width: 479px) {
  .erp-container { padding: 0 16px; }
  .erp-brand-logo { height: 56px; }
  .erp-nav-bar .erp-brand-logo { height: 56px; }
  .erp-nav-bar.is-scrolled .erp-brand-logo { height: 50px; }
  .erp-nav-spacer { height: 84px; }
  .erp-nav-inner { height: 84px; }
  .erp-nav-bar.is-scrolled .erp-nav-inner { height: 76px; }
  .erp-nav-utility { gap: 6px; }
  .erp-lang-toggle { min-width: 0; padding: 0 10px; }
  .erp-site .erp-trust-bar { grid-template-columns: 1fr; }
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
.erp-section-head { max-width: 48em; margin: 0 auto 56px; text-align: center; }
.erp-section-title {
  font-size: clamp(1.7rem, 4vw, 2.3rem);
  font-weight: 800;
  line-height: 1.2;
  margin: 0 auto 14px;
  max-width: 20em;
  text-wrap: balance;
}
.erp-section-lead {
  font-size: 1.02rem;
  line-height: 1.65;
  color: var(--text-muted);
  margin: 0 auto;
  max-width: 38em;
  text-wrap: balance;
}
.erp-nowrap { white-space: nowrap; }

/* ---------- Trust / proof bar ---------- */
.erp-trust-section {
  position: relative;
  padding: 36px 0;
  border-top: 1px solid var(--border-soft);
  border-bottom: 1px solid var(--border-soft);
  background:
    linear-gradient(90deg, color-mix(in srgb, var(--brand-500) 7%, transparent), transparent 30%, transparent 70%, color-mix(in srgb, var(--violet-500) 7%, transparent)),
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
  gap: 12px;
}
.erp-trust-bar li { list-style: none; margin: 0; padding: 0; }
.erp-trust-chip {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  height: 100%;
  min-height: 76px;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 14%, var(--border-soft));
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--surface) 94%, var(--brand-50)), color-mix(in srgb, var(--surface) 88%, transparent));
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
  overflow: hidden;
  transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
}
.erp-trust-chip::after {
  content: '';
  position: absolute;
  inset: auto -20% -40% auto;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: radial-gradient(circle, color-mix(in srgb, var(--brand-500) 16%, transparent), transparent 70%);
  pointer-events: none;
}
.erp-trust-chip { color: inherit; text-decoration: none; cursor: pointer; }
.erp-trust-chip:focus-visible { outline: 2px solid var(--brand-600); outline-offset: 3px; }
.erp-trust-chip:hover {
  border-color: var(--brand-500);
  box-shadow: 0 14px 28px color-mix(in srgb, var(--brand-500) 12%, transparent);
  transform: translateY(-2px);
}
.erp-trust-icon {
  position: relative;
  z-index: 1;
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 14px;
  color: #fff;
  background: linear-gradient(145deg, var(--brand-500), var(--violet-500));
  box-shadow:
    0 8px 18px color-mix(in srgb, var(--brand-500) 28%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.22);
}
.erp-trust-icon svg {
  width: 22px;
  height: 22px;
  animation: erpTrustIconBob 3.4s ease-in-out infinite;
  animation-delay: calc(var(--trust-i, 0) * 0.22s);
}
.erp-trust-chip.is-modules .erp-trust-icon svg { animation-name: erpTrustIconSpinSoft; animation-duration: 6.5s; }
.erp-trust-chip.is-database .erp-trust-icon svg { animation-name: erpTrustIconPulse; }
.erp-trust-chip.is-audit .erp-trust-icon svg { animation-name: erpTrustIconPop; }
.erp-trust-chip.is-roles .erp-trust-icon svg { animation-name: erpTrustIconBob; }
@keyframes erpTrustIconBob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}
@keyframes erpTrustIconSpinSoft {
  0%, 100% { transform: rotate(0deg); }
  40% { transform: rotate(-8deg); }
  70% { transform: rotate(8deg); }
}
@keyframes erpTrustIconPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}
@keyframes erpTrustIconPop {
  0%, 100% { transform: scale(1) translateY(0); }
  45% { transform: scale(1.1) translateY(-2px); }
  60% { transform: scale(0.96) translateY(0); }
}
.erp-trust-copy {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-width: 0;
  text-align: left;
}
.erp-trust-label {
  font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif;
  font-size: 0.95rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--text);
  line-height: 1.25;
}
.erp-trust-hint {
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--text-muted);
  line-height: 1.35;
}
@media (prefers-reduced-motion: reduce) {
  .erp-trust-section::before,
  .erp-trust-icon svg { animation: none !important; }
  .erp-trust-chip:hover { transform: none; }
}

/* ---------- Reveal ---------- */
.erp-reveal { opacity: 0; transform: translateY(22px); transition: opacity 0.6s ease, transform 0.6s ease; }
.erp-reveal.is-visible { opacity: 1; transform: none; }

/* ---------- Problem / solution journey ---------- */
.erp-problem-section {
  position: relative;
  padding-top: calc(var(--space-section) - 8px);
  overflow: hidden;
  background: transparent;
}
.erp-problem-section::before,
.erp-problem-section::after {
  content: none !important;
  display: none !important;
}

.erp-problem-scene {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  align-items: stretch;
  margin: 0 auto 28px;
  max-width: 68em;
}
.erp-problem-board {
  position: relative;
  padding: 20px 18px 18px;
  border-radius: 18px;
  border: 1px solid var(--border-soft);
  background: #FFFFFF;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
  overflow: hidden;
  min-height: 260px;
  display: flex;
  flex-direction: column;
}
.erp-site.dark .erp-problem-board { background: var(--surface); }
.erp-problem-board.is-before {
  border-color: color-mix(in srgb, var(--red) 18%, #E2E8F0);
  background: #F8FAFC;
}
.erp-site.dark .erp-problem-board.is-before {
  background: color-mix(in srgb, var(--red) 8%, var(--surface));
}
.erp-problem-board.is-after {
  border-color: color-mix(in srgb, var(--brand-500) 24%, #E2E8F0);
  background: #F0FDF4;
  overflow: visible;
}
.erp-site.dark .erp-problem-board.is-after {
  background: color-mix(in srgb, var(--brand-500) 10%, var(--surface));
}
.erp-problem-board-label {
  margin: 0 0 14px;
  font-weight: 750;
  font-size: 0.8rem;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.erp-site.lang-hi .erp-problem-board-label { text-transform: none; letter-spacing: 0.02em; }
.erp-problem-board.is-after .erp-problem-board-label { color: var(--brand-600); }
.erp-site.dark .erp-problem-board.is-after .erp-problem-board-label { color: var(--brand-500); }
.erp-problem-board-note {
  margin: auto 0 0;
  padding-top: 12px;
  font-size: 0.86rem;
  line-height: 1.45;
  color: var(--text-muted);
}

.erp-problem-scatter {
  position: relative;
  flex: 1;
  min-height: 168px;
}
.erp-problem-scatter-chip {
  position: absolute;
  z-index: calc(1 + var(--chip-i, 0));
  display: inline-flex;
  align-items: center;
  padding: 7px 11px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  color: color-mix(in srgb, var(--red) 75%, var(--text));
  background: #FFFFFF;
  border: 1px solid color-mix(in srgb, var(--red) 22%, #E2E8F0);
  box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
  white-space: nowrap;
  animation: erpScatterFloat 4.8s ease-in-out infinite;
  animation-delay: calc(var(--chip-i, 0) * 0.28s);
}
.erp-site.dark .erp-problem-scatter-chip {
  background: color-mix(in srgb, var(--surface) 88%, var(--red));
}
/* Spaced so tilted tags never overlap (incl. float travel) */
.erp-problem-scatter-chip:nth-child(1) { left: 3%; top: 4%; transform: rotate(-8deg); }
.erp-problem-scatter-chip:nth-child(2) { left: 52%; top: 2%; transform: rotate(6deg); }
.erp-problem-scatter-chip:nth-child(3) { left: 6%; top: 36%; transform: rotate(4deg); }
.erp-problem-scatter-chip:nth-child(4) { left: 48%; top: 40%; transform: rotate(-5deg); }
.erp-problem-scatter-chip:nth-child(5) { left: 24%; top: 72%; transform: rotate(3deg); }
@keyframes erpScatterFloat {
  0%, 100% { translate: 0 0; }
  50% { translate: 0 -4px; }
}

.erp-problem-hub {
  --hub-r: 112px;
  position: relative;
  flex: 1;
  min-height: 260px;
  display: grid;
  place-items: center;
}
.erp-problem-hub-spokes {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 280px;
  height: 280px;
  max-width: none;
  transform: translate(-50%, -50%);
  z-index: 0;
  pointer-events: none;
  overflow: visible;
}
.erp-problem-hub-core {
  position: relative;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 96px;
  height: 96px;
  padding: 0 10px;
  border-radius: 50%;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  text-align: center;
  color: #fff;
  background: #15803D;
  box-shadow: 0 12px 24px color-mix(in srgb, #15803D 28%, transparent);
  animation: erpHubPulse 3.6s ease-in-out infinite;
}
.erp-problem-hub-node {
  position: absolute;
  z-index: 2;
  left: 50%;
  top: 50%;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  color: #15803D;
  background: #FFFFFF;
  border: 1px solid color-mix(in srgb, #16A34A 28%, #E2E8F0);
  box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
  white-space: nowrap;
  transform:
    translate(-50%, -50%)
    rotate(var(--angle, 0deg))
    translateY(calc(-1 * var(--hub-r)))
    rotate(calc(-1 * var(--angle, 0deg)));
}
.erp-site.dark .erp-problem-hub-node {
  color: var(--brand-500);
  background: var(--surface);
}
@keyframes erpHubPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.04); }
}

.erp-problem-transform {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px 0;
}
.erp-problem-transform-orb {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 999px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 28%, var(--border));
  background: linear-gradient(135deg, color-mix(in srgb, var(--brand-500) 16%, var(--surface)), var(--surface));
  color: var(--brand-600);
  box-shadow: 0 8px 20px var(--glow);
  transform: rotate(90deg);
  animation: erpProblemArrowV 2.8s ease-in-out infinite;
}
.erp-site.dark .erp-problem-transform-orb { color: var(--brand-500); }
@keyframes erpProblemArrowV {
  0%, 100% { transform: rotate(90deg) translateY(0); }
  50% { transform: rotate(90deg) translateY(-5px); }
}

.erp-problem-stories {
  position: relative;
  z-index: 1;
  list-style: none;
  margin: 0 auto;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-width: 68em;
}
.erp-problem-stories > li {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  box-shadow: none;
}
.erp-problem-story {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  align-items: stretch;
  padding: 0;
  border: 0;
  background: transparent;
  box-shadow: none;
  border-radius: 0;
}
.erp-problem-side {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 14px;
  border-radius: 12px;
  min-width: 0;
}
.erp-problem-side.is-pain {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
}
.erp-site.dark .erp-problem-side.is-pain {
  background: color-mix(in srgb, var(--surface) 92%, #fff);
  border-color: var(--border);
}
.erp-problem-side.is-fix {
  background: #F0FDF4;
  border: 1px solid color-mix(in srgb, #16A34A 22%, #E2E8F0);
}
.erp-site.dark .erp-problem-side.is-fix {
  background: color-mix(in srgb, var(--brand-500) 10%, var(--surface));
  border-color: color-mix(in srgb, var(--brand-500) 28%, var(--border));
}
.erp-problem-ico {
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
}
.erp-problem-ico svg { width: 18px; height: 18px; }
.erp-problem-side.is-pain .erp-problem-ico {
  color: #DC2626;
  background: transparent;
  animation: erpPainShake 4.2s ease-in-out infinite;
  animation-delay: calc(var(--story-i, 0) * 0.25s);
}
.erp-problem-side.is-fix .erp-problem-ico {
  color: #15803D;
  background: color-mix(in srgb, #16A34A 14%, #FFFFFF);
  animation: erpFixBob 3.4s ease-in-out infinite;
  animation-delay: calc(var(--story-i, 0) * 0.2s);
}
.erp-site.dark .erp-problem-side.is-fix .erp-problem-ico {
  color: var(--brand-500);
  background: color-mix(in srgb, var(--brand-500) 18%, var(--surface));
}
@keyframes erpPainShake {
  0%, 100% { transform: rotate(0deg); }
  20% { transform: rotate(-5deg); }
  40% { transform: rotate(4deg); }
  60% { transform: rotate(-2deg); }
}
@keyframes erpFixBob {
  0%, 100% { transform: translateY(0) scale(1); }
  50% { transform: translateY(-3px) scale(1.04); }
}
.erp-problem-text { min-width: 0; }
.erp-problem-text h3 {
  margin: 0 0 4px;
  font-size: 0.98rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--text);
  line-height: 1.3;
}
.erp-problem-text p {
  margin: 0;
  font-size: 0.86rem;
  line-height: 1.5;
  color: var(--text-muted);
}
.erp-problem-bridge {
  display: none;
  align-items: center;
  justify-content: center;
  color: var(--brand-600);
  opacity: 0.85;
}
.erp-site.dark .erp-problem-bridge { color: var(--brand-500); }
.erp-reveal.is-visible .erp-problem-side.is-pain {
  animation: erpStoryInLeft 0.65s ease both;
  animation-delay: calc(0.05s + var(--story-i, 0) * 0.04s);
}
.erp-reveal.is-visible .erp-problem-side.is-fix {
  animation: erpStoryInRight 0.65s ease both;
  animation-delay: calc(0.18s + var(--story-i, 0) * 0.04s);
}
@keyframes erpStoryInLeft {
  from { opacity: 0; transform: translateX(-18px); }
  to { opacity: 1; transform: none; }
}
@keyframes erpStoryInRight {
  from { opacity: 0; transform: translateX(18px); }
  to { opacity: 1; transform: none; }
}

@media (max-width: 639px) {
  .erp-section-title { max-width: none; }
  .erp-problem-hub { --hub-r: 92px; min-height: 220px; }
  .erp-problem-hub-spokes { width: 220px; height: 220px; }
  .erp-problem-hub-core { width: 72px; height: 72px; font-size: 0.66rem; }
  .erp-problem-hub-node { font-size: 0.64rem; padding: 5px 8px; }
  .erp-problem-scatter { min-height: 200px; }
  .erp-problem-scatter-chip { font-size: 0.68rem; padding: 6px 9px; }
  /* Vertical stagger on narrow widths — no side-by-side collisions */
  .erp-problem-scatter-chip:nth-child(1) { left: 4%; top: 0%; }
  .erp-problem-scatter-chip:nth-child(2) { left: 44%; top: 4%; }
  .erp-problem-scatter-chip:nth-child(3) { left: 8%; top: 28%; }
  .erp-problem-scatter-chip:nth-child(4) { left: 22%; top: 52%; max-width: 70%; white-space: normal; text-align: center; line-height: 1.25; }
  .erp-problem-scatter-chip:nth-child(5) { left: 30%; top: 78%; }
  .erp-problem-transform {
    padding: 8px 0;
    position: relative;
    z-index: 2;
  }
  .erp-problem-transform-orb {
    width: 48px;
    height: 48px;
  }
}

/* ---------- Roles / who it's for ---------- */
.erp-roles {
  position: relative;
  overflow: hidden;
}
.erp-roles::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 12% 20%, color-mix(in srgb, var(--brand-500) 9%, transparent), transparent 42%),
    radial-gradient(circle at 88% 75%, color-mix(in srgb, var(--violet-500) 8%, transparent), transparent 40%);
  pointer-events: none;
  animation: erpRolesGlow 10s ease-in-out infinite;
}
@keyframes erpRolesGlow {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}
.erp-roles .erp-container { position: relative; z-index: 1; }
.erp-roles-head { margin-bottom: 36px; }
.erp-roles-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
  align-items: stretch;
}
.erp-role-card {
  position: relative;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 100%;
  padding: 22px 18px 18px;
  border-radius: 18px;
  border: 1px solid color-mix(in srgb, var(--brand-500) 14%, var(--border-soft));
  background:
    linear-gradient(160deg, color-mix(in srgb, var(--brand-500) 6%, transparent), transparent 55%),
    color-mix(in srgb, var(--surface) 94%, transparent);
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.045);
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
  overflow: hidden;
  transform: none !important;
  align-self: stretch;
}
.erp-role-card:hover {
  border-color: color-mix(in srgb, #16A34A 40%, var(--border));
  box-shadow: 0 16px 34px var(--glow);
}
.erp-role-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  margin-bottom: 14px;
  color: #fff;
  background: #15803D;
  box-shadow: 0 12px 22px color-mix(in srgb, #15803D 26%, transparent);
}
.erp-role-icon svg { width: 22px; height: 22px; }
.erp-role-tag {
  display: inline-flex;
  margin: 0 0 8px;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 750;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--brand-700);
  background: color-mix(in srgb, var(--brand-500) 12%, transparent);
}
.erp-site.dark .erp-role-tag { color: var(--brand-500); }
.erp-site.lang-hi .erp-role-tag { text-transform: none; letter-spacing: 0.02em; }
.erp-role-card h3 {
  margin: 0 0 8px;
  font-size: 1.08rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  color: var(--text);
}
.erp-role-card > p {
  margin: 0 0 14px;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--text-muted);
}
.erp-role-points {
  list-style: none;
  margin: auto 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.erp-role-points li {
  position: relative;
  padding-left: 18px;
  font-size: 0.84rem;
  font-weight: 600;
  line-height: 1.4;
  color: var(--text);
}
.erp-role-points li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.45em;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-500), var(--violet-500));
  box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-500) 35%, transparent);
  animation: erpRoleDot 2.8s ease-out infinite;
  animation-delay: calc(var(--role-i, 0) * 0.15s);
}
@keyframes erpRoleDot {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-500) 40%, transparent); }
  70% { box-shadow: 0 0 0 6px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
}
.erp-roles-cta {
  margin-top: 28px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 18px 20px;
  border-radius: 16px;
  border: 1px solid color-mix(in srgb, #16A34A 18%, #E2E8F0);
  background: #F0FDF4;
}
.erp-site.dark .erp-roles-cta {
  background: color-mix(in srgb, var(--brand-500) 10%, var(--surface));
  border-color: color-mix(in srgb, var(--brand-500) 22%, var(--border));
}
.erp-roles-cta p {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 650;
  color: var(--text);
  max-width: 28em;
}
@media (prefers-reduced-motion: reduce) {
  .erp-problem-scatter-chip,
  .erp-problem-hub-core,
  .erp-problem-transform-orb,
  .erp-problem-side.is-pain .erp-problem-ico,
  .erp-problem-side.is-fix .erp-problem-ico,
  .erp-reveal.is-visible .erp-problem-side.is-pain,
  .erp-reveal.is-visible .erp-problem-side.is-fix,
  .erp-roles::before,
  .erp-role-points li::before { animation: none !important; }
}

/* ---------- Why icon + hover ---------- */
.erp-why-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  color: #15803D;
  background: #DCFCE7;
}
.erp-site.dark .erp-why-icon {
  background: color-mix(in srgb, var(--brand-500) 16%, transparent);
  color: var(--brand-500);
}
.erp-why-icon svg { width: 20px; height: 20px; }

/* ---------- Module sticky scroll tour ---------- */
.erp-showcase {
  background: var(--bg-tint);
  position: relative;
  overflow: visible;
}
.erp-showcase .erp-container { position: relative; z-index: 1; }
.erp-showcase-head { margin-bottom: 20px; }
.erp-showcase-frame-bar p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.erp-tour-nav {
  position: sticky;
  top: 76px;
  z-index: 20;
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  overflow-x: auto;
  padding: 10px 4px 14px;
  margin: 0 0 12px;
  background: color-mix(in srgb, var(--bg-tint) 92%, transparent);
  backdrop-filter: blur(8px);
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.erp-tour-nav::-webkit-scrollbar { display: none; }
.erp-tour-nav-btn {
  flex: 0 0 auto;
  padding: 7px 12px;
  border-radius: 999px;
  border: 1px solid #E2E8F0;
  background: #FFFFFF;
  color: #475569;
  font-family: inherit;
  font-size: 0.78rem;
  font-weight: 650;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
.erp-site.dark .erp-tour-nav-btn {
  background: var(--surface);
  border-color: var(--border);
  color: var(--text-muted);
}
.erp-tour-nav-btn:hover {
  border-color: #16A34A;
  color: #15803D;
}
.erp-tour-nav-btn.is-active {
  background: #15803D;
  border-color: #15803D;
  color: #FFFFFF;
}

.erp-tour-rail {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding-left: 8px;
}
.erp-tour-track { display: none; }
.erp-tour-step {
  position: relative;
  scroll-margin-top: 140px;
}
.erp-tour-node { display: none; }

.erp-tour-pair {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  align-items: stretch;
}

.erp-tour-card {
  position: relative;
  padding: 16px 16px 14px;
  border-radius: 16px;
  border: 1px solid var(--border-soft);
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
  overflow: hidden;
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.erp-tour-card::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 3px;
  background: transparent;
  transition: background 0.25s ease;
}
.erp-tour-step.is-active .erp-tour-card {
  border-color: color-mix(in srgb, #16A34A 42%, var(--border));
  box-shadow: 0 14px 28px var(--glow);
}
.erp-tour-step.is-active .erp-tour-card::before {
  background: #15803D;
}
.erp-tour-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}
.erp-tour-step-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  color: var(--text-dim);
  background: var(--surface-2);
  transition: color 0.25s ease, background 0.25s ease, box-shadow 0.25s ease;
}
.erp-tour-step-icon svg { width: 20px; height: 20px; }
.erp-tour-step.is-active .erp-tour-step-icon {
  color: #fff;
  background: #15803D;
  box-shadow: 0 10px 18px color-mix(in srgb, #15803D 28%, transparent);
}
.erp-tour-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #fff;
  background: #15803D;
}
.erp-site.lang-hi .erp-tour-pill { text-transform: none; letter-spacing: 0.02em; }
.erp-tour-step-kicker {
  margin: 0 0 4px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--text-dim);
}
.erp-site.lang-hi .erp-tour-step-kicker { text-transform: none; letter-spacing: 0.02em; }
.erp-tour-card h3 {
  margin: 0 0 6px;
  font-size: 1.12rem;
  font-weight: 780;
  letter-spacing: -0.02em;
  color: var(--text);
}
.erp-tour-step-desc {
  margin: 0 0 10px;
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--text-muted);
}
.erp-tour-bullets {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.erp-tour-bullets li {
  position: relative;
  padding-left: 16px;
  font-size: 0.84rem;
  font-weight: 600;
  line-height: 1.4;
  color: var(--text);
}
.erp-tour-bullets li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.45em;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #16A34A;
}
.erp-tour-step-cta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
  padding: 0;
  border: 0;
  background: transparent;
  font-family: inherit;
  font-size: 0.8rem;
  font-weight: 700;
  color: #15803D;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.25s ease;
}
.erp-site.dark .erp-tour-step-cta { color: var(--brand-500); }
.erp-tour-step.is-active .erp-tour-step-cta,
.erp-tour-step-cta:hover { opacity: 1; }

.erp-tour-shot { margin: 0; }
.erp-tour-frame {
  border-radius: 16px;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow-md);
  overflow: hidden;
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.erp-tour-step.is-active .erp-tour-frame {
  border-color: color-mix(in srgb, #16A34A 36%, var(--border));
  box-shadow: 0 14px 32px var(--glow);
}
.erp-tour-frame-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding-right: 12px;
  border-bottom: 1px solid var(--border-soft);
}
.erp-tour-frame-head .erp-mock-bar { flex: 1; border-bottom: 0; }
.erp-tour-counter {
  display: inline-flex;
  align-items: baseline;
  gap: 3px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.78rem;
  color: var(--text-dim);
  white-space: nowrap;
}
.erp-tour-counter strong {
  font-size: 1rem;
  font-weight: 800;
  color: #15803D;
}
.erp-site.dark .erp-tour-counter strong { color: var(--brand-500); }
.erp-tour-viewport {
  position: relative;
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  min-height: 200px;
  padding: 0;
  border: 0;
  background: #F8FAFC;
  overflow: hidden;
  cursor: zoom-in;
}
.erp-tour-img {
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: left top;
  opacity: 0.55;
  filter: grayscale(0.2) saturate(0.85);
  transition: opacity 0.3s ease, filter 0.3s ease;
}
.erp-tour-step.is-active .erp-tour-img {
  opacity: 1 !important;
  filter: none !important;
}

.erp-tour-lightbox {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(4px);
}
.erp-tour-lightbox-img {
  max-width: min(1200px, 96vw);
  max-height: 88vh;
  width: auto;
  height: auto;
  border-radius: 12px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
  background: #fff;
}
.erp-tour-lightbox-close {
  position: absolute;
  top: 18px;
  right: 18px;
  width: 42px;
  height: 42px;
  border: 0;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #FFFFFF;
  color: #0F172A;
  cursor: pointer;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}
@media (max-width: 899px) {
  .erp-tour-nav { top: 64px; }
  .erp-tour-shot { position: static; }
  .erp-tour-step { min-height: 0; padding: 0; }
  .erp-tour-rail { gap: 22px; padding-left: 0; }
}
@media (prefers-reduced-motion: reduce) {
  .erp-tour-img { transition: none; }
}

/* ---------- UDISE+ ---------- */
.erp-udise { padding-bottom: calc(var(--space-section) - 24px); }
.erp-udise-grid { display: grid; grid-template-columns: 1fr; gap: 28px; align-items: center; }
.erp-udise-copy .erp-section-title { text-align: left; margin-bottom: 10px; }
.erp-udise-copy .erp-section-lead { text-align: left; max-width: 34em; margin-top: 12px; }
.erp-udise-badge {
  display: inline-flex;
  align-items: center;
  margin: 0 0 4px;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #15803D;
  background: #DCFCE7;
  border: 1px solid #BBF7D0;
}
.erp-site.dark .erp-udise-badge {
  color: var(--brand-500);
  background: color-mix(in srgb, var(--brand-500) 16%, transparent);
  border-color: color-mix(in srgb, var(--brand-500) 28%, transparent);
}
.erp-udise-caps {
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
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
  color: #475569;
  max-width: 32em;
}
.erp-site.dark .erp-udise-cap p { color: var(--text-muted); }
.erp-udise-visual { margin: 0; }
.erp-udise-shot {
  position: relative;
  margin: 0;
  border-radius: 14px;
  border: 1px solid color-mix(in srgb, #16A34A 22%, #E2E8F0);
  background: #F8FAFC;
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
  aspect-ratio: 16 / 9;
  object-fit: cover;
  object-position: left top;
  background: #F8FAFC;
  filter: none;
}
.erp-site.dark .erp-tour-step.is-active .erp-tour-img {
  filter: none;
  opacity: 1;
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
  color: #475569;
  margin: 0;
  max-width: 24em;
}
.erp-site.dark .erp-why-card p { color: var(--text-muted); }

/* ---------- Steps ---------- */
.erp-steps-row { position: relative; display: grid; grid-template-columns: 1fr; gap: 28px; }
.erp-steps-line {
  display: none;
  position: absolute;
  top: 28px;
  left: 12.5%;
  right: 12.5%;
  height: 2px;
  background: #BBF7D0;
  z-index: 0;
}
.erp-step { position: relative; z-index: 1; text-align: center; }
.erp-step-icon {
  width: 52px;
  height: 52px;
  margin: 0 auto 10px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  color: #15803D;
  background: #DCFCE7;
  border: 1px solid #BBF7D0;
}
.erp-step-icon svg { width: 22px; height: 22px; }
.erp-step-num {
  display: block;
  font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif;
  font-weight: 800;
  font-size: 0.72rem;
  letter-spacing: 0.1em;
  color: #15803D;
  margin-bottom: 8px;
}
.erp-site.dark .erp-step-num { color: var(--brand-500); }
.erp-step h3 { font-size: 1.05rem; font-weight: 750; margin: 0 0 8px; letter-spacing: -0.01em; }
.erp-step p { font-size: 0.88rem; line-height: 1.55; color: #475569; margin: 0 auto; max-width: 20em; }
.erp-site.dark .erp-step p { color: var(--text-muted); }
.erp-steps-cta {
  display: flex;
  justify-content: center;
  margin-top: 36px;
}

/* ---------- Testimonials ---------- */
.erp-testimonials { background: var(--bg); }
.erp-testimonials-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
.erp-testimonial {
  margin: 0;
  padding: 24px 22px;
  border-radius: 16px;
  border: 1px solid var(--border-soft);
  background: var(--surface);
  box-shadow: var(--shadow-sm);
}
.erp-testimonial-quote {
  margin: 0 0 18px;
  font-size: 0.98rem;
  line-height: 1.6;
  color: #475569;
}
.erp-site.dark .erp-testimonial-quote { color: var(--text-muted); }
.erp-testimonial footer {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.erp-testimonial footer strong {
  font-size: 0.92rem;
  font-weight: 750;
  color: var(--text);
}
.erp-testimonial footer span {
  font-size: 0.82rem;
  color: #64748B;
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
.erp-faq-a p { margin: 0; padding: 0 4px 18px; font-size: 0.9rem; line-height: 1.6; color: #475569; }
.erp-site.dark .erp-faq-a p { color: var(--text-muted); }
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
  background: #052E16;
  color: #fff;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}
.erp-cta-inner::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
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
  line-height: 1.2;
  text-wrap: balance;
}
.erp-cta-sub {
  font-size: 1.02rem;
  color: rgba(255, 255, 255, 0.88);
  margin: 0;
  line-height: 1.55;
}
.erp-cta-actions { display: flex; flex-wrap: wrap; gap: 12px; }
.erp-cta-inner .erp-btn-primary {
  background: #fff;
  color: #052E16;
  box-shadow: none;
}
.erp-cta-inner .erp-btn-primary:hover {
  transform: translateY(-1px);
  background: #F0FDF4;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}
.erp-cta-inner .erp-btn-outline-light {
  color: #fff;
  border-color: rgba(255, 255, 255, 0.9);
}
.erp-cta-inner .erp-btn-outline-light:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: #fff;
  color: #fff;
}

/* ---------- Footer ---------- */
.erp-footer { border-top: 1px solid var(--border-soft); padding: 56px 0 28px; background: var(--bg-tint); }
.erp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 34px; padding-bottom: 34px; }
.erp-footer-brand .erp-brand-logo { height: 34px; margin-bottom: 12px; }
.erp-footer-note { font-size: 0.88rem; color: #475569; margin: 0 0 10px; max-width: 26em; }
.erp-footer-address { font-size: 0.88rem; color: #475569; margin: 0 0 8px; font-weight: 600; }
.erp-footer-contact-lines {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
}
.erp-footer-contact-lines a {
  font-size: 0.88rem;
  color: #15803D;
  text-decoration: none;
  font-weight: 650;
}
.erp-footer-contact-lines a:hover { text-decoration: underline; }
.erp-footer-col { display: flex; flex-direction: column; gap: 10px; }
.erp-footer-col h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-dim); margin: 0 0 2px; }
.erp-site.lang-hi .erp-footer-col h4 { text-transform: none; }
.erp-footer-col a { font-size: 0.88rem; color: #475569; text-decoration: none; transition: color 0.15s ease; }
.erp-footer-col a:hover { color: var(--brand-600); }
.erp-footer-fineprint { font-size: 0.78rem; color: var(--text-dim); margin: 2px 0 0; }
.erp-footer-contact-name { margin: 0; font-size: 0.88rem; font-weight: 650; color: var(--text); }
.erp-footer-contact-person { margin: 0; font-size: 0.85rem; color: #475569; }
.erp-footer-whatsapp { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; color: #475569; text-decoration: none; }
.erp-footer-whatsapp:hover { color: var(--brand-600); }
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
  .erp-roles-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .erp-why-grid { grid-template-columns: repeat(2, 1fr); }
  .erp-steps-row { grid-template-columns: repeat(2, 1fr); }
  .erp-testimonials-grid { grid-template-columns: repeat(2, 1fr); }
  .erp-problem-scene { grid-template-columns: 1fr auto 1fr; gap: 18px; align-items: stretch; }
  .erp-problem-transform { padding: 0; }
  .erp-problem-transform-orb {
    transform: none;
    animation-name: erpProblemArrowH;
  }
  @keyframes erpProblemArrowH {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(6px); }
  }
  .erp-problem-story {
    grid-template-columns: 1fr auto 1fr;
    gap: 12px;
    align-items: stretch;
    padding: 0;
  }
  .erp-problem-bridge { display: flex; }
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
  .erp-roles-grid { grid-template-columns: repeat(4, 1fr); gap: 16px; }
  .erp-why-grid { grid-template-columns: repeat(4, 1fr); gap: 36px 28px; }
  .erp-why-card h3 { white-space: nowrap; }
  .erp-steps-row { grid-template-columns: repeat(4, 1fr); gap: 20px; }
  .erp-steps-line { display: block; }
  .erp-testimonials-grid { grid-template-columns: repeat(3, 1fr); }
  .erp-tour-nav { top: 84px; }
  .erp-tour-rail { padding-left: 34px; gap: 8px; }
  .erp-tour-track {
    display: block;
    position: absolute;
    left: 15px;
    top: 28px;
    bottom: 28px;
    width: 2px;
    border-radius: 999px;
    background: var(--border-soft);
    overflow: hidden;
  }
  .erp-tour-track-fill {
    display: block;
    width: 100%;
    height: 0;
    border-radius: inherit;
    background: #15803D;
    transition: height 0.45s ease;
  }
  .erp-tour-step {
    min-height: 0;
    display: flex;
    align-items: center;
    scroll-margin-top: 160px;
    padding: 10px 0;
  }
  .erp-tour-pair {
    width: 100%;
    grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.12fr);
    gap: 22px;
    align-items: center;
  }
  .erp-tour-shot {
    position: sticky;
    top: 160px;
  }
  .erp-tour-viewport { min-height: 280px; aspect-ratio: 16 / 9; }
  .erp-tour-node {
    display: grid;
    place-items: center;
    position: absolute;
    left: -34px;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
  }
  .erp-tour-node-core {
    position: relative;
    z-index: 1;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.62rem;
    font-weight: 800;
    color: var(--text-dim);
    background: var(--surface);
    border: 1.5px solid var(--border);
    transition: color 0.25s ease, background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
  }
  .erp-tour-node-ring {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    border: 2px solid color-mix(in srgb, var(--brand-500) 45%, transparent);
    opacity: 0;
    transform: scale(0.7);
  }
  .erp-tour-step.is-done .erp-tour-node-core {
    color: #fff;
    background: color-mix(in srgb, var(--brand-500) 80%, var(--violet-500));
    border-color: transparent;
  }
  .erp-tour-step.is-active .erp-tour-node-core {
    color: #fff;
    background: linear-gradient(145deg, var(--brand-500), var(--violet-500));
    border-color: transparent;
    transform: scale(1.08);
    box-shadow: 0 8px 16px color-mix(in srgb, var(--brand-500) 30%, transparent);
  }
  .erp-tour-step.is-active .erp-tour-node-ring {
    opacity: 1;
    animation: erpTourNodePulse 2s ease-out infinite;
  }
  @keyframes erpTourNodePulse {
    0% { transform: scale(0.85); opacity: 0.7; }
    100% { transform: scale(1.55); opacity: 0; }
  }
  .erp-udise-grid { grid-template-columns: 1fr 1fr; }
}
@media (min-width: 900px) and (prefers-reduced-motion: reduce) {
  .erp-tour-step.is-active .erp-tour-node-ring { animation: none; opacity: 0; }
}

@media (min-width: 1100px) {
  .erp-roles-grid { gap: 18px; }
}
</style>
