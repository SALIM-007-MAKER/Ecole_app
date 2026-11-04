// Sans compte : accès anonyme, redirections, formulaire de connexion.
const { test, expect } = require('@playwright/test');
const { visiter } = require('./helpers');

const PROTEGEES = ['dashboard', 'eleves', 'classes', 'matieres', 'professeurs', 'bulletins', 'v2/vie-scolaire/absences', 'utilisateurs', 'parametres', 'profile'];

test('la page de connexion s\'affiche sans erreur', async ({ page }) => {
  const { statut, erreurs } = await visiter(page, 'login');
  expect(statut).toBe(200);
  expect(erreurs).toEqual([]);
  await expect(page.locator('input[type=email]')).toBeVisible();
  await expect(page.locator('input[type=password]')).toBeVisible();
});

for (const chemin of PROTEGEES) {
  test(`anonyme : ${chemin} redirige vers la connexion`, async ({ page }) => {
    await page.goto(chemin);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('#sidebar')).toHaveCount(0);
  });
}

test('identifiants invalides : pas de session ouverte', async ({ page }) => {
  await page.goto('login');
  await page.fill('input[type=email]', 'inconnu-e2e@example.test');
  await page.fill('input[type=password]', 'mauvais-mot-de-passe-e2e');
  await page.click('button[type=submit]');
  await expect(page).toHaveURL(/\/login/);
  await page.goto('dashboard');
  await expect(page).toHaveURL(/\/login/);
});

test('POST de connexion sans jeton CSRF refusé', async ({ request }) => {
  const rep = await request.post('login', { form: { email: 'x@example.test', password: 'x' }, maxRedirects: 0 });
  expect([302, 400, 403, 419]).toContain(rep.status());
  if (rep.status() === 302) expect(rep.headers()['location'] || '').toMatch(/login/);
});

test('route inexistante : réponse propre, sans trace PHP', async ({ page }) => {
  const { statut, erreurs } = await visiter(page, 'cette-page-n-existe-pas-e2e', { ignorer404: true });
  expect([302, 404]).toContain(statut);
  expect(erreurs).toEqual([]);
});

test('la version de PHP n\'est pas exposée', async ({ request }) => {
  const rep = await request.get('login');
  expect(rep.headers()['x-powered-by'] || '').not.toMatch(/PHP\/\d/);
});
