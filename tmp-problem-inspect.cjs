const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
  await page.evaluate(() => {
    document.querySelectorAll(".erp-reveal").forEach((el) => el.classList.add("is-visible"));
    const sticky = document.querySelector(".erp-nav, header, .erp-site-header");
  });
  await page.locator(".erp-problem-section").scrollIntoViewIfNeeded();
  await page.waitForTimeout(500);
  const info = await page.evaluate(() => {
    const section = document.querySelector(".erp-problem-section");
    const cs = getComputedStyle(section);
    const before = getComputedStyle(section, "::before");
    const chips = [...document.querySelectorAll(".erp-problem-scatter-chip")].map((el) => {
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return { text: el.textContent, top: s.top, left: s.left, t: Math.round(r.top), l: Math.round(r.left), w: Math.round(r.width), h: Math.round(r.height), z: s.zIndex, transform: s.transform };
    });
    // overlap check
    const overlaps = [];
    for (let i = 0; i < chips.length; i++) {
      for (let j = i + 1; j < chips.length; j++) {
        const a = chips[i], b = chips[j];
        const hit = !(a.l + a.w < b.l || b.l + b.w < a.l || a.t + a.h < b.t || b.t + b.h < a.t);
        if (hit) overlaps.push([a.text, b.text]);
      }
    }
    const stories = [...document.querySelectorAll(".erp-problem-story")].map((el) => {
      const s = getComputedStyle(el);
      return { border: s.border, bg: s.backgroundColor, padding: s.padding, shadow: s.boxShadow };
    });
    const spokes = document.querySelector(".erp-problem-hub-spokes");
    const spokeBox = spokes ? spokes.getBoundingClientRect() : null;
    const transform = document.querySelector(".erp-problem-transform");
    const orb = document.querySelector(".erp-problem-transform-orb");
    return {
      sectionBg: cs.backgroundImage + " | " + cs.backgroundColor,
      beforeContent: before.content,
      beforeDisplay: before.display,
      chips,
      overlaps,
      stories: stories[0],
      spokeBox,
      transformDisplay: transform ? getComputedStyle(transform).display : null,
      orbTransform: orb ? getComputedStyle(orb).transform : null,
      hubR: getComputedStyle(document.querySelector(".erp-problem-hub")).getPropertyValue("--hub-r"),
    };
  });
  console.log(JSON.stringify(info, null, 2));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
