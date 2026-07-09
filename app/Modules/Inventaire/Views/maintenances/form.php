<?php /** @var array|null $maintenance @var array $articles @var string|null $error */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Planifier maintenance — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/inventaire/maintenances" class="text-slate-500 hover:text-slate-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Planifier une maintenance</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/v2/inventaire/maintenances" class="bg-white rounded-xl shadow-sm p-6 space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Équipement <span class="text-red-500">*</span></label>
            <select name="article_id" required
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                <option value="">— Sélectionner un équipement —</option>
                <?php foreach ($articles as $a): ?>
                <option value="<?=$a['id']?>"><?= htmlspecialchars($a['designation']) ?> (<?= htmlspecialchars($a['reference']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                    <option value="preventive">Préventive</option>
                    <option value="corrective">Corrective</option>
                    <option value="predictive">Prédictive</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date planifiée <span class="text-red-500">*</span></label>
                <input type="date" name="date_planifiee" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Prestataire</label>
                <input type="text" name="prestataire"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Coût estimé (€)</label>
                <input type="number" name="cout" step="0.01" min="0" value="0"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <textarea name="description" rows="3"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/v2/inventaire/maintenances" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
                Planifier
            </button>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
