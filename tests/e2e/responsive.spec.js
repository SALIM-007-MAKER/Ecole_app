// Mobile : tiroir de navigation et absence de débordement horizontal.
const { test, expect } = require('@playwright/test');

test.use({ viewport: { width: 390, height: 844 } });

test('mobile : la sidebar est un tiroir (fermé par défaut, ouvrable, refermable)', async ({ page }) => {
  await page.goto('dashboard');
  const sidebar = page.locator('#sidebar');
  await expect(sidebar).not.toHaveClass(/\bopen\b/);
  await page.click('#openSidebar');
  await expect(sidebar).toHaveClass(/\bopen\b/);
  await page.keyboard.press('Escape');
  await expect(sidebar).not.toHaveClass(/\bopen\b/);
});

test('mobile : pas de défilement horizontal sur le tableau de bord', async ({ page }) => {
  await page.goto('dashboard');
  const { sw, cw } = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
  expect(sw).toBeLessThanOrEqual(cw + 1);
});

test('mobile : le contenu n\'est pas décalé par la sidebar', async ({ page }) => {
  await page.goto('dashboard');
  const pad = await page.evaluate(() => getComputedStyle(document.querySelector('.main-wrapper')).paddingLeft);
  expect(pad).toBe('0px');
});
