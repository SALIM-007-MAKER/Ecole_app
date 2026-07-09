<?php /** @var array $articles @var array $pagination @var array $categories @var object $filters */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Articles — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Articles</h1>
            <p class="text-slate-500 text-sm"><?= $pagination['total'] ?? 0 ?> article(s) au catalogue</p>
        </div>
        <a href="/v2/inventaire/articles/creer"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 text-sm font-medium">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouvel article
        </a>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="search" value="<?= htmlspecialchars($filters->search ?? '') ?>"
               placeholder="Rechercher..." class="border rounded-lg px-3 py-2 text-sm">
        <select name="type" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Tous types</option>
            <option value="consommable" <?= ($filters->type ?? '') === 'consommable' ? 'selected' : '' ?>>Consommable</option>
            <option value="durable"     <?= ($filters->type ?? '') === 'durable'     ? 'selected' : '' ?>>Durable</option>
            <option value="equipement"  <?= ($filters->type ?? '') === 'equipement'  ? 'selected' : '' ?>>Équipement</option>
        </select>
        <select name="categorie_id" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($filters->categorieId ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-slate-700 text-white rounded-lg px-4 py-2 text-sm">Filtrer</button>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Référence</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Désignation</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Type</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Catégorie</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Stock</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($articles as $a): ?>
                <?php $enAlerte = (float)($a['stock_total'] ?? 0) <= (float)($a['seuil_alerte'] ?? 0); ?>
                <tr class="hover:bg-slate-50 <?= $enAlerte ? 'bg-red-50' : '' ?>">
                    <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= htmlspecialchars($a['reference']) ?></td>
                    <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($a['designation']) ?></td>
                    <td class="px-4 py-3">
                        <?php $typeColors = ['consommable'=>'blue','durable'=>'green','equipement'=>'purple']; $c2=$typeColors[$a['type']]??'slate'; ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$c2?>-100 text-<?=$c2?>-700">
                            <?= ucfirst($a['type']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($a['categorie_nom'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right">
                        <span class="font-semibold <?= $enAlerte ? 'text-red-600' : 'text-slate-800' ?>">
                            <?= number_format((float)($a['stock_total'] ?? 0), 1) ?>
                        </span>
                        <?php if ($enAlerte): ?>
                        <i data-lucide="alert-triangle" class="w-3 h-3 text-red-500 inline ml-1"></i>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="/v2/inventaire/articles/<?= $a['id'] ?>" class="text-violet-600 hover:underline text-xs mr-2">Voir</a>
                        <a href="/v2/inventaire/articles/<?= $a['id'] ?>/modifier" class="text-slate-500 hover:underline text-xs">Modifier</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($articles)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucun article trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
