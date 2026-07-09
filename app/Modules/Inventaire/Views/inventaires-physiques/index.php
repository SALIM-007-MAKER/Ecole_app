<?php /** @var array $inventaires */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inventaires Physiques</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Inventaires Physiques</h1>
        <a href="/v2/inventaire/inventaires-physiques/creer"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i> Ouvrir inventaire
        </a>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Nom</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Ouverture</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Clôture</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($inventaires as $inv): ?>
                <?php $colors=['en_cours'=>'blue','termine'=>'green','annule'=>'red']; $col=$colors[$inv['statut']]??'slate'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($inv['nom']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($inv['date_debut'])) ?></td>
                    <td class="px-4 py-3 text-slate-400"><?= $inv['date_fin'] ? date('d/m/Y', strtotime($inv['date_fin'])) : '—' ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= str_replace('_', ' ', ucfirst($inv['statut'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <?php if ($inv['statut'] === 'en_cours'): ?>
                        <a href="/v2/inventaire/inventaires-physiques/<?=$inv['id']?>/session" class="text-violet-600 hover:underline text-xs">Saisir</a>
                        <form method="POST" action="/v2/inventaire/inventaires-physiques/<?=$inv['id']?>/cloture" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-green-600 hover:underline text-xs" onclick="return confirm('Clôturer et appliquer les ajustements ?')">Clôturer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($inventaires)): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucun inventaire.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
