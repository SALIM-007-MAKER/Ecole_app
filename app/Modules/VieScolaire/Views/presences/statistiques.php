<?php
$classes       = $classes       ?? [];
$classeId      = $classeId      ?? 0;
$anneeScolaire = $anneeScolaire ?? date('Y') . '-' . (date('Y') + 1);
$statsClasse   = $statsClasse   ?? [];
$perms         = $perms         ?? [];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600"></i>
            Statistiques des présences
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Taux de présence par classe et par élève</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>Liste des sessions
    </a>
</div>

<!-- Filtres -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classe</label>
            <select name="classe_id" class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
                <option value="">Choisir une classe…</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classeId == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nom'], ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
            <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($anneeScolaire, ENT_QUOTES) ?>"
                   pattern="\d{4}-\d{4}" placeholder="2025-2026"
                   class="w-32 rounded-lg border border-slate-200 text-sm px-3 py-1.5 focus:ring-2 focus:ring-violet-300 focus:outline-none">
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition-colors">
            <i data-lucide="search" class="w-4 h-4"></i>Analyser
        </button>
    </form>
</div>

<?php if ($classeId > 0 && !empty($statsClasse)): ?>
<!-- Résumé classe -->
<?php
$tauxMoyen = count($statsClasse) > 0
    ? round(array_sum(array_column($statsClasse, 'taux_presence')) / count($statsClasse), 1)
    : 0;
$tauxColor = $tauxMoyen >= 80 ? 'emerald' : ($tauxMoyen >= 60 ? 'amber' : 'red');
?>
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center">
                <i data-lucide="users" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= count($statsClasse) ?></p>
                <p class="text-xs text-slate-500">Élèves analysés</p>
            </div>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-<?= $tauxColor ?>-100 flex items-center justify-center">
                <i data-lucide="percent" class="w-5 h-5 text-<?= $tauxColor ?>-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= $tauxMoyen ?>%</p>
                <p class="text-xs text-slate-500">Taux moyen de présence</p>
            </div>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                <i data-lucide="user-x" class="w-5 h-5 text-red-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900">
                    <?= count(array_filter($statsClasse, fn($r) => (float)$r['taux_presence'] < 75)) ?>
                </p>
                <p class="text-xs text-slate-500">Élèves &lt; 75%</p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau détail -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-700">Détail par élève — <?= htmlspecialchars($anneeScolaire, ENT_QUOTES) ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Séances</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Présent</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Absent</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Retard</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Taux</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($statsClasse as $row):
                $taux  = (float)($row['taux_presence'] ?? 0);
                $tc    = $taux >= 80 ? 'emerald' : ($taux >= 60 ? 'amber' : 'red');
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-900">
                    <?= htmlspecialchars($row['eleve_nom'], ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-center text-slate-700"><?= (int)$row['total_seances'] ?></td>
                <td class="px-4 py-3 text-center text-emerald-700 font-medium"><?= (int)$row['presents'] ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ((int)$row['absents'] > 0): ?>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"><?= (int)$row['absents'] ?></span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-center">
                    <?php if ((int)$row['retards'] > 0): ?>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700"><?= (int)$row['retards'] ?></span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-slate-100 rounded-full h-1.5">
                            <div class="bg-<?= $tc ?>-500 h-1.5 rounded-full" style="width:<?= min($taux, 100) ?>%"></div>
                        </div>
                        <span class="text-xs font-medium text-<?= $tc ?>-700 w-10"><?= $taux ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($classeId > 0): ?>
<div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
    <i data-lucide="clipboard" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p class="text-sm">Aucune donnée de présence pour cette classe sur la période sélectionnée.</p>
</div>
<?php else: ?>
<div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
    <i data-lucide="bar-chart-2" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p class="text-sm">Sélectionnez une classe pour afficher les statistiques.</p>
</div>
<?php endif; ?>
