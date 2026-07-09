<?php /** @var array $maintenances @var array $dues */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Maintenances — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Maintenances</h1>
        <a href="/v2/inventaire/maintenances/creer"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 text-sm font-medium">
            <i data-lucide="plus" class="w-4 h-4"></i> Planifier maintenance
        </a>
    </div>

    <?php if (!empty($dues)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
        <p class="text-amber-700 font-semibold text-sm"><?= count($dues) ?> maintenance(s) prévue(s) ce mois</p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Type</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Date planifiée</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Prestataire</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Coût</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($maintenances as $m): ?>
                <?php $colors=['planifiee'=>'slate','en_cours'=>'blue','terminee'=>'green','annulee'=>'red']; $col=$colors[$m['statut']]??'slate'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium"><?= htmlspecialchars($m['designation']) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($m['reference']) ?></div>
                    </td>
                    <td class="px-4 py-3"><?= ucfirst($m['type']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($m['date_planifiee'])) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($m['prestataire'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-right"><?= $m['cout'] ? number_format((float)$m['cout'], 2).' €' : '—' ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= str_replace('_', ' ', ucfirst($m['statut'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-1">
                        <?php if ($m['statut'] === 'planifiee'): ?>
                        <form method="POST" action="/v2/inventaire/maintenances/<?=$m['id']?>/demarrer" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-blue-600 hover:underline text-xs">Démarrer</button>
                        </form>
                        <?php endif; ?>
                        <?php if (in_array($m['statut'], ['planifiee','en_cours'], true)): ?>
                        <form method="POST" action="/v2/inventaire/maintenances/<?=$m['id']?>/annuler" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-red-500 hover:underline text-xs">Annuler</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
