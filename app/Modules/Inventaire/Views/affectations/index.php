<?php /** @var array $affectations @var string|null $statut */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Affectations — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Affectations</h1>
        <a href="/v2/inventaire/affectations/creer"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 text-sm font-medium">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Nouvelle affectation
        </a>
    </div>

    <div class="flex gap-2 mb-6">
        <?php foreach (['' => 'Toutes', 'en_cours' => 'En cours', 'retournee' => 'Retournée', 'perdue' => 'Perdue'] as $s => $label): ?>
        <a href="/v2/inventaire/affectations<?= $s ? "?statut={$s}" : '' ?>"
           class="px-3 py-1.5 rounded-full text-xs border <?= $statut === ($s ?: null) ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-slate-600' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Article</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Bénéficiaire</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Qté</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Date affectation</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Retour prévu</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($affectations as $a): ?>
                <?php $colors=['en_cours'=>'blue','retournee'=>'green','perdue'=>'red']; $col=$colors[$a['statut']]??'slate'; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($a['designation']) ?></div>
                        <div class="text-xs font-mono text-slate-400"><?= htmlspecialchars($a['reference']) ?></div>
                    </td>
                    <td class="px-4 py-3"><?= htmlspecialchars(($a['user_prenom'] ?? '').' '.($a['user_nom'] ?? '')) ?></td>
                    <td class="px-4 py-3 text-right font-semibold"><?= number_format((float)$a['quantite'], 1) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($a['date_affectation'])) ?></td>
                    <td class="px-4 py-3 text-slate-400"><?= $a['date_retour_prevue'] ? date('d/m/Y', strtotime($a['date_retour_prevue'])) : '—' ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= str_replace('_', ' ', ucfirst($a['statut'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <?php if ($a['statut'] === 'en_cours'): ?>
                        <form method="POST" action="/v2/inventaire/affectations/<?=$a['id']?>/retourner" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="etat" value="bon">
                            <button type="submit" class="text-green-600 hover:underline text-xs">Retour</button>
                        </form>
                        <form method="POST" action="/v2/inventaire/affectations/<?=$a['id']?>/perdu" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-red-500 hover:underline text-xs" onclick="return confirm('Déclarer perdu ?')">Perdu</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($affectations)): ?>
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Aucune affectation.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
