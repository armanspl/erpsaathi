const { chromium } = require("playwright");
const fs = require("fs");
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 720 } });
  const files = ["academics", "fees", "admissions", "attendance"];
  fs.mkdirSync("storage/app/hero-reviews/webp-check", { recursive: true });
  for (const f of files) {
    await page.goto("http://localhost:8000/assets/img/dashboard/hero/" + f + ".webp");
    await page.waitForTimeout(300);
    await page.screenshot({ path: `storage/app/hero-reviews/webp-check/${f}.png`, fullPage: true });
  }
  await browser.close();
  console.log("ok");
})().catch((e) => { console.error(e); process.exit(1); });
