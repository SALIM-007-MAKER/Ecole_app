<?php
use App\Services\MenuService;
use Core\Tenant\BrandingService;

/* ── Branding tenant (Phase 14.5) ─────────────────────────────────────────
   L'établissement vient TOUJOURS de la session de l'utilisateur connecté
   (jamais d'un état global partagé) — repli sur l'établissement par défaut
   tant que TenantMiddleware n'est pas activé (cohérent avec Phases 14.2-14.4). */
$__sessionForBranding = \Core\Session::getUser();
$__etabIdForBranding = $__sessionForBranding['etablissement_id']
    ?? (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
$branding = BrandingService::make()->get((int)$__etabIdForBranding);
$__logoFsPath = ROOT_PATH . '/public' . $branding->logoUrl;
$__brandWeb = file_exists($__logoFsPath) ? $branding->logoUrl : null;

/* ── Données utilisateur ──────────────────────────────────────────────── */
$currentUser = \Core\Session::getUser();
$role        = $currentUser['role'] ?? '';
$perms       = $currentUser['permissions'] ?? [];
$uri         = $_SERVER['REQUEST_URI'] ?? '';
$userName    = htmlspecialchars(trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? 'Utilisateur')), ENT_QUOTES);
$userInitial = strtoupper(($currentUser['prenom'][0] ?? '') ?: ($currentUser['nom'][0] ?? 'U'));

/* ── Labels rôles ─────────────────────────────────────────────────────── */
$roleLabels = [
    'admin'      => 'Administrateur',
    'directeur'  => 'Directeur',
    'enseignant' => 'Enseignant',
    'comptable'  => 'Comptable',
    'secretaire' => 'Secrétaire',
    'parent'     => 'Parent',
    'eleve'      => 'Élève',
];
$roleLabel = $roleLabels[$role] ?? ucfirst($role);

$roleColors = [
    'admin'      => 'bg-red-100 text-red-700',
    'directeur'  => 'bg-violet-100 text-violet-700',
    'enseignant' => 'bg-amber-100 text-amber-700',
    'comptable'  => 'bg-emerald-100 text-emerald-700',
    'secretaire' => 'bg-sky-100 text-sky-700',
    'parent'     => 'bg-indigo-100 text-indigo-700',
    'eleve'      => 'bg-slate-100 text-slate-700',
];
$roleClass = $roleColors[$role] ?? 'bg-slate-100 text-slate-700';

/* ── Menu dynamique ───────────────────────────────────────────────────── */
$menus = MenuService::getMenuStructure($role, $perms);
$currentUri = $uri;
$bottomNavItems = MenuService::getBottomNavItems($role, $perms);

/* ── Titre de page ────────────────────────────────────────────────────────
   Source unique : $title si la vue le définit explicitement, sinon on le
   déduit du libellé du menu correspondant à l'URL courante (même source
   que la sidebar), pour que le header ne retombe jamais sur un titre figé. */
