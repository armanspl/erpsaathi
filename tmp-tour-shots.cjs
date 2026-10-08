const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const fs = require("fs");
  fs.mkdirSync("storage/app/hero-reviews", { recursive: true });
  for (const [name, width, height] of [["desktop", 1440, 900], ["mobile", 375, 812]]) {
    const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 2 });
    await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
    await page.evaluate(() => {
      document.querySelectorAll(".erp-reveal").forEach((el) => el.classList.add("is-visible"));
      document.querySelectorAll(".erp-nav-bar").forEach((el) => { el.style.display = "none"; });
    });
    await page.locator("#roles").scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    await page.locator("#roles").screenshot({ path: `storage/app/hero-reviews/roles-${name}.png` });
    await page.locator("#modules").scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    await page.locator("#modules").screenshot({ path: `storage/app/hero-reviews/tour-${name}.png` });
    // lightbox
    if (name === "desktop") {
      await page.locator(".erp-tour-step.is-active .erp-tour-step-cta").click();
      await page.waitForTimeout(300);
      await page.screenshot({ path: "storage/app/hero-reviews/tour-lightbox.png" });
      await page.locator(".erp-tour-lightbox-close").click();
    }
    await page.close();
  }
  await browser.close();
  console.log("ok");
})().catch((e) => { console.error(e); process.exit(1); });
