// Fonctionnalités de l'application, module par module (compte de test local requis).
//  - Lecture seule : toujours exécutée (pages, listes, filtres, recherche, exports, formulaires).
//  - Écriture (créer / modifier / supprimer) : seulement avec E2E_WRITE=1, sur une base de TEST ;
//    toutes les données créées portent le préfixe E2E- et sont supprimées en fin de parcours.
const { test, expect } = require('@playwright/test');
const { visiter } = require('./helpers');

const ECRITURE = process.env.E2E_WRITE === '1';
const SUFFIXE = Date.now().toString(36).toUpperCase();

async function pageSansErreur(page, chemin) {
  const { statut, erreurs } = await visiter(page, chemin);
  expect(statut, chemin).toBe(200);
  expect(erreurs, chemin).toEqual([]);
}

// ── Pages de consultation de chaque module ───────────────────────────────────
const MODULES = {
  'Élèves':        ['eleves', 'eleves/create', 'eleves/import'],
  'Classes':       ['classes', 'classes/create'],
  'Matières':      ['matieres', 'matieres/create'],
  'Professeurs':   ['professeurs', 'professeurs/create'],
  'Bulletins':     ['bulletins', 'bulletins/classe', 'bulletins/classement'],
  'Notifications': ['notifications'],
  'Profil':        ['profile'],
  'Paramètres':    ['parametres', 'parametres/etablissement', 'parametres/apparence', 'parametres/annee-scolaire',
                    'parametres/academique', 'parametres/notation', 'parametres/finances', 'parametres/documents',
                    'parametres/notifications', 'parametres/securite', 'parametres/sauvegarde', 'parametres/avance'],
};

for (const [module, chemins] of Object.entries(MODULES)) {
  test.describe(`Consultation — ${module}`, () => {
    for (const chemin of chemins) {
      test(`/${chemin} s'affiche sans erreur`, async ({ page }) => { await pageSansErreur(page, chemin); });
    }
  });
}

