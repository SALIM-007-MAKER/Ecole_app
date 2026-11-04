const { test, expect } = require('@playwright/test');

const EMAIL = process.env.E2E_EMAIL;
const PASSWORD = process.env.E2E_PASSWORD;

test.skip(!EMAIL || !PASSWORD, 'E2E_EMAIL et E2E_PASSWORD requis (compte de test local)');

const nav = (page) => page.locator('#sidebar .sidebar-nav');
const navScroll = (page) => nav(page).evaluate((e) => e.scrollTop);
const navMax = (page) => nav(page).evaluate((e) => e.scrollHeight - e.clientHeight);

test.beforeEach(async ({ page }) => {
  await page.goto('login');
  await page.fill('input[type=email]', EMAIL);
  await page.fill('input[type=password]', PASSWORD);
  await page.click('button[type=submit]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  await page.evaluate(() => { localStorage.removeItem('ecole_sidebar_collapsed'); sessionStorage.clear(); });
  await page.goto('dashboard');
});

// Ouvre le premier groupe pour que la liste dépasse la hauteur visible.
async function ouvrirGroupes(page) {
  const groupes = page.locator('#sidebar [data-group]');
  const n = await groupes.count();
  for (let i = 0; i < n; i++) {
    await groupes.nth(i).click();
    if ((await navMax(page)) > 100) return;
  }
}

test('la sidebar est fixe et ne défile pas avec la page', async ({ page }) => {
  const avant = await page.locator('#sidebar').boundingBox();
  await page.mouse.move(700, 300);
  await page.mouse.wheel(0, 800);
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0);
  const apres = await page.locator('#sidebar').boundingBox();
  expect(apres.y).toBe(avant.y);
});

test('défilement dans la sidebar : descend et remonte, y compris sur un sous-menu', async ({ page }) => {
  await ouvrirGroupes(page);
  const max = await navMax(page);
  expect(max).toBeGreaterThan(100);

  const box = await nav(page).boundingBox();
  await page.mouse.move(box.x + 100, box.y + 150); // survol d'un sous-menu ouvert
  await page.mouse.wheel(0, 2000);
  await expect.poll(() => navScroll(page)).toBeGreaterThan(max - 2);

  await page.mouse.wheel(0, -2000);
  await expect.poll(() => navScroll(page)).toBeLessThan(2);
});

test('le défilement de la sidebar ne se propage pas à la page principale', async ({ page }) => {
  await ouvrirGroupes(page);
  const box = await nav(page).boundingBox();
  await page.mouse.move(box.x + 100, box.y + 150);
  for (let i = 0; i < 5; i++) { await page.mouse.wheel(0, 1500); }
  await expect.poll(() => navScroll(page)).toBeGreaterThan(0);
  expect(await page.evaluate(() => window.scrollY)).toBe(0);
});

test('la position de défilement de la sidebar est conservée après un clic sur un sous-menu', async ({ page }) => {
  await ouvrirGroupes(page);
  const box = await nav(page).boundingBox();
  await page.mouse.move(box.x + 100, box.y + 150);
  await page.mouse.wheel(0, 2000);
  await expect.poll(() => navScroll(page)).toBeGreaterThan(50);

  const lien = nav(page).locator('.nav-group-content a:visible').last();
  await lien.click();
  await page.waitForLoadState('domcontentloaded');
  expect(await navScroll(page)).toBeGreaterThan(50);
});

test("l'état réduit est appliqué avant le premier rendu et conservé entre deux pages", async ({ page }) => {
  await page.click('#toggleSidebar');
  await expect(page.locator('#sidebar')).toHaveClass(/collapsed/);

  await page.addInitScript(() => {
    window.__largeurs = [];
    new MutationObserver(() => {
      const s = document.getElementById('sidebar');
      if (s) window.__largeurs.push(Math.round(s.getBoundingClientRect().width));
    }).observe(document, { childList: true, subtree: true });
  });
  await page.goto('dashboard');

  const largeurs = await page.evaluate(() => window.__largeurs);
  expect(largeurs.length).toBeGreaterThan(0);
  expect(Math.max(...largeurs)).toBeLessThanOrEqual(80); // jamais étendue (260px) pendant le chargement
  await expect(page.locator('html')).not.toHaveClass(/sb-preload|sb-collapsed-init/);
});