$pageTitle = $title ?? MenuService::resolveActiveLabel($menus, $currentUri) ?? 'Tableau de bord';
$pageTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <title><?= $pageTitle ?> — <?= htmlspecialchars($branding->appName, ENT_QUOTES) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>">
    <meta name="csrf-token" content="<?= \Core\Session::getCsrfToken() ?>">

    <!-- PWA -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($branding->appName, ENT_QUOTES) ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <?php $__faviconFsPath = ROOT_PATH . '/public' . $branding->faviconUrl; $__faviconWeb = file_exists($__faviconFsPath) ? $branding->faviconUrl : $__brandWeb; ?>
    <?php if ($__faviconWeb): ?>
    <link rel="icon" href="<?= BASE_URL ?><?= $__faviconWeb ?>">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?><?= $__faviconWeb ?>">
    <?php endif; ?>

    <!-- Google Fonts — police du tenant -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($branding->fontFamily) ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css">

    <!-- Tailwind CDN (utilitaires pour les vues uniquement) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: { extend: { fontFamily: { sans: ['<?= addslashes($branding->fontFamily) ?>','ui-sans-serif','system-ui','sans-serif'] } } }
    }
    </script>

    <!-- Design System — chargé APRÈS Tailwind pour avoir priorité -->
    <?php $cssV = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/css/app.css') ?: '1'; ?>
    <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= $cssV ?>" rel="stylesheet">

    <!-- Branding tenant — couleurs (Phase 14.5, blueprint §10.1/§10.2) -->
    <style>
        :root {
            --violet: <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>;
            --violet-hover: color-mix(in srgb, <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?> 85%, black);
            --violet-light: color-mix(in srgb, <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?> 85%, white);
            --brand-secondary: <?= htmlspecialchars($branding->secondaryColor, ENT_QUOTES) ?>;
        }
        body { font-family: '<?= addslashes($branding->fontFamily) ?>', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>
<body class="app-shell">

<!-- ══ OVERLAY MOBILE ═══════════════════════════════════════════════════ -->
<div id="sidebar-overlay" aria-hidden="true"></div>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════ -->
<aside id="sidebar" class="app-sidebar" aria-label="Navigation principale">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <?php if ($__brandWeb): ?>
                <img src="<?= BASE_URL ?><?= $__brandWeb ?>" alt="<?= htmlspecialchars($branding->appName, ENT_QUOTES) ?>" class="brand-logo-img" />
            <?php else: ?>
                <i class="fa-solid fa-graduation-cap"></i>
            <?php endif; ?>
        </div>
        <span class="sidebar-brand-name"><?= htmlspecialchars($branding->appName, ENT_QUOTES) ?></span>
        <button id="toggleSidebar" class="sidebar-collapse-btn" aria-label="Réduire la navigation" title="Réduire la navigation">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <button id="closeSidebar" class="sidebar-close-btn" aria-label="Fermer le menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <?php include __DIR__ . '/partials/navigation.php'; ?>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/profile" class="sidebar-user">
            <?php if (!empty($currentUser['photo'])): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($currentUser['photo'], ENT_QUOTES) ?>"
                     class="sidebar-user-avatar" style="object-fit:cover" alt="">
            <?php else: ?>
                <div class="sidebar-user-avatar"><?= $userInitial ?></div>
            <?php endif; ?>
            <div>
                <div class="sidebar-user-name"><?= $userName ?></div>
                <div class="sidebar-user-role"><?= $roleLabel ?></div>
            </div>
            <i class="fa-solid fa-gear" style="margin-left:auto;width:0.875rem;height:0.875rem;color:var(--sb-text)"></i>
        </a>
    </div>
</aside>

<!-- ══ MAIN WRAPPER ══════════════════════════════════════════════════════ -->
<div class="main-wrapper" role="application" aria-label="Interface <?= htmlspecialchars($branding->appName, ENT_QUOTES) ?>">

    <!-- ── HEADER ────────────────────────────────────────────────────── -->
    <header class="app-header">
        <button id="openSidebar" class="header-icon-btn" aria-label="Ouvrir le menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="header-brand-mobile">
            <div class="header-brand-icon">
                <?php if ($__brandWeb): ?>
                    <img src="<?= BASE_URL ?><?= $__brandWeb ?>" alt="<?= htmlspecialchars($branding->appName, ENT_QUOTES) ?>" class="brand-logo-img" />
                <?php else: ?>
                    <i class="fa-solid fa-graduation-cap"></i>
                <?php endif; ?>
            </div>
            <div>
                <p class="header-brand-title"><?= htmlspecialchars($branding->appName, ENT_QUOTES) ?></p>
                <p class="header-brand-subtitle"><?= $roleLabel ?></p>
            </div>
        </div>

        <h1 class="page-title">
            <?= $pageTitle ?>
        </h1>

        <div style="display:flex;align-items:center;gap:0.25rem;margin-left:auto;flex-shrink:0">
            <a href="<?= BASE_URL ?>/notifications" class="header-icon-btn" title="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span id="notif-badge"></span>
            </a>

            <div style="position:relative" id="userDropdownWrapper">
                <button id="userDropdownBtn"
                        class="user-chip"
                        aria-haspopup="true" aria-expanded="false">
                    <?php if (!empty($currentUser['photo'])): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($currentUser['photo'], ENT_QUOTES) ?>"
                             style="width:1.875rem;height:1.875rem;border-radius:50%;object-fit:cover;flex-shrink:0" alt="">
                    <?php else: ?>
                        <div class="avatar-chip">
                            <?= $userInitial ?>
                        </div>
                    <?php endif; ?>
                    <span class="user-chip-name"><?= htmlspecialchars($currentUser['prenom'] ?? $currentUser['nom'] ?? '', ENT_QUOTES) ?></span>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>

                <div id="userDropdown" class="dropdown-menu hidden">
                    <div style="padding:.875rem 1rem;border-bottom:1px solid var(--border)">
                        <div style="font-size:.875rem;font-weight:600;color:var(--txt-1)"><?= $userName ?></div>
                        <div style="font-size:.75rem;color:var(--txt-3);margin-top:.125rem">
                            <?= htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES) ?>
                        </div>
                        <span class="badge <?= $roleClass ?>" style="margin-top:.375rem"><?= $roleLabel ?></span>
                    </div>
                    <?php $__availableSchools = $currentUser['available_etablissements'] ?? []; ?>
                    <?php if (count($__availableSchools) > 1): ?>
                    <div style="padding:.5rem 1rem .25rem;font-size:.6875rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--txt-3)">
                        Changer d'établissement
                    </div>
                    <?php foreach ($__availableSchools as $__school): ?>
                    <form method="POST" action="<?= BASE_URL ?>/auth/switch-school">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <input type="hidden" name="school_id" value="<?= (int)$__school['id'] ?>">
                        <button type="submit" class="dropdown-item" style="width:100%"
                                <?= $__school['id'] === ($currentUser['etablissement_id'] ?? null) ? 'disabled aria-current="true"' : '' ?>>
                            <i class="fa-solid fa-school"></i>
                            <span><?= htmlspecialchars($__school['nom_court'], ENT_QUOTES) ?></span>
                            <?php if ($__school['id'] === ($currentUser['etablissement_id'] ?? null)): ?>
                            <i class="fa-solid fa-check" style="margin-left:auto;color:var(--violet)"></i>
                            <?php endif; ?>
                        </button>
                    </form>
                    <?php endforeach; ?>
                    <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/profile" class="dropdown-item">
                        <i class="fa-solid fa-user"></i>Mon profil
                    </a>
                    <a href="<?= BASE_URL ?>/notifications/preferences" class="dropdown-item">
                        <i class="fa-solid fa-gear"></i>Paramètres
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="<?= BASE_URL ?>/logout">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <button type="submit" class="dropdown-item danger" style="width:100%">
                            <i class="fa-solid fa-right-from-bracket"></i>Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- ── CONTENU ────────────────────────────────────────────────────── -->
    <main role="main" style="flex:1">
        <div style="padding:1.5rem;max-width:1440px;margin:0 auto">

            <?php /* Messages flash — succès/info se ferment seuls après 5s,
                     erreur/avertissement restent affichés jusqu'à fermeture
                     manuelle (l'utilisateur doit pouvoir les lire/agir). */
            foreach (['success'=>['check-circle','#f0fdf4','#bbf7d0','#166534',true],
                       'error'  =>['alert-circle','#fef2f2','#fecaca','#991b1b',false],
                       'warning'=>['alert-triangle','#fffbeb','#fde68a','#92400e',false],
                       'info'   =>['info','#eff6ff','#bfdbfe','#1e40af',true]]
                     as $type => [$icon, $bg, $br, $tc, $autoDismiss]):
                if (!\Core\Session::hasFlash($type)) continue; ?>
            <div role="alert" <?= $autoDismiss ? 'data-autodismiss="5000"' : '' ?>
                 style="display:flex;align-items:flex-start;gap:.75rem;padding:.875rem 1rem;
                        border-radius:.75rem;border:1px solid <?= $br ?>;background:<?= $bg ?>;
                        color:<?= $tc ?>;font-size:.875rem;margin-bottom:1rem;transition:opacity .3s ease">
                <i data-lucide="<?= $icon ?>" style="width:1rem;height:1rem;margin-top:.125rem;flex-shrink:0"></i>
                <span style="flex:1"><?= htmlspecialchars((string)\Core\Session::getFlash($type), ENT_QUOTES) ?></span>
                <button onclick="this.closest('[role=alert]').remove()"
                        style="background:none;border:none;cursor:pointer;opacity:.6;padding:.125rem"
                        onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.6">
                    <i data-lucide="x" style="width:1rem;height:1rem"></i>
                </button>
            </div>
            <?php endforeach; ?>
            <script>
            document.querySelectorAll('[role=alert][data-autodismiss]').forEach(function (el) {
                var delay = parseInt(el.getAttribute('data-autodismiss'), 10) || 5000;
                setTimeout(function () {
                    el.style.opacity = '0';
                    setTimeout(function () { el.remove(); }, 300);
                }, delay);
            });
            </script>

            <?php if (\Core\Session::hasFlash('errors')): ?>
            <div role="alert"
                 style="display:flex;align-items:flex-start;gap:.75rem;padding:.875rem 1rem;
                        border-radius:.75rem;border:1px solid #fde68a;background:#fffbeb;
                        color:#92400e;font-size:.875rem;margin-bottom:1rem">
                <i data-lucide="alert-triangle" style="width:1rem;height:1rem;margin-top:.125rem;flex-shrink:0"></i>
                <div style="flex:1">
                    <?php foreach ((array)\Core\Session::getFlash('errors') as $msgs): ?>
                    <?php foreach ((array)$msgs as $msg): ?>
                    <div><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <button onclick="this.closest('[role=alert]').remove()"
                        style="background:none;border:none;cursor:pointer;opacity:.6">
                    <i data-lucide="x" style="width:1rem;height:1rem"></i>
                </button>
            </div>
            <?php endif; ?>

            <?php $content = $content ?? ''; ?>
            <?= $content ?>

        </div>
    </main>


