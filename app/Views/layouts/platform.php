<?php $operator = \Core\Platform\PlatformAuth::current(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($title ?? 'Portail Plateforme', ENT_QUOTES, 'UTF-8') ?> — EduNova Platform</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f172a">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { corePlugins: { preflight: false } }</script>
    <?php $cssV = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/css/app.css') ?: '1'; ?>
    <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= $cssV ?>" rel="stylesheet">
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; background: #0f172a; }
        .platform-header {
            background: #0f172a; border-bottom: 1px solid #1e293b; padding: 0 1.5rem;
            display: flex; align-items: center; gap: 2rem; height: 3.75rem;
        }
        .platform-header a { color: #94a3b8; font-size: .875rem; font-weight: 500; text-decoration: none; display: flex; align-items: center; gap: .375rem; }
        .platform-header a:hover, .platform-header a.active { color: #f1f5f9; }
        .platform-main { background: #f1f5f9; min-height: calc(100vh - 3.75rem); padding: 1.5rem; }
        .platform-content { max-width: 1280px; margin: 0 auto; }
    </style>
</head>
<body>

<header class="platform-header">
    <div style="display:flex;align-items:center;gap:.5rem;color:#f1f5f9;font-weight:700">
        <i data-lucide="server-cog" style="width:1.25rem;height:1.25rem;color:#a78bfa"></i>
        EduNova <span style="color:#a78bfa">Platform</span>
    </div>
    <nav style="display:flex;gap:1.5rem;flex:1">
        <a href="<?= BASE_URL ?>/platform/dashboard"><i data-lucide="layout-dashboard" style="width:1rem;height:1rem"></i>Tableau de bord</a>
        <a href="<?= BASE_URL ?>/platform/etablissements"><i data-lucide="building-2" style="width:1rem;height:1rem"></i>Établissements</a>
        <a href="<?= BASE_URL ?>/platform/plans"><i data-lucide="badge-dollar-sign" style="width:1rem;height:1rem"></i>Plans</a>
        <?php if (\Core\Platform\PlatformAuth::hasLevel('super_admin')): ?>
        <a href="<?= BASE_URL ?>/platform/backups"><i data-lucide="shield-check" style="width:1rem;height:1rem"></i>Sauvegardes</a>
        <?php endif; ?>
    </nav>
    <div style="display:flex;align-items:center;gap:1rem">
        <span style="color:#94a3b8;font-size:.8125rem"><?= htmlspecialchars($operator['email'] ?? '', ENT_QUOTES) ?> · <?= htmlspecialchars($operator['niveau'] ?? '', ENT_QUOTES) ?></span>
        <form method="POST" action="<?= BASE_URL ?>/platform/logout">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <button type="submit" style="color:#94a3b8;background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:.375rem;font-size:.875rem">
                <i data-lucide="log-out" style="width:1rem;height:1rem"></i>Déconnexion
            </button>
        </form>
    </div>
</header>

<main class="platform-main">
    <div class="platform-content">
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

<script src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
</body>
</html>
