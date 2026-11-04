// Détecteurs d'erreurs communs à toutes les suites.
const ERREURS_PAGE = /Fatal error|Parse error|Uncaught (Error|Exception|TypeError)|Stack trace:|SQLSTATE\[|Warning: |Notice: |Deprecated: |Undefined (variable|index|array key|property)|Call to (undefined|a member)/;

// Ressources externes (polices, CDN, push) : leur indisponibilité n'est pas un défaut de l'application.
const BRUIT_CONSOLE = /fonts\.(googleapis|gstatic)|cdn\.|jsdelivr|tailwindcss|favicon|service-?worker|ERR_(BLOCKED|INTERNET|NAME)|Failed to load resource.*net::/i;

async function visiter(page, url, { ignorer404 = false } = {}) {
  const erreurs = [];
  const surPageError = (e) => erreurs.push('JS: ' + e.message);
  const surConsole = (m) => { if (m.type() === 'error' && !BRUIT_CONSOLE.test(m.text()) && !(ignorer404 && /status of 404/.test(m.text()))) erreurs.push('console: ' + m.text().slice(0, 200)); };
  page.on('pageerror', surPageError);
  page.on('console', surConsole);
  let statut = 0;
  try {
    const rep = await page.goto(url, { waitUntil: 'domcontentloaded' });
    statut = rep ? rep.status() : 0;
    const html = await page.content();
    const m = html.match(ERREURS_PAGE);
    if (m) erreurs.push('page: ' + m[0]);
  } finally {
    page.off('pageerror', surPageError);
    page.off('console', surConsole);
  }
  return { statut, erreurs };
}

module.exports = { ERREURS_PAGE, visiter };
