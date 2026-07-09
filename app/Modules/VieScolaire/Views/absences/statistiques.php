<?php
$classes       = $classes      ?? [];
$classeId      = $classeId     ?? 0;
$anneeScolaire = $anneeScolaire ?? date('Y') . '-' . (date('Y') + 1);
$statsClasse   = $statsClasse  ?? [];
$perms         = $perms        ?? [];
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600"></i>
            Statistiques des absences
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Analyse par classe et par élève</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
        <i data-lucide="list" class="w-4 h-4"></i>Liste des absences
    </a>
</div>

<!-- Filtres -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Classe</label>
            <select name="classe_id" required class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 bg-white focus:ring-2 focus:ring-violet-300 focus:outline-none">
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
<!-- Résumé de classe -->
<?php
$totalAbsences = array_sum(array_column($statsClasse, 'absences'));
$totalRetards  = array_sum(array_column($statsClasse, 'total')) - $totalAbsences;
$nonJustifiees = array_sum(array_column($statsClasse, 'non_justifiees'));
?>
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                <i data-lucide="calendar-x" class="w-5 h-5 text-red-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= $totalAbsences ?></p>
                <p class="text-xs text-slate-500">Absences totales</p>
            </div>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                <i data-lucide="clock" class="w-5 h-5 text-amber-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= $nonJustifiees ?></p>
                <p class="text-xs text-slate-500">Non justifiées</p>
            </div>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center">
                <i data-lucide="users" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= count($statsClasse) ?></p>
                <p class="text-xs text-slate-500">Élèves concernés</p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau par élève -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-700">Détail par élève — <?= htmlspecialchars($anneeScolaire, ENT_QUOTES) ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Total</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Absences</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Non justifiées</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($statsClasse as $row): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-900">
                    <?= htmlspecialchars($row['eleve_nom'], ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-center text-slate-700"><?= $row['total'] ?></td>
                <td class="px-4 py-3 text-center text-slate-700"><?= $row['absences'] ?></td>
                <td class="px-4 py-3 text-center">
                    <?php if ($row['non_justifiees'] > 0): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                        <?= $row['non_justifiees'] ?>
                    </span>
                    <?php else: ?>
                    <span class="text-emerald-600 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                    <a href="<?= BASE_URL ?>/v2/vie-scolaire/absences?eleve_id=<?= $row['eleve_id'] ?>&annee_scolaire=<?= urlencode($anneeScolaire) ?>"
                       class="text-xs text-violet-600 hover:text-violet-800 font-medium">Voir</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($classeId > 0): ?>
<div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
    <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p class="text-sm">Aucune absence enregistrée pour cette classe sur la période sélectionnée.</p>
</div>
<?php else: ?>
<div class="bg-white border border-slate-200 rounded-xl p-12 text-center text-slate-400">
    <i data-lucide="bar-chart-2" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
    <p class="text-sm">Sélectionnez une classe pour afficher les statistiques.</p>
</div>
<?php endif; ?>
