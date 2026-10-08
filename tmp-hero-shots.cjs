const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const outDir = "storage/app/hero-reviews";
  const fs = require("fs");
  fs.mkdirSync(outDir, { recursive: true });

  for (const [name, width, height] of [["desktop", 1440, 900], ["mobile", 375, 812]]) {
    const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 2 });
    await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
    await page.waitForTimeout(1800);
    await page.evaluate(() => {
      localStorage.setItem("erp-welcome-theme-v3", "light");
      document.documentElement.classList.remove("dark");
      const root = document.querySelector(".erp-site");
      if (root) root.classList.remove("dark");
    });
    await page.waitForTimeout(600);
    await page.locator(".erp-hero").screenshot({ path: `${outDir}/hero-${name}.png` });
    await page.screenshot({ path: `${outDir}/page-${name}.png`, fullPage: false });
    await page.close();
  }
  await browser.close();
  console.log("screenshots-ok");
})().catch((e) => { console.error(e); process.exit(1); });
