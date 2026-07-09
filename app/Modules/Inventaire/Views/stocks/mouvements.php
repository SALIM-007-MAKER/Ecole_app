<?php /** @var array $mouvements */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal des mouvements — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/v2/inventaire/stocks" class="text-slate-500 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Journal des mouvements de stock</h1>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Type</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Quantité</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Avant</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Après</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Référence</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php
            $typeColors = [
                'entree'      => 'green',
                'sortie'      => 'red',
                'transfert'   => 'blue',
                'ajustement'  => 'amber',
            ];
            $typeLabels = [
                'entree'      => 'Entrée',
                'sortie'      => 'Sortie',
                'transfert'   => 'Transfert',
                'ajustement'  => 'Ajustement',
            ];
            ?>
            <?php foreach ($mouvements as $m): ?>
                <?php
                $col   = $typeColors[$m['type']] ?? 'slate';
                $label = $typeLabels[$m['type']] ?? ucfirst($m['type']);
                $qty   = (float)$m['quantite'];
                $sign  = $m['type'] === 'entree' ? '+' : ($m['type'] === 'sortie' ? '−' : '');
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-slate-500 text-xs"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= $label ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium"><?= htmlspecialchars($m['designation'] ?? '—') ?></p>
                        <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($m['reference'] ?? '') ?></p>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-<?=$col?>-700">
                        <?= $sign ?><?= $qty ?>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-500"><?= (float)$m['quantite_avant'] ?></td>
                    <td class="px-4 py-3 text-right font-semibold"><?= (float)$m['quantite_apres'] ?></td>
                    <td class="px-4 py-3 text-xs text-slate-500">
                        <?= htmlspecialchars($m['reference_type'] ?? '') ?>
                        <?php if (!empty($m['reference_id']) && $m['reference_id'] > 0): ?>
                            #<?= $m['reference_id'] ?>
                        <?php endif; ?>
                        <?php if (!empty($m['notes'])): ?>
                            <span class="text-slate-400">— <?= htmlspecialchars($m['notes']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($mouvements)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Aucun mouvement enregistré.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
