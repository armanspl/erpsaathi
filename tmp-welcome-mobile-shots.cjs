const { chromium } = require("playwright");
const fs = require("fs");

(async () => {
  const outDir = "storage/app/welcome-reviews";
  fs.mkdirSync(outDir, { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage({
    viewport: { width: 375, height: 812 },
    deviceScaleFactor: 2,
  });

  await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
  await page.evaluate(() => {
    localStorage.setItem("erp-welcome-theme-v3", "light");
    document.documentElement.classList.remove("dark");
    const root = document.querySelector(".erp-site");
    if (root) root.classList.remove("dark");
    document.querySelectorAll(".erp-reveal").forEach((el) => el.classList.add("is-visible"));
  });
  await page.waitForTimeout(800);

  const shots = [
    ["01-hero", ".erp-hero"],
    ["02-problem", ".erp-problem-section"],
    ["03-udise", ".erp-udise"],
    ["04-why", ".erp-why"],
    ["05-how", ".erp-steps"],
    ["06-about", ".erp-about"],
    ["07-testimonials", ".erp-testimonials"],
    ["08-pricing", ".erp-pricing"],
    ["09-faq", ".erp-faq-section"],
    ["10-cta", ".erp-cta"],
    ["11-footer", ".erp-footer"],
  ];

  for (const [name, sel] of shots) {
    const loc = page.locator(sel).first();
    await loc.scrollIntoViewIfNeeded();
    await page.waitForTimeout(250);
    await loc.screenshot({ path: `${outDir}/${name}-375.png` });
  }

  await page.screenshot({ path: `${outDir}/viewport-top-375.png`, fullPage: false });
  await browser.close();
  console.log("mobile-shots-ok");
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
