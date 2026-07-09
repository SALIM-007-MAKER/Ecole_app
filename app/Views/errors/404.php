<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 — Page introuvable</title>
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
:root { color-scheme: light; --bg:#f3f4f8; --surface:#fff; --text:#0f172a; --muted:#64748b; --violet:#7c3aed; }
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,sans-serif;background:linear-gradient(135deg,#f8f5ff,#eef2ff);color:var(--text);min-height:100vh;display:grid;place-items:center;padding:1.5rem} .card{width:min(100%,480px);background:var(--surface);border:1px solid rgba(124,58,237,.12);box-shadow:0 20px 45px rgba(15,23,42,.08);border-radius:1.5rem;padding:2rem;text-align:center} .icon{width:4rem;height:4rem;border-radius:1.25rem;background:rgba(124,58,237,.12);display:grid;place-items:center;margin:0 auto 1rem;color:var(--violet);font-size:1.5rem} h1{margin:.25rem 0 .5rem;font-size:2rem} p{margin:0 0 1.25rem;color:var(--muted);line-height:1.6} .actions{display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap} a{display:inline-flex;align-items:center;gap:.5rem;padding:.8rem 1rem;border-radius:999px;text-decoration:none;font-weight:600} .btn-light{background:#f8fafc;border:1px solid #e2e8f0;color:#334155} .btn-primary{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff}
</style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fa-solid fa-file-circle-xmark"></i></div>
        <p style="font-size:3rem;font-weight:800;letter-spacing:-.04em;color:var(--violet);margin-bottom:.25rem">404</p>
        <h1>Page introuvable</h1>
        <p>La page que vous recherchez n'existe pas ou a été déplacée.</p>
        <div class="actions">
            <a class="btn-light" href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i>Retour</a>
            <a class="btn-primary" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>"><i class="fa-solid fa-house"></i>Accueil</a>
        </div>
    </div>
</body>
</html>
