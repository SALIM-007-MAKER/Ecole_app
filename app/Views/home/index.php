<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNova — Gestion scolaire</title>
    <meta name="theme-color" content="#7c3aed">
    <?php
    $__logoPng = $_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/img/edunova-logo.png';
    $__logoSvg = $_SERVER['DOCUMENT_ROOT'] . '/ecole_app/public/assets/img/edunova-logo.svg';
    $__brandWeb = file_exists($__logoSvg) ? '/assets/img/edunova-logo.svg' : '/assets/img/edunova-logo.png';
    ?>
    <link rel="icon" href="<?= BASE_URL ?><?= $__brandWeb ?>">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?><?= $__brandWeb ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } }
    }
    </script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .gradient-text {
            background: linear-gradient(135deg, #7c3aed, #2563eb);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hero-bg {
            background: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(124,58,237,0.12) 0%, transparent 70%);
        }
        .feature-card { transition: transform 0.2s, box-shadow 0.2s; }
        .feature-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-white text-slate-800 min-h-full">

<!-- Nav -->
<nav class="border-b border-slate-100 bg-white/80 backdrop-blur-sm sticky top-0 z-10">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-violet-600 flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>
            <span class="font-bold text-slate-900 tracking-tight">EduNova</span>
        </div>
        <a href="<?= BASE_URL ?>/login"
           class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-lg transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Connexion
        </a>
    </div>
</nav>

<!-- Hero -->
<main class="hero-bg">
    <div class="max-w-6xl mx-auto px-6 pt-20 pb-24 text-center">

        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-violet-50 border border-violet-100 text-violet-700 text-xs font-semibold mb-6">
            <span class="w-1.5 h-1.5 rounded-full bg-violet-500 animate-pulse"></span>
            Plateforme de gestion scolaire complète
        </span>

        <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold tracking-tight leading-tight mb-6">
            Gérez votre établissement<br>
            <span class="gradient-text">avec élégance</span>
        </h1>
        <p class="text-lg text-slate-500 max-w-2xl mx-auto mb-10 leading-relaxed">
            EduNova centralise élèves, enseignants, notes, absences, emplois du temps et finances
            dans une interface moderne pensée pour les établissements scolaires.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="<?= BASE_URL ?>/login"
               class="inline-flex items-center gap-2 px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl transition-colors shadow-lg shadow-violet-200 text-sm">
                Accéder à l'application →
            </a>
        </div>

        <!-- Stats row -->
        <div class="flex items-center justify-center gap-8 mt-16 flex-wrap">
            <?php
            $feats = [
                ['Élèves', 'suivis en temps réel'],
                ['9', 'modules intégrés'],
                ['100%', 'responsive & PWA'],
            ];
            foreach ($feats as [$n, $l]):
            ?>
            <div class="text-center">
                <div class="text-2xl font-bold text-slate-900"><?= $n ?></div>
                <div class="text-xs text-slate-400 mt-0.5"><?= $l ?></div>
            </div>
            <?php if (next($feats)): ?>
            <div class="w-px h-10 bg-slate-200"></div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- Features -->
    <div class="max-w-6xl mx-auto px-6 pb-20">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php
            $features = [
                ['#ede9fe','#7c3aed','M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2 M9 12l2 2 4-4','Gestion des élèves','Inscriptions, fiches élèves, importation CSV, suivi individuel complet.'],
                ['#dcfce7','#16a34a','M12 20h9 M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z','Notes & Bulletins','Saisie des contrôles, calcul automatique des moyennes, bulletins PDF.'],
                ['#e0f2fe','#0284c7','M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75','Enseignants','Affectation matières/classes, tableau de bord pédagogique personnalisé.'],
                ['#fef3c7','#d97706','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z','Emploi du temps','Planning hebdomadaire/mensuel, gestion des salles et des créneaux.'],
                ['#fce7f3','#db2777','M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-8 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z','Comptabilité','Frais scolaires, paiements, dépenses, caisse et rapports financiers.'],
                ['#d1fae5','#059669','M9 19v-6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2zm0 0V9a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v10m-6 0a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2m0 0V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v14a2 2 0 0 0-2 2h-2a2 2 0 0 0-2-2z','Rapports & Stats','Dashboard analytique, statistiques scolaires, financières et de présences.'],
            ];
            foreach ($features as [$bg, $ic, $path, $title, $desc]):
            ?>
            <div class="feature-card bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4" style="background:<?= $bg ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $ic ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="<?= $path ?>"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-slate-900 mb-1.5"><?= $title ?></h3>
                <p class="text-sm text-slate-500 leading-relaxed"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<footer class="border-t border-slate-100 py-6 text-center text-xs text-slate-400">
    &copy; <?= date('Y') ?> EduNova — Tous droits réservés
</footer>

<script src="https://cdn.jsdelivr.net/npm/lucide@0.400.0/dist/umd/lucide.min.js"></script>
<script>if(window.lucide)lucide.createIcons();</script>
</body>
</html>