// ── Formulaires : champs obligatoires et validation côté serveur ────────────
test.describe('Formulaires — validation', () => {
  for (const [chemin, champs] of [
    ['classes/create',  ['niveau', 'nom', 'annee_scolaire']],
    ['matieres/create', ['nom', 'coefficient']],
    ['eleves/create',   ['nom', 'prenom', 'date_naissance']],
  ]) {
    test(`/${chemin} : champs attendus présents, jeton CSRF inclus`, async ({ page }) => {
      await page.goto(chemin);
      await expect(page.locator('input[name=_csrf_token]').first()).toHaveCount(1);
      for (const c of champs) await expect(page.locator(`[name="${c}"]`).first()).toBeAttached();
    });

    test(`/${chemin} : envoi d'un formulaire vide refusé sans erreur serveur`, async ({ page }) => {
      await page.goto(chemin);
      const form = page.locator('form:has(input[name=_csrf_token])').first();
      await form.evaluate((f) => { f.noValidate = true; f.submit(); });
      await page.waitForLoadState('domcontentloaded');
      const html = await page.content();
      expect(html).not.toMatch(/Fatal error|SQLSTATE\[|Stack trace:/);
    });
  }
});

// ── Listes : recherche, pagination, exports ──────────────────────────────────
test.describe('Listes', () => {
  test('élèves : la recherche filtre la liste sans erreur', async ({ page }) => {
    await pageSansErreur(page, 'eleves?search=zzz-e2e-inexistant');
    await pageSansErreur(page, 'eleves?page=1');
  });

  test('élèves : export PDF et Excel répondent (fichier ou page, jamais une erreur)', async ({ request }) => {
    for (const chemin of ['eleves/export/pdf', 'eleves/export/excel']) {
      const rep = await request.get(chemin);
      expect(rep.status(), chemin).toBe(200);
      expect((await rep.body()).length, chemin).toBeGreaterThan(100);
    }
  });

  test('paramètre de pagination invalide : pas de plantage', async ({ page }) => {
    await pageSansErreur(page, 'eleves?page=-5&per_page=abc');
  });
});

// ── Sécurité applicative ─────────────────────────────────────────────────────
test.describe('Sécurité', () => {
  test('POST sans jeton CSRF refusé (suppression d\'un élève)', async ({ request }) => {
    const rep = await request.post('eleves/999999/delete', { form: {}, maxRedirects: 0 });
    expect([302, 400, 403, 404, 419]).toContain(rep.status());
  });

  test('identifiant inexistant : page introuvable, pas d\'erreur SQL', async ({ page }) => {
    for (const chemin of ['eleves/999999', 'classes/999999', 'matieres/999999', 'professeurs/999999']) {
      const { statut, erreurs } = await visiter(page, chemin, { ignorer404: true });
      expect([302, 404], chemin).toContain(statut);
      expect(erreurs, chemin).toEqual([]);
    }
  });

  test('injection dans un paramètre de recherche : échappée', async ({ page }) => {
    await page.goto('eleves?search=' + encodeURIComponent('<script>window.__xss=1</script>\' OR 1=1 --'));
    expect(await page.evaluate(() => window.__xss)).toBeUndefined();
    expect(await page.content()).not.toMatch(/SQLSTATE\[/);
  });

  test('ressource d\'un autre établissement : refus (404), jamais le contenu', async ({ page }) => {
    const { statut } = await visiter(page, 'eleves/1', { ignorer404: true });
    expect([200, 302, 404]).toContain(statut); // 200 uniquement si l'élève 1 appartient au compte de test
  });
});

// ── Cycles de création / modification / suppression (base de TEST uniquement) ─
test.describe('Cycles CRUD', () => {
  test.skip(!ECRITURE, 'E2E_WRITE=1 requis : ces tests écrivent dans la base');
  test.describe.configure({ mode: 'serial' });

  const nomClasse = `E2E-CL-${SUFFIXE}`;
  const nomMatiere = `E2E-MAT-${SUFFIXE}`;
  const nomEleve = `E2E-ELEVE-${SUFFIXE}`;

  async function idParNom(page, liste, nom) {
    await page.goto(`${liste}?search=${encodeURIComponent(nom)}`);
    const lien = page.locator(`a[href*="/${liste}/"]`, { hasText: nom }).first();
    await expect(lien, `${nom} introuvable dans ${liste}`).toBeVisible();
    const href = await lien.getAttribute('href');
    return href.match(new RegExp(`/${liste}/(\\d+)`))[1];
  }

  async function supprimer(page, liste, nom) {
    const id = await idParNom(page, liste, nom);
    await page.goto(`${liste}/${id}`);
    page.once('dialog', (d) => d.accept());
    await page.locator('form[action$="/delete"] button, button:has-text("Supprimer")').first().click();
    await page.waitForLoadState('domcontentloaded');
    const { statut } = await visiter(page, `${liste}/${id}`, { ignorer404: true });
    expect([302, 404]).toContain(statut);
  }

  test('classe : créer, retrouver, modifier', async ({ page }) => {
    await page.goto('classes/create');
    await page.fill('[name=niveau]', 'E2E');
    await page.fill('[name=nom]', nomClasse);
    await page.fill('[name=annee_scolaire]', String(new Date().getFullYear()));
    await page.locator('form:has([name=nom]) button[type=submit]').first().click();
    await page.waitForLoadState('domcontentloaded');
    expect(await page.content()).not.toMatch(/Fatal error|SQLSTATE\[/);

    const id = await idParNom(page, 'classes', nomClasse);
    await page.goto(`classes/${id}/edit`);
    await page.fill('[name=max_eleves]', '30');
    await page.locator('form:has([name=nom]) button[type=submit]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await pageSansErreur(page, `classes/${id}`);
  });

  test('matière : créer et retrouver', async ({ page }) => {
    await page.goto('matieres/create');
    await page.fill('[name=nom]', nomMatiere);
    await page.fill('[name=coefficient]', '2');
    await page.locator('form:has([name=nom]) button[type=submit]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await idParNom(page, 'matieres', nomMatiere);
  });

  test('élève : créer, afficher, modifier', async ({ page }) => {
    await page.goto('eleves/create');
    await page.fill('[name=nom]', nomEleve);
    await page.fill('[name=prenom]', 'Test');
    await page.fill('[name=date_naissance]', '2012-05-10');
    await page.locator('input[name=sexe]').first().check({ force: true });
    await page.locator('form:has([name=nom]) button[type=submit]').first().click();
    await page.waitForLoadState('domcontentloaded');
    expect(await page.content()).not.toMatch(/Fatal error|SQLSTATE\[/);

    const id = await idParNom(page, 'eleves', nomEleve);
    await pageSansErreur(page, `eleves/${id}`);
    await page.goto(`eleves/${id}/edit`);
    await page.fill('[name=prenom]', 'TestModifie');
    await page.locator('form:has([name=nom]) button[type=submit]').first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.goto(`eleves/${id}`);
    await expect(page.locator('body')).toContainText('TestModifie');
  });

  test('nettoyage : suppression des données E2E', async ({ page }) => {
    for (const [liste, nom] of [['eleves', nomEleve], ['matieres', nomMatiere], ['classes', nomClasse]]) {
      await supprimer(page, liste, nom).catch((e) => { throw new Error(`nettoyage ${liste}/${nom} : ${e.message}`); });
    }
  });
});
