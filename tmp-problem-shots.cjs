const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const fs = require("fs");
  const outDir = "storage/app/hero-reviews";
  fs.mkdirSync(outDir, { recursive: true });
  for (const [name, width, height] of [["desktop", 1440, 900], ["mobile", 375, 812]]) {
    const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 2 });
    await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
    await page.evaluate(() => {
      localStorage.setItem("erp-welcome-theme-v3", "light");
      document.documentElement.classList.remove("dark");
      const root = document.querySelector(".erp-site");
      if (root) root.classList.remove("dark");
      document.querySelectorAll(".erp-reveal").forEach((el) => el.classList.add("is-visible"));
    });
    await page.waitForTimeout(800);
    await page.locator(".erp-problem-section").scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    await page.locator(".erp-problem-section").screenshot({ path: `${outDir}/problem-${name}.png` });
    await page.close();
  }
  await browser.close();
  console.log("ok");
})().catch((e) => { console.error(e); process.exit(1); });
