# Compilation de Tailwind CSS

L'application n'utilise plus le CDN « Play » de Tailwind (réservé au développement, non épinglé, sans SRI, indisponible hors ligne) :
les utilitaires sont compilés en deux feuilles versionnées dans `public/assets/vendor/tailwind/`.

## Quand recompiler
À chaque fois que des classes Tailwind sont ajoutées ou retirées dans `app/**/*.php` ou `public/assets/js/**/*.js`.

## Outil (non versionné)
Binaire autonome **Tailwind CSS v3.4.17** (aucune installation Node requise) :

- Windows : https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-windows-x64.exe
  → à enregistrer sous `tools/tailwind/tailwindcss.exe`
- Somme de contrôle SHA-256 attendue : `67f1c5e3f5a03406a7bf5badf5ada09b79f3ae78ec43450c15f7e983068da346`
  (vérifiable avec `certutil -hashfile tools\tailwind\tailwindcss.exe SHA256`, et dans `sha256sums.txt` de la même version).

Le binaire est ignoré par git (`.gitignore`).

## Compiler
- Windows : `tools\tailwind\build.cmd`
- Linux/macOS : `sh tools/tailwind/build.sh` (binaire correspondant, variable `TAILWIND_BIN`)

Deux feuilles sont produites :

| Fichier | Preflight | Utilisée par |
|---|---|---|
| `tailwind.css` | non | `layouts/main`, `layouts/auth`, `layouts/platform`, `layouts/platform-auth`, `home/index` |
| `tailwind-preflight.css` | oui | pages autonomes : erreurs 403/404/500, `layouts/print`, `eleves/export-pdf`, portails, rapports, partages publics |

Les couleurs construites dynamiquement en PHP (`bg-<?= $couleur ?>-100`…) sont couvertes par la liste `safelist` de `tailwind.config.js`.
La police de l'établissement est passée par la variable CSS `--font-sans`, la couleur d'un portail par `--portal` / `--portal-light`.
