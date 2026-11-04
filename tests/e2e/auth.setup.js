const { test, expect } = require('@playwright/test');
const fs = require('fs');

test('connexion (compte de test local)', async ({ page }) => {
  await page.goto('login');
  await page.fill('input[type=email]', process.env.E2E_EMAIL);
  await page.fill('input[type=password]', process.env.E2E_PASSWORD);
  await page.click('button[type=submit]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 15000 });
  await expect(page.locator('#sidebar')).toBeVisible();
  fs.mkdirSync('.auth', { recursive: true });
  await page.context().storageState({ path: '.auth/user.json' });
});
