<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planification — <?= htmlspecialchars($planif['nom'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/rapports/planifications" class="text-slate-400 hover:text-violet-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($planif['nom'] ?? '') ?></h1>
        <span class="px-2 py-0.5 text-xs rounded-full <?= $planif['actif'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
            <?= $planif['actif'] ? 'Actif' : 'Inactif' ?>
        </span>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm">
        <?= $_GET['success'] === 'executed' ? 'Exécution lancée.' : 'Enregistré.' ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-4">
        <dl class="grid grid-cols-2 gap-y-4 gap-x-8">
            <div><dt class="text-xs font-medium text-slate-500">Domaine</dt>
                <dd class="text-sm text-slate-800 mt-0.5 capitalize"><?= htmlspecialchars($planif['domaine'] ?? '') ?></dd></div>
            <div><dt class="text-xs font-medium text-slate-500">Format</dt>
                <dd class="text-sm text-slate-800 mt-0.5"><?= strtoupper(htmlspecialchars($planif['type_export'] ?? '')) ?></dd></div>
            <div><dt class="text-xs font-medium text-slate-500">Fréquence</dt>
                <dd class="text-sm text-slate-800 mt-0.5 capitalize"><?= htmlspecialchars($planif['frequence'] ?? '') ?></dd></div>
            <div><dt class="text-xs font-medium text-slate-500">Heure</dt>
                <dd class="text-sm text-slate-800 mt-0.5"><?= htmlspecialchars(substr($planif['heure_execution'] ?? '', 0, 5)) ?></dd></div>
            <div><dt class="text-xs font-medium text-slate-500">Créé le</dt>
                <dd class="text-sm text-slate-800 mt-0.5"><?= htmlspecialchars($planif['created_at'] ?? '') ?></dd></div>
        </dl>
    </div>

    <div class="flex gap-3">
        <a href="/v2/rapports/planifications/<?= $planif['id'] ?>/edit"
           class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
            <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
        </a>
        <form method="post" action="/v2/rapports/planifications/<?= $planif['id'] ?>/executer"
              onsubmit="return confirm('Exécuter ce rapport maintenant ?')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                <i data-lucide="play" class="w-4 h-4"></i> Exécuter
            </button>
        </form>
        <form method="post" action="/v2/rapports/planifications/<?= $planif['id'] ?>/supprimer"
              onsubmit="return confirm('Supprimer cette planification ?')">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button class="flex items-center gap-2 px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Supprimer
            </button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
