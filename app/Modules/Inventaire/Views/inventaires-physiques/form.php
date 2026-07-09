<?php /** @var string|null $error */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ouvrir inventaire physique — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/inventaire/inventaires-physiques" class="text-slate-500 hover:text-slate-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Ouvrir un inventaire physique</h1>
    </div>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-800">
        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
        <strong>Attention :</strong> L'ouverture d'un inventaire physique bloque les mouvements de stock sur les articles inventoriés.
    </div>

    <form method="POST" action="/v2/inventaire/inventaires-physiques" class="bg-white rounded-xl shadow-sm p-6 space-y-5">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Libellé <span class="text-red-500">*</span></label>
            <input type="text" name="libelle" required placeholder="Ex: Inventaire annuel 2026"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'inventaire <span class="text-red-500">*</span></label>
            <input type="date" name="date_inventaire" required value="<?= date('Y-m-d') ?>"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="/v2/inventaire/inventaires-physiques" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
            <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
                <i data-lucide="clipboard-list" class="w-4 h-4 inline mr-1"></i>Ouvrir l'inventaire
            </button>
        </div>
    </form>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
