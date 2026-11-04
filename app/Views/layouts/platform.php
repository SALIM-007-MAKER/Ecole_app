<?php
use Core\Tenant\BrandingService;

/*
 * Portail plateforme (Super-Admin SaaS) — MÊME coquille visuelle que l'espace des établissements (layouts/main.php) :
 * barre latérale violette, en-tête avec titre de page et menu utilisateur, mêmes classes du design system (app.css), même police et
 * même charte (couleurs de l'établissement par défaut). Seuls le menu et l'identité (opérateur, sans établissement) diffèrent.
 */
$operator = \Core\Platform\PlatformAuth::current();

$__etabIdForBranding = (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
$branding = BrandingService::make()->get($__etabIdForBranding);
$__logoFsPath = ROOT_PATH . '/public' . $branding->logoUrl;
$__brandWeb = file_exists($__logoFsPath) ? $branding->logoUrl : null;

$niveauLabels = ['super_admin' => 'Super administrateur', 'admin' => 'Administrateur plateforme', 'support' => 'Support'];
$niveauColors = ['super_admin' => 'bg-red-100 text-red-700', 'admin' => 'bg-violet-100 text-violet-700', 'support' => 'bg-sky-100 text-sky-700'];
$niveau       = (string)($operator['niveau'] ?? '');
$niveauLabel  = $niveauLabels[$niveau] ?? ucfirst($niveau);
$niveauClass  = $niveauColors[$niveau] ?? 'bg-slate-100 text-slate-700';
$opEmail      = (string)($operator['email'] ?? '');
$opNom        = trim((string)($operator['nom'] ?? '')) !== '' ? (string)$operator['nom'] : $opEmail;
$opInitial    = strtoupper(mb_substr($opNom !== '' ? $opNom : 'O', 0, 1));

/* Menu : un seul élément actif, déterminé par le chemin (sans query string ni sous-répertoire de l'application). */
$__chemin = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$__base   = (string)parse_url((string)BASE_URL, PHP_URL_PATH);
if ($__base !== '' && str_starts_with($__chemin, $__base)) { $__chemin = substr($__chemin, strlen($__base)); }
$__menu = [
    ['/platform/dashboard',     'Tableau de bord', 'fa-gauge-high',        true],
    ['/platform/etablissements', 'Établissements', 'fa-school',             true],
    ['/platform/plans',          'Plans',           'fa-tags',               true],
    ['/platform/revenus',        'Revenus',         'fa-coins',              \Core\Platform\PlatformAuth::hasLevel('super_admin')],
    ['/platform/parametres',     'Paramètres',      'fa-sliders',           \Core\Platform\PlatformAuth::hasLevel('super_admin')],
    ['/platform/audit',          "Journal d'audit", 'fa-clipboard-list',     \Core\Platform\PlatformAuth::hasLevel('admin', 'super_admin')],
    ['/platform/backups',        'Sauvegardes',     'fa-shield-halved',      \Core\Platform\PlatformAuth::hasLevel('super_admin')],
];
$__menu = array_values(array_filter($__menu, static fn(array $m): bool => $m[3]));
$__actif = null;
foreach ($__menu as $m) {
    if ($__chemin === $m[0] || str_starts_with($__chemin, $m[0] . '/')) { $__actif = $m[0]; break; }
}

$pageTitle = htmlspecialchars($title ?? 'Portail plateforme', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $pageTitle ?> — EduNova Platform</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>">
    <meta name="csrf-token" content="<?= \Core\Session::getCsrfToken() ?>">
    <?php $__faviconFsPath = ROOT_PATH . '/public' . $branding->faviconUrl; $__faviconWeb = file_exists($__faviconFsPath) ? $branding->faviconUrl : $__brandWeb; ?>
    <?php if ($__faviconWeb): ?>
    <link rel="icon" href="<?= BASE_URL ?><?= $__faviconWeb ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($branding->fontFamily) ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">

    <!-- Sidebar : état figé AVANT le premier rendu (comme layouts/main.php) -->
    <script>
    (function () {
        try {
            var h = document.documentElement;
            h.classList.add('sb-preload');
            if (window.innerWidth >= 1024 && localStorage.getItem('ecole_sidebar_collapsed') === '1') h.classList.add('sb-collapsed-init');
        } catch (e) {}
    })();
    </script>

    <?php $cssV = @filemtime(ROOT_PATH . '/public/assets/css/app.css') ?: '1'; ?>
    <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= $cssV ?>" rel="stylesheet">
    <style>
        :root {
            --violet: <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>;
            --violet-hover: color-mix(in srgb, <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?> 85%, black);
            --violet-light: color-mix(in srgb, <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?> 85%, white);
            --brand-secondary: <?= htmlspecialchars($branding->secondaryColor, ENT_QUOTES) ?>;
        }
        body { font-family: '<?= addslashes($branding->fontFamily) ?>', ui-sans-serif, system-ui, sans-serif; }
    </style>
    <!-- Tailwind compilé (tools/tailwind/README.md) : en FIN de <head>, comme le faisait le CDN, pour conserver la même cascade avec app.css -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/tailwind/tailwind.css?v=<?= @filemtime(ROOT_PATH . '/public/assets/vendor/tailwind/tailwind.css') ?: 1 ?>">
    <style>:root{--font-sans:'<?= addslashes($branding->fontFamily) ?>'}</style>
</head>
<body class="app-shell">

<div id="sidebar-overlay" aria-hidden="true"></div>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════ -->
<aside id="sidebar" class="app-sidebar" aria-label="Navigation de la plateforme">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <?php if ($__brandWeb): ?>
                <img src="<?= BASE_URL ?><?= $__brandWeb ?>" alt="EduNova" class="brand-logo-img" />
            <?php else: ?>
                <i class="fa-solid fa-server"></i>
            <?php endif; ?>
        </div>
        <span class="sidebar-brand-name">EduNova Platform</span>
        <button id="toggleSidebar" class="sidebar-collapse-btn" aria-label="Réduire la navigation" title="Réduire la navigation">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <button id="closeSidebar" class="sidebar-close-btn" aria-label="Fermer le menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="sidebar-nav" role="navigation" aria-label="Navigation de la plateforme">
        <?php foreach ($__menu as [$url, $label, $icone]): ?>
        <a href="<?= BASE_URL . $url ?>" class="nav-item<?= $url === $__actif ? ' nav-active' : '' ?>" title="<?= htmlspecialchars($label, ENT_QUOTES) ?>"<?= $url === $__actif ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= $icone ?>"></i>
            <span class="nav-item-text"><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/platform/compte/mot-de-passe" class="sidebar-user" title="Changer mon mot de passe">
            <div class="sidebar-user-avatar"><?= htmlspecialchars($opInitial, ENT_QUOTES) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($opNom, ENT_QUOTES) ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($niveauLabel, ENT_QUOTES) ?></div>
            </div>
            <i class="fa-solid fa-key" style="margin-left:auto;width:0.875rem;height:0.875rem;color:var(--sb-text)"></i>
        </a>
    </div>
</aside>

<!-- ══ MAIN WRAPPER ══════════════════════════════════════════════════════ -->
<div class="main-wrapper" role="application" aria-label="Interface EduNova Platform">

    <header class="app-header">
        <button id="openSidebar" class="header-icon-btn" aria-label="Ouvrir le menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="header-brand-mobile">
            <div class="header-brand-icon">
                <?php if ($__brandWeb): ?>
                    <img src="<?= BASE_URL ?><?= $__brandWeb ?>" alt="EduNova" class="brand-logo-img" />
                <?php else: ?>
                    <i class="fa-solid fa-server"></i>
                <?php endif; ?>
            </div>
            <div>
                <p class="header-brand-title">EduNova Platform</p>
                <p class="header-brand-subtitle"><?= htmlspecialchars($niveauLabel, ENT_QUOTES) ?></p>
            </div>
        </div>

        <h1 class="page-title"><?= $pageTitle ?></h1>

        <div style="display:flex;align-items:center;gap:0.25rem;margin-left:auto;flex-shrink:0">
            <div style="position:relative" id="userDropdownWrapper">
                <button id="userDropdownBtn" class="user-chip" aria-haspopup="true" aria-expanded="false">
                    <div class="avatar-chip"><?= htmlspecialchars($opInitial, ENT_QUOTES) ?></div>
                    <span class="user-chip-name"><?= htmlspecialchars($opNom, ENT_QUOTES) ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <div id="userDropdown" class="dropdown-menu hidden">
                    <div style="padding:.875rem 1rem;border-bottom:1px solid var(--border)">
                        <div style="font-size:.875rem;font-weight:600;color:var(--txt-1)"><?= htmlspecialchars($opNom, ENT_QUOTES) ?></div>
                        <div style="font-size:.75rem;color:var(--txt-3);margin-top:.125rem"><?= htmlspecialchars($opEmail, ENT_QUOTES) ?></div>
                        <span class="badge <?= $niveauClass ?>" style="margin-top:.375rem"><?= htmlspecialchars($niveauLabel, ENT_QUOTES) ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>/platform/compte/mot-de-passe" class="dropdown-item">
                        <i class="fa-solid fa-key"></i>Mon mot de passe
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="<?= BASE_URL ?>/platform/logout">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <button type="submit" class="dropdown-item danger" style="width:100%">
                            <i class="fa-solid fa-right-from-bracket"></i>Déconnexion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main role="main" style="flex:1">
        <div style="padding:1.5rem;max-width:1440px;margin:0 auto">
        <?php
        // Traçabilité : événements du fichier de secours pas encore rejoués dans le journal (panne d'écriture), et échec survenu pendant cette requête.
        $__secours = \Core\Platform\PlatformAuditService::resumeSecours();
        $__avertissement = \Core\Session::getFlash('audit_warning');
        if (\Core\Platform\PlatformAuditService::echecsDeLaRequete() > 0 && !$__avertissement) {
            $__avertissement = 'Une opération vient d\'être effectuée sans que sa traçabilité ait pu être enregistrée dans le journal d\'audit.';
        }
        ?>
        <?php if ($__avertissement || $__secours['count'] > 0): ?>
        <div class="alert alert-danger mb-4" role="alert" style="border-width:2px">
            <i data-lucide="shield-alert" class="w-4 h-4 shrink-0"></i>
            <div>
                <div style="font-size:.875rem;font-weight:700">Journal d'audit : traçabilité incomplète</div>
                <?php if ($__avertissement): ?>
                <div style="font-size:.875rem"><?= htmlspecialchars((string)$__avertissement, ENT_QUOTES) ?></div>
                <?php endif; ?>
                <?php if ($__secours['count'] > 0): ?>
                <div style="font-size:.875rem"><?= (int)$__secours['count'] ?> événement(s) n'ont pas pu être écrits dans la base et sont conservés dans le fichier de secours du serveur
                    (dossier storage/logs, dernier le <?= htmlspecialchars((string)$__secours['derniere'], ENT_QUOTES) ?>).
                    Une fois la base rétablie : <code>php database/audit-rejouer.php</code>.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if (\Core\Session::hasFlash('error')): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <span><?= htmlspecialchars((string)\Core\Session::getFlash('error'), ENT_QUOTES) ?></span>
        </div>
        <?php endif; ?>
        <?php if (\Core\Session::hasFlash('success')): ?>
        <div class="alert alert-success mb-4" role="alert">
            <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
            <span><?= htmlspecialchars(\Core\Session::getFlash('success'), ENT_QUOTES) ?></span>
        </div>
        <?php endif; ?>
        <?php if (\Core\Session::hasFlash('errors')): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <div>
                <?php foreach ((array)\Core\Session::getFlash('errors') as $msg): ?>
                <div style="font-size:.875rem"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?= $content ?>
        </div>
    </main>
</div><!-- /.main-wrapper -->

<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<script>window.APP_BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/vendor/lucide/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
