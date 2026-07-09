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
        body { font-family: ui-sans-serif, system-ui, sans-serif; }
        .platform-bg {
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 3rem 1rem; background: radial-gradient(circle at top, #1e293b, #0f172a 60%);
        }
        .platform-card {
            width: 100%; max-width: 24rem; background: #0f172a; border: 1px solid #334155;
            border-radius: 1rem; padding: 2.25rem 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,.5);
        }
        .platform-card label { color: #cbd5e1; }
        .platform-card input { background: #1e293b; border-color: #334155; color: #f1f5f9; }
    </style>
</head>
<body>
<div class="platform-bg">
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem;color:#e2e8f0">
        <i data-lucide="server-cog" style="width:1.5rem;height:1.5rem;color:#a78bfa"></i>
        <span style="font-weight:700;font-size:1.1rem">EduNova <span style="color:#a78bfa">Platform</span></span>
    </div>

    <?php if (\Core\Session::hasFlash('errors')): ?>
    <div class="alert alert-danger" style="width:100%;max-width:24rem;margin-bottom:1rem" role="alert">
        <i data-lucide="alert-circle" style="width:1rem;height:1rem;flex-shrink:0"></i>
        <div style="flex:1">
            <?php foreach ((array)\Core\Session::getFlash('errors') as $msg): ?>
            <div style="font-size:0.875rem"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="platform-card"><?= $content ?></div>

    <p style="margin-top:2rem;font-size:0.75rem;color:#64748b;text-align:center">
        Accès réservé aux opérateurs de la plateforme EduNova.
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
</body>
</html>
