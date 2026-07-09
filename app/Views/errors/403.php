<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 — Accès refusé</title>
<?php
$__logoPng = $_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/img/edunova-logo.png';
$__logoSvg = $_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/img/edunova-logo.svg';
$__brandWeb = file_exists($__logoSvg) ? '/assets/img/edunova-logo.svg' : '/assets/img/edunova-logo.png';
$__base = defined('BASE_URL') ? BASE_URL : '';
?>
<link rel="icon" href="<?= $__base ?><?= $__brandWeb ?>">
<link rel="apple-touch-icon" href="<?= $__base ?><?= $__brandWeb ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>body { font-family: Inter, system-ui, sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="text-center max-w-sm w-full">
        <!-- Icon -->
        <div class="w-20 h-20 rounded-3xl bg-amber-100 flex items-center justify-center mx-auto mb-6 shadow-sm">
            <i class="fa-solid fa-shield-halved text-4xl text-amber-500"></i>
        </div>
        <!-- Big number -->
        <p class="text-[96px] font-black leading-none text-violet-600 select-none mb-2">403</p>
        <!-- Title -->
        <h1 class="text-2xl font-bold text-slate-900 mb-3">Accès refusé</h1>
        <p class="text-slate-500 text-sm leading-relaxed mb-8">
            Vous n'avez pas les permissions nécessaires pour accéder à cette page.
        </p>
        <!-- Actions -->
        <div class="flex items-center justify-center gap-3">
            <a href="javascript:history.back()"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>Retour
            </a>
            <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-all shadow-sm shadow-violet-200">
                <i class="fa-solid fa-house"></i>Accueil
            </a>
        </div>
    </div>
</body>
</html>
