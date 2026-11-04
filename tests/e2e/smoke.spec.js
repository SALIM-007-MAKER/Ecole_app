// Parcours de toute la navigation visible du compte de test : chaque lien doit répondre 200, sans erreur PHP/SQL/JS.
const { test, expect } = require('@playwright/test');
const { visiter } = require('./helpers');

const EXCLUS = /logout|deconnexion|\/export|\/download|\/telecharger|\/pdf|\.csv|\.xlsx/i;

async function liensDeLaNavigation(page) {
  await page.goto('dashboard');
  const hrefs = await page.$$eval('#sidebar a[href]', (as) => as.map((a) => a.href.split('#')[0]));
  const origine = new URL(page.url()).origin;
  return [...new Set(hrefs.filter((h) => h.startsWith(origine) && !EXCLUS.test(h)))].sort();
}

test('tableau de bord : sidebar et contenu présents, sans erreur', async ({ page }) => {
  const { statut, erreurs } = await visiter(page, 'dashboard');
  expect(statut).toBe(200);
  expect(erreurs).toEqual([]);
  await expect(page.locator('#sidebar')).toBeVisible();
  await expect(page.locator('main')).toBeVisible();
});

test('toutes les pages de la navigation répondent sans erreur', async ({ page }) => {
  test.setTimeout(900000);
  const liens = await liensDeLaNavigation(page);
  expect(liens.length, 'aucun lien trouvé dans la sidebar').toBeGreaterThan(3);

  const defauts = [];
  for (const lien of liens) {
    const { statut, erreurs } = await visiter(page, lien);
    const chemin = new URL(lien).pathname;
    if (statut !== 200) defauts.push(`${chemin} -> HTTP ${statut}`);
    for (const e of erreurs) defauts.push(`${chemin} -> ${e}`);
  }
  console.log(`${liens.length} pages parcourues, ${defauts.length} défaut(s)`);
  expect(defauts, defauts.join('\n')).toEqual([]);
});

test('au plus deux éléments actifs (lien + groupe) par page de la navigation', async ({ page }) => {
  const liens = (await liensDeLaNavigation(page)).slice(0, 15);
  for (const lien of liens) {
    await page.goto(lien);
    const actifs = await page.locator('#sidebar .nav-active:visible').count();
    expect.soft(actifs, new URL(lien).pathname).toBeLessThanOrEqual(2);
  }
});

test('profil : accessible et formulaire présent', async ({ page }) => {
  const { statut, erreurs } = await visiter(page, 'profile');
  expect(statut).toBe(200);
  expect(erreurs).toEqual([]);
  await expect(page.locator('form').first()).toBeVisible();
});
