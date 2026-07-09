<?php
// Variables attendues depuis PortalBaseController::portalRender()
$portal      = $portal      ?? 'admin';
$portalTitle = $portalTitle ?? 'Portail';
$menu        = $menu        ?? null;
$unreadCount = $unreadCount ?? 0;
$cssVars     = $cssVars     ?? '';
$portalColor = $portalColor ?? ['bg' => 'bg-violet-600', 'text' => 'text-violet-600', 'primary' => '#7c3aed', 'name' => 'violet'];
$theme       = $theme       ?? ['radius' => 'rounded-lg', 'shadow' => 'shadow-sm'];
$currentUser = $currentUser ?? [];
$baseUrl     = $baseUrl     ?? '';

$bgClass    = $portalColor['bg']   ?? 'bg-violet-600';
$textClass  = $portalColor['text'] ?? 'text-violet-600';
$colorName  = $portalColor['name'] ?? 'violet';
$userName   = trim(($currentUser['prenom'] ?? '') . ' ' . ($currentUser['nom'] ?? ''));
$userRole   = $currentUser['role'] ?? '';
$uri        = $_SERVER['REQUEST_URI'] ?? '';
?>
<!doctype html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? $portalTitle) ?> — EduNova</title>
    <meta name="theme-color" content="<?= htmlspecialchars($portalColor['primary']) ?>">
    <style><?= $cssVars ?></style>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        portal: {
                            DEFAULT: '<?= htmlspecialchars($portalColor['primary']) ?>',
                            light:   '<?= htmlspecialchars($portalColor['light'] ?? '#ede9fe') ?>',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-item.active { background-color: var(--portal-light); color: var(--portal-primary); font-weight: 600; }
        .sidebar-item:not(.active):hover { background-color: #f8fafc; }
        .badge-portal { background-color: var(--portal-primary); }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">

<!-- Layout principal : sidebar fixe + contenu scrollable -->
<div class="flex h-full">

    <!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-white border-r border-slate-200 transition-transform duration-200 shadow-sm -translate-x-full lg:translate-x-0">

        <!-- Logo / portail header -->
        <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-200 <?= $bgClass ?> text-white shrink-0">
            <i data-lucide="school" class="w-7 h-7 shrink-0"></i>
            <div class="min-w-0">
                <p class="font-bold text-sm leading-tight truncate">EduNova</p>
                <p class="text-xs opacity-80 truncate"><?= htmlspecialchars($portalTitle) ?></p>
            </div>
        </div>

        <!-- Menu navigation -->
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5" id="portal-nav">
            <?php if ($menu !== null): ?>
                <?php foreach ($menu->items as $item): ?>
                    <?php $hasChildren = !empty($item->children); ?>

                    <?php if ($item->separator): ?>
                        <div class="my-2 border-t border-slate-100"></div>
                    <?php endif; ?>

                    <?php if ($hasChildren): ?>
                        <!-- Item avec sous-menu -->
                        <div>
                            <button onclick="toggleSubmenu('sub-<?= $item->id ?>')"
                                    class="sidebar-item <?= $item->active ? 'active' : '' ?> w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-slate-600 transition-colors">
                                <i data-lucide="<?= htmlspecialchars($item->icon) ?>" class="w-4 h-4 shrink-0"></i>
                                <span class="flex-1 text-left truncate"><?= htmlspecialchars($item->label) ?></span>
                                <?php if ($item->badge): ?>
                                    <span class="badge-portal text-white text-xs px-1.5 py-0.5 rounded-full"><?= $item->badge ?></span>
                                <?php endif; ?>
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5 shrink-0 transition-transform" id="chevron-<?= $item->id ?>"></i>
                            </button>
                            <div id="sub-<?= $item->id ?>" class="<?= $item->active ? '' : 'hidden' ?> pl-4 mt-0.5 space-y-0.5">
                                <?php foreach ($item->children as $child): ?>
                                    <a href="<?= htmlspecialchars($baseUrl . $child->url) ?>"
                                       class="sidebar-item <?= $child->active ? 'active' : '' ?> flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-slate-600 transition-colors">
                                        <i data-lucide="<?= htmlspecialchars($child->icon) ?>" class="w-3.5 h-3.5 shrink-0"></i>
                                        <span class="flex-1 truncate"><?= htmlspecialchars($child->label) ?></span>
                                        <?php if ($child->badge): ?>
                                            <span class="badge-portal text-white text-xs px-1.5 py-0.5 rounded-full"><?= $child->badge ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Item simple -->
                        <a href="<?= htmlspecialchars($baseUrl . $item->url) ?>"
                           class="sidebar-item <?= $item->active ? 'active' : '' ?> flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-slate-600 transition-colors">
                            <i data-lucide="<?= htmlspecialchars($item->icon) ?>" class="w-4 h-4 shrink-0"></i>
                            <span class="flex-1 truncate"><?= htmlspecialchars($item->label) ?></span>
                            <?php if ($item->badge): ?>
                                <span class="badge-portal text-white text-xs px-1.5 py-0.5 rounded-full"><?= $item->badge ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <!-- Utilisateur connecté -->
        <div class="border-t border-slate-200 p-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full <?= $bgClass ?> text-white flex items-center justify-center font-semibold text-sm shrink-0">
                    <?= strtoupper(substr($userName ?: '?', 0, 1)) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-700 truncate"><?= htmlspecialchars($userName ?: 'Utilisateur') ?></p>
                    <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars(ucfirst($userRole)) ?></p>
                </div>
                <a href="<?= $baseUrl ?>/logout" class="text-slate-400 hover:text-red-500 transition-colors" title="Déconnexion">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ═══════════════════════ MAIN CONTENT ═══════════════════════ -->
    <div class="flex-1 flex flex-col min-w-0 ml-64">

        <!-- Topbar -->
        <header class="sticky top-0 z-30 bg-white border-b border-slate-200 h-16 flex items-center gap-3 px-4 shrink-0 shadow-sm">

            <!-- Burger mobile -->
            <button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')"
                    class="lg:hidden text-slate-500 hover:text-slate-700">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>

            <!-- Breadcrumbs -->
            <?php if ($menu !== null && !empty($menu->breadcrumbs)): ?>
                <nav class="hidden md:flex items-center gap-1 text-sm text-slate-400 min-w-0">
                    <?php foreach ($menu->breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?><i data-lucide="chevron-right" class="w-3.5 h-3.5 shrink-0"></i><?php endif; ?>
                        <?php if ($i < count($menu->breadcrumbs) - 1): ?>
                            <a href="<?= htmlspecialchars($baseUrl . $crumb['url']) ?>" class="hover:text-slate-600 truncate"><?= htmlspecialchars($crumb['label']) ?></a>
                        <?php else: ?>
                            <span class="font-medium text-slate-700 truncate"><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <!-- Recherche globale -->
            <div class="flex-1 max-w-md mx-auto hidden md:block">
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="search"
                           id="global-search"
                           placeholder="Rechercher..."
                           class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-portal focus:border-transparent"
                           autocomplete="off">
                    <div id="search-results" class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-slate-200 hidden z-50 max-h-80 overflow-y-auto"></div>
                </div>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <!-- Préférences -->
                <a href="<?= $baseUrl ?>/v2/portals/<?= $portal ?>/preferences"
                   class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100"
                   title="Préférences">
                    <i data-lucide="settings" class="w-4.5 h-4.5"></i>
                </a>

                <!-- Notifications -->
                <a href="<?= $baseUrl ?>/v2/portals/notifications"
                   class="relative text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100"
                   title="Notifications">
                    <i data-lucide="bell" class="w-4.5 h-4.5"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 badge-portal text-white text-xs w-4 h-4 rounded-full flex items-center justify-center font-bold leading-none">
                            <?= min(99, $unreadCount) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- Contenu de la page -->
        <main class="flex-1 overflow-y-auto p-4 md:p-6">
            <?= $content ?? '' ?>
        </main>

    </div><!-- /.main -->
</div><!-- /.flex -->

<!-- Scripts -->
<script>
lucide.createIcons();

// Sous-menu toggle
function toggleSubmenu(id) {
    const sub   = document.getElementById(id);
    const parts = id.split('-');
    const chev  = document.getElementById('chevron-' + parts.slice(1).join('-'));
    if (sub) {
        sub.classList.toggle('hidden');
        if (chev) chev.classList.toggle('rotate-180');
    }
}

// Recherche globale AJAX
const searchInput = document.getElementById('global-search');
const searchBox   = document.getElementById('search-results');
let searchTimer;
if (searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const q = searchInput.value.trim();
        if (q.length < 2) { searchBox.classList.add('hidden'); return; }
        searchTimer = setTimeout(() => {
            fetch('<?= $baseUrl ?>/api/v2/portals/<?= $portal ?>/search?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.results || data.results.results.length === 0) {
                    searchBox.innerHTML = '<p class="p-3 text-sm text-slate-400">Aucun résultat.</p>';
                } else {
                    searchBox.innerHTML = data.results.results.map(r =>
                        `<a href="${r.url}" class="flex items-center gap-3 p-3 hover:bg-slate-50 border-b border-slate-100 last:border-0">
                            <i data-lucide="${r.icon}" class="w-4 h-4 text-slate-400 shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-700 truncate">${r.titre}</p>
                                <p class="text-xs text-slate-400 truncate">${r.sous_titre || r.module}</p>
                            </div>
                        </a>`
                    ).join('');
                    lucide.createIcons();
                }
                searchBox.classList.remove('hidden');
            })
            .catch(() => searchBox.classList.add('hidden'));
        }, 300);
    });
    document.addEventListener('click', e => {
        if (!searchInput.contains(e.target) && !searchBox.contains(e.target)) {
            searchBox.classList.add('hidden');
        }
    });
}
</script>
</body>
</html>
