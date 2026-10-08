const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 375, height: 812 }, deviceScaleFactor: 2 });
  await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
  await page.evaluate(() => {
    document.querySelectorAll(".erp-reveal").forEach((el) => el.classList.add("is-visible"));
    // hide sticky nav for clean section shot
    const nav = document.querySelector(".erp-header, .erp-nav-wrap, header.erp-top, .erp-site > header, .erp-sticky, nav");
    document.querySelectorAll("header, .erp-nav, .erp-topbar").forEach((el) => { el.style.visibility = "hidden"; });
  });
  await page.locator(".erp-problem-section").scrollIntoViewIfNeeded();
  await page.waitForTimeout(400);
  const info = await page.evaluate(() => {
    const chips = [...document.querySelectorAll(".erp-problem-scatter-chip")].map((el) => {
      const r = el.getBoundingClientRect();
      return { text: el.textContent.trim(), t: Math.round(r.top), l: Math.round(r.left), b: Math.round(r.bottom), r: Math.round(r.right), w: Math.round(r.width), h: Math.round(r.height) };
    });
    const overlaps = [];
    for (let i = 0; i < chips.length; i++) {
      for (let j = i + 1; j < chips.length; j++) {
        const a = chips[i], b = chips[j];
        const hit = !(a.r < b.l || b.r < a.l || a.b < b.t || b.b < a.t);
        if (hit) overlaps.push([a.text, b.text, a, b]);
      }
    }
    const nodes = [...document.querySelectorAll(".erp-problem-hub-node")].map((el) => {
      const r = el.getBoundingClientRect();
      return { text: el.textContent.trim(), t: Math.round(r.top), l: Math.round(r.left), b: Math.round(r.bottom), r: Math.round(r.right) };
    });
    const core = document.querySelector(".erp-problem-hub-core").getBoundingClientRect();
    const coreBox = { t: Math.round(core.top), l: Math.round(core.left), b: Math.round(core.bottom), r: Math.round(core.right) };
    const nodeTouch = nodes.filter((n) => !(n.r < coreBox.l || coreBox.r < n.l || n.b < coreBox.t || coreBox.b < n.t));
    const orb = document.querySelector(".erp-problem-transform-orb");
    const orbR = orb.getBoundingClientRect();
    const title = document.querySelector(".erp-problem-section .erp-section-title");
    return { chips, overlaps, coreBox, nodeTouch, orb: { t: Math.round(orbR.top), h: Math.round(orbR.height), visible: getComputedStyle(orb).visibility, transform: getComputedStyle(orb).transform }, titleText: title.textContent, titleWidth: title.getBoundingClientRect().width };
  });
  console.log(JSON.stringify(info, null, 2));
  await page.locator(".erp-problem-section").screenshot({ path: "storage/app/hero-reviews/problem-mobile.png" });
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
