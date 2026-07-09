<?php
$absencesParClasse = $absencesParClasse ?? [];
$absencesHebdo     = $absencesHebdo     ?? [];
$repartition       = $repartition       ?? [];
$topAbsents        = $topAbsents        ?? [];

$total      = (int)($repartition['total']          ?? 0);
$absences   = (int)($repartition['absences']       ?? 0);
$retards    = (int)($repartition['retards']        ?? 0);
$justifiees = (int)($repartition['justifiees']     ?? 0);
$nonJust    = (int)($repartition['non_justifiees'] ?? 0);
$tauxPresence = 0;
if ($absencesParClasse) {
    $totEleves = array_sum(array_column((array)$absencesParClasse, 'nb_eleves'));
    $totAbsVol = array_sum(array_column((array)$absencesParClasse, 'nb_absences'));
    if ($totEleves > 0) $tauxPresence = max(0, round((1 - $totAbsVol / max(1, $totEleves * 200)) * 100, 1));
}
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="calendar-check" class="w-6 h-6 text-sky-600"></i>Rapport de présences
    </h2>
    <a href="<?= BASE_URL ?>/rapports/export/excel/presences" class="btn btn-outline text-emerald-600 border-emerald-200 hover:bg-emerald-50">
        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>Excel
    </a>
</div>

<!-- Nav -->
<div class="flex items-center gap-1 mb-6 overflow-x-auto pb-1">
    <a href="<?= BASE_URL ?>/rapports" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>Dashboard
    </a>
    <a href="<?= BASE_URL ?>/rapports/scolaire" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="graduation-cap" class="w-4 h-4"></i>Scolaire
    </a>
    <a href="<?= BASE_URL ?>/rapports/financier" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="banknote" class="w-4 h-4"></i>Finance
    </a>
    <a href="<?= BASE_URL ?>/rapports/presences" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-violet-600 text-white">
        <i data-lucide="calendar-check" class="w-4 h-4"></i>Présences
    </a>
    <a href="<?= BASE_URL ?>/rapports/reussite" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="trophy" class="w-4 h-4"></i>Réussite
    </a>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <?php
    $kpis = [
        ['val'=>$total,             'label'=>'Total signalements', 'icon'=>'bell',        'bg'=>'bg-violet-100', 'tc'=>'text-violet-600'],
        ['val'=>$absences,          'label'=>'Absences',           'icon'=>'user-x',      'bg'=>'bg-red-100',    'tc'=>'text-red-600'],
        ['val'=>$retards,           'label'=>'Retards',            'icon'=>'clock',       'bg'=>'bg-amber-100',  'tc'=>'text-amber-600'],
        ['val'=>$justifiees,        'label'=>'Justifiées',         'icon'=>'check-circle','bg'=>'bg-emerald-100','tc'=>'text-emerald-600'],
        ['val'=>$nonJust,           'label'=>'Non justifiées',     'icon'=>'x-circle',    'bg'=>'bg-slate-100',  'tc'=>'text-slate-600'],
        ['val'=>$tauxPresence.'%',  'label'=>'Taux de présence',   'icon'=>'trending-up', 'bg'=>'bg-sky-100',    'tc'=>'text-sky-600'],
    ];
    foreach ($kpis as $k): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center">
        <div class="mx-auto mb-2 w-10 h-10 rounded-full <?= $k['bg'] ?> flex items-center justify-center">
            <i data-lucide="<?= $k['icon'] ?>" class="w-5 h-5 <?= $k['tc'] ?>"></i>
        </div>
        <p class="text-xl font-bold text-slate-800"><?= $k['val'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5"><?= $k['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="trending-up" class="w-4 h-4 text-amber-500"></i>Évolution des absences (16 dernières semaines)
        </div>
        <div class="p-4 relative" style="height:280px">
            <?php if ($absencesHebdo): ?>
            <canvas id="hebdoChart"></canvas>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                <i data-lucide="info" class="w-10 h-10 mb-2 opacity-40"></i>
                <p class="text-sm">Aucune donnée disponible</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="pie-chart" class="w-4 h-4 text-red-500"></i>Justifiées vs Non justifiées
        </div>
        <div class="p-4 flex items-center justify-center" style="height:280px">
            <?php if ($total > 0): ?>
            <canvas id="justChart"></canvas>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center text-slate-400">
                <i data-lucide="info" class="w-10 h-10 mb-2 opacity-40"></i>
                <p class="text-sm">Aucun signalement</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tables -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
    <!-- Absences par classe -->
    <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="table" class="w-4 h-4 text-violet-600"></i>Absences par classe
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800 text-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Classe</th>
                        <th class="px-4 py-3 text-center font-semibold">Élèves</th>
                        <th class="px-4 py-3 text-center font-semibold">Absences</th>
                        <th class="px-4 py-3 text-center font-semibold">Retards</th>
                        <th class="px-4 py-3 text-center font-semibold">Moy/élève</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($absencesParClasse as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($r->classe, ENT_QUOTES) ?></span>
                        <?php if ($r->niveau): ?>
                        <span class="ml-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600">
                            <?= htmlspecialchars($r->niveau, ENT_QUOTES) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-600"><?= (int)$r->nb_eleves ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-red-100 text-red-700">
                            <?= (int)$r->nb_seches ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700">
                            <?= (int)$r->nb_retards ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-slate-500"><?= $r->moy_par_eleve ?? '0' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$absencesParClasse): ?>
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                        <p>Aucune donnée</p>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top absents -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 text-red-500"></i>Top 10 des absents
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800 text-slate-100">
                    <tr>
                        <th class="px-4 py-3 text-center font-semibold w-10">#</th>
                        <th class="px-4 py-3 text-left font-semibold">Élève</th>
                        <th class="px-4 py-3 text-left font-semibold">Classe</th>
                        <th class="px-4 py-3 text-center font-semibold">Abs.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($topAbsents as $i => $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold <?= $i < 3 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $i + 1 ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($r->prenom . ' ' . $r->nom, ENT_QUOTES) ?></p>
                        <?php if (!empty($r->matricule)): ?>
                        <p class="text-slate-400 text-xs"><?= htmlspecialchars($r->matricule, ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars($r->classe ?? '—', ENT_QUOTES) ?></td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold bg-red-100 text-red-700">
                            <?= (int)$r->nb_absences ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$topAbsents): ?>
                <tr>
                    <td colspan="4" class="px-4 py-10 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                        <p>Aucune absence</p>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "system-ui, sans-serif";

    <?php if ($absencesHebdo): ?>
    var hd = <?= json_encode(array_map(fn($r) => ['l'=>$r->label,'a'=>(int)$r->absences,'r'=>(int)$r->retards], $absencesHebdo), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('hebdoChart'), {
        type: 'line',
        data: {
            labels: hd.map(d=>d.l),
            datasets: [
                { label:'Absences', data:hd.map(d=>d.a), borderColor:'#ef4444', backgroundColor:'#ef444433', fill:true, tension:.3, pointRadius:4 },
                { label:'Retards',  data:hd.map(d=>d.r), borderColor:'#f59e0b', backgroundColor:'#f59e0b33', fill:true, tension:.3, pointRadius:4 }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });
    <?php endif; ?>

    <?php if ($total > 0): ?>
    new Chart(document.getElementById('justChart'), {
        type: 'doughnut',
        data: {
            labels: ['Justifiées','Non justifiées'],
            datasets: [{ data:[<?= $justifiees ?>,<?= $nonJust ?>],
                backgroundColor:['#10b981','#ef4444'], borderWidth:2 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'bottom'}} }
    });
    <?php endif; ?>
})();
</script>
