<?php
/* Portail plateforme : même gabarit que layouts/auth.php (connexion des établissements), identité « EduNova Platform ». */
$branding = \Core\Tenant\BrandingService::make()->get((int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <?php $pageTitle = htmlspecialchars($title ?? 'Portail plateforme', ENT_QUOTES, 'UTF-8'); ?>
    <title><?= $pageTitle ?> — EduNova Platform</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= htmlspecialchars($branding->primaryColor, ENT_QUOTES) ?>">
    <?php $__brandWeb = $branding->faviconUrl ?? $branding->logoUrl; ?>
    <?php if ($__brandWeb && file_exists(ROOT_PATH . '/public' . $__brandWeb)): ?>
    <link rel="icon" href="<?= BASE_URL ?><?= $__brandWeb ?>">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?><?= $__brandWeb ?>">
    <?php endif; ?>

    <!-- Google Fonts — police du tenant -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($branding->fontFamily) ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CDN -->

    <!-- Design System -->
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
    <!-- Tailwind compilé (tools/tailwind/README.md) : en FIN de <head>, comme le faisait le CDN, pour conserver la même cascade avec app.css -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/tailwind/tailwind.css?v=<?= @filemtime(ROOT_PATH . '/public/assets/vendor/tailwind/tailwind.css') ?: 1 ?>">
    <style>:root{--font-sans:'<?= addslashes($branding->fontFamily) ?>'}</style>
    <style>
    /* Tient dans l'écran sans défilement : tout se compacte avec la hauteur disponible. */
    .auth-bg{min-height:100vh;min-height:100dvh;box-sizing:border-box;padding:1rem !important;justify-content:center}
    @media (max-height:700px){.pf-brand{margin-bottom:.5rem !important}.auth-card{padding:1.25rem !important}.pf-foot{margin-top:.5rem !important}}
    @media (max-height:560px){.pf-foot{display:none}}
    </style>
</head>
<body>

<div class="auth-bg">

    <div class="pf-brand" style="display:flex;flex-direction:column;align-items:center;gap:.25rem;margin-bottom:1rem;color:var(--txt-1)">
        <div class="pf-logo" style="width:clamp(84px,19vh,150px);height:clamp(84px,19vh,150px);box-sizing:border-box;border-radius:50%;background:#fcfcfc;border:2px solid rgba(124,58,237,.25);box-shadow:0 4px 14px rgba(15,23,42,.12);display:flex;align-items:center;justify-content:center">
            <img src="<?= BASE_URL ?>/assets/img/edunova-logo.png" alt="EduNova — Organiser l'école, inspirer l'avenir" width="116" height="116" style="width:68%;height:68%;object-fit:contain;display:block">
        </div>
        <span style="font-weight:600;font-size:.95rem;letter-spacing:.04em;color:var(--violet)">Portail Plateforme</span>
    </div>

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
    <p class="pf-foot" style="margin-top:1rem;font-size:0.75rem;color:var(--txt-3);text-align:center">
        Accès réservé aux opérateurs de la plateforme EduNova.
    </p>
</div>

<!-- Toast container -->
<div id="toast-container"></div>

<script src="<?= BASE_URL ?>/assets/vendor/lucide/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
<script>window.APP_BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