</div><!-- /.main-wrapper -->

<nav class="mobile-bottom-nav" aria-label="Navigation principale mobile">
    <?php foreach ($bottomNavItems as $item): ?>
        <?php $itemUrl = BASE_URL . ($item['url'] ?? '/'); ?>
        <a href="<?= $itemUrl ?>" class="mobile-nav-item<?= MenuService::isMenuActive($item['url'] ?? '', $currentUri) ? ' active' : '' ?>">
            <i class="fa-solid <?= htmlspecialchars(MenuService::getMenuIconClass($item['icon'] ?? 'home'), ENT_QUOTES) ?>"></i>
            <span><?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- Bannière hors-ligne -->
<div id="offline-banner" style="display:none;position:fixed;left:0;right:0;bottom:0;z-index:9998;
     background:#0f172a;padding:.75rem 1.5rem;color:#e2e8f0;font-size:.875rem;
     align-items:center;gap:.75rem" role="alert">
    <i data-lucide="wifi-off" style="width:1rem;height:1rem;flex-shrink:0"></i>
    <span style="flex:1">Vous êtes hors ligne. Certaines fonctionnalités sont indisponibles.</span>
    <button onclick="location.reload()" class="btn btn-sm btn-secondary">
        <i data-lucide="refresh-cw"></i>Réessayer
    </button>
