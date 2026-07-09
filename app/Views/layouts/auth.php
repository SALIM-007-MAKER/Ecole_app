<?php $branding = $branding ?? \Core\Tenant\BrandingService::forCurrentRequest(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <?php $pageTitle = htmlspecialchars($title ?? 'Connexion', ENT_QUOTES, 'UTF-8'); ?>
    <title><?= $pageTitle ?> — <?= htmlspecialchars($branding->appName, ENT_QUOTES) ?></title>
    <meta name="description" content="<?= htmlspecialchars($branding->appName, ENT_QUOTES) ?> — Gestion scolaire complète.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>">

    <!-- PWA -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <?php $__brandWeb = $branding->faviconUrl ?? $branding->logoUrl; ?>
    <?php if ($__brandWeb && file_exists(ROOT_PATH . '/public' . $__brandWeb)): ?>
    <link rel="icon" href="<?= BASE_URL ?><?= $__brandWeb ?>">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?><?= $__brandWeb ?>">
    <?php endif; ?>

    <!-- Google Fonts — police du tenant -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($branding->fontFamily) ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: { extend: { fontFamily: { sans: ['<?= addslashes($branding->fontFamily) ?>','ui-sans-serif','system-ui','sans-serif'] } } }
    }
    </script>

    <!-- Design System -->
    <?php $cssV = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/css/app.css') ?: '1'; ?>
    <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= $cssV ?>" rel="stylesheet">

    <style>
    :root {
        --violet: <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>;
        --violet-hover: color-mix(in srgb, <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?> 85%, black);
        --violet-light: color-mix(in srgb, <?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?> 85%, white);
        --brand-secondary: <?= htmlspecialchars($branding->secondaryColor, ENT_QUOTES) ?>;
    }
    body { font-family: '<?= addslashes($branding->fontFamily) ?>', ui-sans-serif, system-ui, sans-serif; }
    /* Auth-specific layout */
    .auth-bg {
        min-height: 100%;
        background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 40%, #f8fafc 100%);
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 3rem 1rem;
    }
    .auth-card {
        width: 100%; max-width: 26rem;
        background: #fff;
        border-radius: 1.25rem;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-xl);
        padding: 2.5rem 2rem;
    }
    </style>
</head>
<body>

<div class="auth-bg">

    <!-- NOTE: logo moved inside the auth card per request -->

    <!-- Messages flash -->
    <?php if (\Core\Session::hasFlash('error')): ?>
    <div class="alert alert-danger" style="width:100%;max-width:26rem;margin-bottom:1rem" role="alert">
        <i data-lucide="alert-circle" style="width:1rem;height:1rem;flex-shrink:0"></i>
        <span style="flex:1"><?= htmlspecialchars(\Core\Session::getFlash('error'), ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <?php if (\Core\Session::hasFlash('success')): ?>
    <div class="alert alert-success" style="width:100%;max-width:26rem;margin-bottom:1rem" role="alert">
        <i data-lucide="check-circle" style="width:1rem;height:1rem;flex-shrink:0"></i>
        <span style="flex:1"><?= htmlspecialchars(\Core\Session::getFlash('success'), ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <?php if (\Core\Session::hasFlash('info')): ?>
    <div class="alert alert-info" style="width:100%;max-width:26rem;margin-bottom:1rem" role="alert">
        <i data-lucide="info" style="width:1rem;height:1rem;flex-shrink:0"></i>
        <span style="flex:1"><?= htmlspecialchars(\Core\Session::getFlash('info'), ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <?php if (\Core\Session::hasFlash('errors')): ?>
    <div class="alert alert-warning" style="width:100%;max-width:26rem;margin-bottom:1rem" role="alert">
        <i data-lucide="alert-triangle" style="width:1rem;height:1rem;flex-shrink:0"></i>
        <div style="flex:1">
            <?php foreach ((array)\Core\Session::getFlash('errors') as $msgs): ?>
            <?php foreach ((array)$msgs as $msg): ?>
                <div style="font-size:0.875rem"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Carte principale -->
    <div class="auth-card">
        <?= $content ?>
    </div>

    <!-- Pied de page -->
    <p style="margin-top:2rem;font-size:0.75rem;color:var(--txt-3);text-align:center">
        <?= htmlspecialchars($branding->footerText ?? ('© ' . date('Y') . ' ' . $branding->appName . ' — Tous droits réservés'), ENT_QUOTES) ?>
    </p>
</div>

<!-- Toast container -->
<div id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
