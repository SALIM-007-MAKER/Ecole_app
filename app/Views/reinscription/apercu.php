<?php
$source      = $source      ?? '';
$destination = $destination ?? '';
$lignes      = $lignes      ?? [];

$totalDeplaces = 0;
$totalSortants = 0;
$totalDepassement = 0;
foreach ($lignes as $l) {
    if ($l['sortant']) { $totalSortants += (int)$l['classe_source']->nb_eleves; }
    else { $totalDeplaces += (int)$l['classe_source']->nb_eleves; }
    if ($l['depassement']) { $totalDepassement++; }
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="shield-check" class="w-5 h-5 text-violet-600"></i>Confirmer la réinscription
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Vérifiez le résumé avant d'exécuter — cette opération déplace les élèves en masse.</p>
    </div>
</div>

<!-- KPI -->
<div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-5 p-4 text-center">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="arrow-right" class="w-5 h-5 text-emerald-600"></i>
            </div>
            <p class="text-2xl font-black text-slate-900"><?= $totalDeplaces ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Élèves à réinscrire</p>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-5 p-4 text-center">
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="log-out" class="w-5 h-5 text-slate-500"></i>
            </div>
            <p class="text-2xl font-black text-slate-900"><?= $totalSortants ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Élèves sortants</p>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-5 p-4 text-center">
            <div class="w-10 h-10 rounded-xl <?= $totalDepassement > 0 ? 'bg-red-100' : 'bg-emerald-100' ?> flex items-center justify-center mx-auto mb-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 <?= $totalDepassement > 0 ? 'text-red-500' : 'text-emerald-600' ?>"></i>
            </div>
            <p class="text-2xl font-black <?= $totalDepassement > 0 ? 'text-red-600' : 'text-slate-900' ?>"><?= $totalDepassement ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Classe(s) en dépassement</p>
        </div>
    </div>
</div>

<?php if ($totalDepassement > 0): ?>
<div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 mb-5 text-sm text-red-800">
    <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
    <div>Certaines classes destination n'ont pas assez de places pour tous les élèves. Les élèves en surnombre seront ignorés (à traiter manuellement ensuite) — la capacité de chaque classe destination ne sera jamais dépassée.</div>
</div>
<?php endif; ?>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100">
            <thead>
                <tr>
                    <th>Classe source</th>
                    <th class="text-center">Élèves</th>
                    <th></th>
                    <th>Classe destination</th>
                    <th class="text-center">Places restantes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lignes as $l): ?>
                <tr>
                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($l['classe_source']->niveau . ' — ' . $l['classe_source']->nom, ENT_QUOTES) ?></td>
                    <td class="text-center"><?= (int)$l['classe_source']->nb_eleves ?></td>
                    <td class="text-center text-slate-300"><i data-lucide="arrow-right" class="w-4 h-4"></i></td>
                    <td>
                        <?php if ($l['sortant']): ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">Sortant (fin de cycle)</span>
                        <?php else: ?>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($l['classe_destination']->niveau . ' — ' . $l['classe_destination']->nom, ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if (!$l['sortant']): ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $l['depassement'] ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' ?>">
                            <?= $l['places_restantes'] ?? '∞' ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/reinscription/executer">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
    <input type="hidden" name="source" value="<?= htmlspecialchars($source, ENT_QUOTES) ?>">
    <input type="hidden" name="destination" value="<?= htmlspecialchars($destination, ENT_QUOTES) ?>">
    <?php foreach ($lignes as $l): ?>
    <input type="hidden" name="classes_source[]" value="<?= $l['classe_source']->id ?>">
    <input type="hidden" name="classe_dest_<?= $l['classe_source']->id ?>"
           value="<?= $l['sortant'] ? 'sortant' : $l['classe_destination']->id ?>">
    <?php endforeach; ?>

    <div class="flex items-center justify-end gap-3">
        <a href="<?= BASE_URL ?>/reinscription/plan?source=<?= urlencode($source) ?>&destination=<?= urlencode($destination) ?>" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Modifier le plan
        </a>
        <button type="submit" class="btn btn-success" onclick="return confirm('Confirmer la réinscription de <?= $totalDeplaces ?> élève(s) et le passage en sortant de <?= $totalSortants ?> élève(s) ? Cette action est difficilement réversible.');">
            <i data-lucide="check" class="w-4 h-4"></i>Confirmer et exécuter
        </button>
    </div>
</form>