</div>

<!-- Toast container -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>
<button id="scrollTopBtn" class="scroll-top-btn" title="Retour en haut" aria-label="Retour en haut">
    <i class="fa-solid fa-arrow-up"></i>
</button>

<!-- ══ SCRIPTS ═══════════════════════════════════════════════════════════ -->
<script>window.APP_BASE_URL = '<?= BASE_URL ?>';</script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
<script>if (window.lucide) lucide.createIcons();</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<?php if (\Core\Session::isLogged()): ?>
<script>
(function() {
    /* Push subscribe button */
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        var btn = document.getElementById('pushSubscribeBtn');
        if (btn) btn.style.display = 'inline-flex';
    }
    /* Notification badge */
    var BASE = '<?= BASE_URL ?>';
    function refreshBadge() {
        fetch(BASE + '/api/notifications/unread-count', { credentials: 'same-origin' })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(d) {
                if (!d) return;
                var count  = d.count || 0;
                var badge  = document.getElementById('notif-badge');
                var sBadge = document.querySelector('.sidebar-notif-badge');
                var label  = count > 99 ? '99+' : count;
                if (count > 0) {
                    if (badge)  { badge.textContent = label; badge.style.display = 'flex'; }
                    if (sBadge) { sBadge.textContent = label; sBadge.style.display = 'inline-flex'; }
                } else {
                    if (badge)  badge.style.display = 'none';
                    if (sBadge) sBadge.style.display = 'none';
                }
            }).catch(function(){});
    }
    refreshBadge();
    setInterval(refreshBadge, 30000);
})();
</script>
<?php endif; ?>

</body>
</html>
