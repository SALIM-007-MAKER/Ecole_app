// Usage : E2E_EMAIL=... E2E_PASSWORD=... npx playwright test
//   E2E_BASE_URL (défaut http://localhost/ecole_app) ; --project=public pour ne lancer que les tests sans connexion.
// Les tests connectés exigent un compte de TEST local : jamais de mot de passe dans le dépôt.
const { defineConfig } = require('@playwright/test');

const avecCompte = !!(process.env.E2E_EMAIL && process.env.E2E_PASSWORD);

module.exports = defineConfig({
  testDir: '.',
  timeout: 45000,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: (process.env.E2E_BASE_URL || 'http://localhost/ecole_app').replace(/\/?$/, '/'),
    viewport: { width: 1280, height: 600 },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'public', testMatch: /public\.spec\.js/ },
    ...(avecCompte ? [
      { name: 'setup', testMatch: /auth\.setup\.js/ },
      { name: 'app', testMatch: /(sidebar|smoke|responsive|fonctionnalites)\.spec\.js/, dependencies: ['setup'], use: { storageState: '.auth/user.json' } },
    ] : []),
  ],
});
