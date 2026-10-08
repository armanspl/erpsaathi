const { chromium } = require("playwright");
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto("http://localhost:8000/", { waitUntil: "networkidle", timeout: 60000 });
  await page.waitForTimeout(1500);
  const info = await page.evaluate(() => {
    const img = document.querySelector(".erp-hero-shot-img.is-active");
    if (!img) return { err: "no active img" };
    return {
      src: img.currentSrc || img.src,
      complete: img.complete,
      naturalWidth: img.naturalWidth,
      naturalHeight: img.naturalHeight,
      className: img.className,
      display: getComputedStyle(img).display,
      opacity: getComputedStyle(img).opacity,
      filter: getComputedStyle(img).filter,
    };
  });
  console.log(JSON.stringify(info, null, 2));
  const resp = await page.goto("http://localhost:8000/assets/img/dashboard/hero-dashboard-green.svg?v=3");
  console.log("direct svg", resp.status(), resp.headers()["content-type"]);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
