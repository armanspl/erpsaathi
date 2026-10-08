const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const fs = require("fs");
  fs.mkdirSync("storage/app/hero-reviews", { recursive: true });

  async function shot(name, width, height) {
    const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 2 });
    await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
    await page.evaluate(() => {
      localStorage.setItem("erp-welcome-theme-v3", "light");
      document.documentElement.classList.remove("dark");
      document.querySelector(".erp-site")?.classList.remove("dark");
      document.querySelectorAll(".erp-reveal").forEach((el) => el.classList.add("is-visible"));
      document.querySelectorAll(".erp-nav-bar").forEach((el) => { el.style.display = "none"; });
    });
    await page.locator(".erp-problem-section").scrollIntoViewIfNeeded();
    await page.waitForTimeout(500);
    const info = await page.evaluate(() => {
      const chips = [...document.querySelectorAll(".erp-problem-scatter-chip")].map((el) => {
        const r = el.getBoundingClientRect();
        return { text: el.textContent.trim(), t: Math.round(r.top), l: Math.round(r.left), b: Math.round(r.bottom), r: Math.round(r.right) };
      });
      const overlaps = [];
      for (let i = 0; i < chips.length; i++) {
        for (let j = i + 1; j < chips.length; j++) {
          const a = chips[i], b = chips[j];
          const hit = !(a.r <= b.l || b.r <= a.l || a.b <= b.t || b.b <= a.t);
          if (hit) overlaps.push(a.text + " x " + b.text);
        }
      }
      const core = document.querySelector(".erp-problem-hub-core").getBoundingClientRect();
      const nodes = [...document.querySelectorAll(".erp-problem-hub-node")].map((el) => {
        const r = el.getBoundingClientRect();
        const touch = !(r.right <= core.left || core.right <= r.left || r.bottom <= core.top || core.bottom <= r.top);
        const gap = Math.min(
          Math.hypot(r.left + r.width/2 - (core.left+core.width/2), r.top + r.height/2 - (core.top+core.height/2)) - core.width/2 - Math.max(r.width, r.height)/2
        );
        return { text: el.textContent.trim(), touch, cx: Math.round(r.left+r.width/2), cy: Math.round(r.top+r.height/2) };
      });
      const title = document.querySelector(".erp-problem-section .erp-section-title");
      return {
        overlaps,
        nodeTouch: nodes.filter(n => n.touch).map(n => n.text),
        titleHTML: title.innerHTML,
        titleH: Math.round(title.getBoundingClientRect().height),
        orbOn: !!document.querySelector(".erp-problem-transform-orb"),
      };
    });
    console.log(name, JSON.stringify(info));
    await page.locator(".erp-problem-section").screenshot({ path: `storage/app/hero-reviews/problem-${name}.png` });
    await page.close();
  }

  await shot("desktop", 1440, 900);
  await shot("mobile", 375, 812);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
