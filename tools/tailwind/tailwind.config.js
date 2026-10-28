/**
 * Compilation de Tailwind CSS (remplace le CDN « Play » en production).
 *
 *   tools\tailwind\build.cmd           (Windows)     ou     sh tools/tailwind/build.sh
 *
 * Deux feuilles sont produites :
 *   - public/assets/vendor/tailwind/tailwind.css            sans « preflight » (gabarits main, auth, platform, platform-auth, home/index)
 *   - public/assets/vendor/tailwind/tailwind-preflight.css  avec « preflight » (pages autonomes : erreurs, impression, portails, rapports…)
 * Le binaire « tailwindcss » v3.4.17 (standalone) n'est pas versionné : voir tools/tailwind/README.md.
 */
const preflight = process.env.TW_PREFLIGHT === '1';

// Couleurs construites dynamiquement côté PHP (bg-<?= $couleur ?>-100, text-…-700…) : le scanneur ne peut pas les voir.
const couleurs = 'red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone';
const nuances = '50|100|200|300|400|500|600|700|800|900';

module.exports = {
  content: ['./app/**/*.php', './public/assets/js/**/*.js', './public/*.html'],
  corePlugins: { preflight },
  safelist: [
    { pattern: new RegExp('^(bg|text|border)-(' + couleurs + ')-(' + nuances + ')$'), variants: ['hover'] },
  ],
  theme: {
    extend: {
      // La police est fournie par le gabarit (--font-sans = police de l'établissement) ; Inter par défaut.
      fontFamily: { sans: ['var(--font-sans, Inter)', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      // Couleur des portails (valeur dynamique par portail, définie en variable CSS par le gabarit)
      colors: { portal: { DEFAULT: 'var(--portal, #7c3aed)', light: 'var(--portal-light, #ede9fe)' } },
    },
  },
};
