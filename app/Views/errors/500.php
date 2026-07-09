<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 — Erreur serveur</title>
<?php
$__logoPng = $_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/img/edunova-logo.png';
$__logoSvg = $_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/img/edunova-logo.svg';
$__brandWeb = file_exists($__logoSvg) ? '/assets/img/edunova-logo.svg' : '/assets/img/edunova-logo.png';
$__base = defined('BASE_URL') ? BASE_URL : '';
?>
<link rel="icon" href="<?= $__base ?><?= $__brandWeb ?>">
<link rel="apple-touch-icon" href="<?= $__base ?><?= $__brandWeb ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.7.2/css/all.min.css">
<style>
:root { color-scheme: light; --bg:#f3f4f8; --surface:#fff; --text:#0f172a; --muted:#64748b; --violet:#7c3aed; --danger:#dc2626; }
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,sans-serif;background:linear-gradient(135deg,#fef2f2,#fff7ed);color:var(--text);min-height:100vh;display:grid;place-items:center;padding:1.5rem} .card{width:min(100%,520px);background:var(--surface);border:1px solid rgba(220,38,38,.12);box-shadow:0 20px 45px rgba(15,23,42,.08);border-radius:1.5rem;padding:2rem;text-align:center} .icon{width:4rem;height:4rem;border-radius:1.25rem;background:rgba(220,38,38,.12);display:grid;place-items:center;margin:0 auto 1rem;color:var(--danger);font-size:1.5rem} h1{margin:.25rem 0 .5rem;font-size:2rem} p{margin:0 0 1.25rem;color:var(--muted);line-height:1.6} .debug{background:#fff1f2;border:1px solid #fecdd3;border-radius:1rem;padding:.9rem;text-align:left;font-family:Consolas,monospace;font-size:.8rem;color:var(--danger);overflow:auto;max-height:180px;margin-bottom:1rem} .actions{display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap} a{display:inline-flex;align-items:center;gap:.5rem;padding:.8rem 1rem;border-radius:999px;text-decoration:none;font-weight:600} .btn-light{background:#f8fafc;border:1px solid #e2e8f0;color:#334155} .btn-primary{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff}
</style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fa-solid fa-bug"></i></div>
        <p style="font-size:3rem;font-weight:800;letter-spacing:-.04em;color:var(--violet);margin-bottom:.25rem">500</p>
        <h1>Erreur serveur</h1>
        <p>Une erreur interne s'est produite. Veuillez réessayer ultérieurement ou contacter l'administrateur.</p>
        <?php if (!empty($message) && (defined('APP_DEBUG') && APP_DEBUG)): ?>
        <div class="debug"><?= htmlspecialchars($message, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <div class="actions">
            <a class="btn-light" href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i>Retour</a>
            <a class="btn-primary" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>"><i class="fa-solid fa-house"></i>Accueil</a>
        </div>
    </div>
</body>
</html>
