<?php
$annee          = $annee          ?? '';
$annees         = $annees         ?? [];
$periodes       = $periodes       ?? [];
$periodeId      = $periodeId      ?? null;
$kpiGlobal      = $kpiGlobal      ?? [];
$elevesParClasse= $elevesParClasse?? [];
$elevesParNiveau= $elevesParNiveau?? [];
$distribution   = $distribution   ?? null;

$f   = fn($n) => number_format((float)$n, 0, ',', ' ');
$pct = fn($a, $b) => $b > 0 ? round($a / $b * 100) : 0;
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="graduation-cap" class="w-6 h-6 text-violet-600"></i>Statistiques scolaires
    </h2>
    <div class="flex items-center gap-2 flex-wrap">
        <form class="flex gap-2" method="GET">
            <select name="annee" class="form-select" onchange="this.form.submit()">
                <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
            <select name="periode_id" class="form-select" onchange="this.form.submit()">
                <option value="">Toutes périodes</option>
                <?php foreach ($periodes as $p): ?>
                <option value="<?= $p->id ?>" <?= $periodeId == $p->id ? 'selected' : '' ?>><?= htmlspecialchars($p->nom, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= BASE_URL ?>/rapports/export/excel/scolaire" class="btn btn-outline text-emerald-600 border-emerald-200 hover:bg-emerald-50">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>Excel
        </a>
    </div>
</div>

<!-- Nav -->
<div class="flex items-center gap-1 mb-6 overflow-x-auto pb-1">
    <a href="<?= BASE_URL ?>/rapports" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>Dashboard
    </a>
    <a href="<?= BASE_URL ?>/rapports/scolaire" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-violet-600 text-white">
        <i data-lucide="graduation-cap" class="w-4 h-4"></i>Scolaire
    </a>
    <a href="<?= BASE_URL ?>/rapports/financier" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="banknote" class="w-4 h-4"></i>Finance
    </a>
    <a href="<?= BASE_URL ?>/rapports/presences" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="calendar-check" class="w-4 h-4"></i>Présences
    </a>
    <a href="<?= BASE_URL ?>/rapports/reussite" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="trophy" class="w-4 h-4"></i>Réussite
    </a>
</div>

<!-- KPIs -->
<?php
$total = (int)($kpiGlobal['total_eleves'] ?? 0);
$actifs= (int)($kpiGlobal['eleves_actifs'] ?? 0);
$g     = (int)($kpiGlobal['garcons'] ?? 0);
$fi    = (int)($kpiGlobal['filles'] ?? 0);
$kpis  = [
    ['label'=>'Total élèves',  'val'=>$f($total),                          'icon'=>'users',       'bg'=>'bg-violet-100', 'tc'=>'text-violet-600'],
    ['label'=>'Élèves actifs', 'val'=>$f($actifs),                         'icon'=>'user-check',  'bg'=>'bg-emerald-100','tc'=>'text-emerald-600'],
    ['label'=>'Classes',       'val'=>$f($kpiGlobal['classes'] ?? 0),      'icon'=>'building-2',  'bg'=>'bg-sky-100',    'tc'=>'text-sky-600'],
    ['label'=>'Garçons',       'val'=>$f($g).' ('.$pct($g,$total).'%)',    'icon'=>'user',        'bg'=>'bg-indigo-100', 'tc'=>'text-indigo-600'],
    ['label'=>'Filles',        'val'=>$f($fi).' ('.$pct($fi,$total).'%)',  'icon'=>'user',        'bg'=>'bg-pink-100',   'tc'=>'text-pink-600'],
    ['label'=>'Enseignants',   'val'=>$f($kpiGlobal['professeurs'] ?? 0),  'icon'=>'user-cog',    'bg'=>'bg-amber-100',  'tc'=>'text-amber-600'],
];
?>
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <?php foreach ($kpis as $k): ?>
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
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
    <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-600"></i>Effectifs par classe
        </div>
        <div class="p-4 relative" style="height:300px">
            <canvas id="elevesChart"></canvas>
        </div>
    </div>
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="pie-chart" class="w-4 h-4 text-sky-600"></i>Distribution des moyennes
        </div>
        <div class="p-4 relative" style="height:300px">
            <?php if ($distribution): ?>
            <canvas id="distribChart"></canvas>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full text-slate-400">
                <i data-lucide="info" class="w-10 h-10 mb-2 opacity-40"></i>
                <p class="text-sm text-center">Sélectionnez une période pour afficher la distribution.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tableau effectifs -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center justify-between">
        <span class="flex items-center gap-2">
            <i data-lucide="table" class="w-4 h-4 text-violet-600"></i>Détail par classe
        </span>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-violet-100 text-violet-700">
            <?= count($elevesParClasse) ?> classes
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Classe</th>
                    <th class="px-4 py-3 text-left font-semibold">Niveau</th>
                    <th class="px-4 py-3 text-center font-semibold">Total</th>
                    <th class="px-4 py-3 text-center font-semibold">Garçons</th>
                    <th class="px-4 py-3 text-center font-semibold">Filles</th>
                    <th class="px-4 py-3 text-left font-semibold">Répartition</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($elevesParClasse as $row):
                $nb = (int)$row->nb_eleves;
                $pg = $nb > 0 ? round($row->garcons / $nb * 100) : 0;
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($row->nom, ENT_QUOTES) ?></td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                        <?= htmlspecialchars($row->niveau ?? '', ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center font-bold text-slate-800"><?= $nb ?></td>
                <td class="px-4 py-3 text-center font-semibold text-indigo-600"><?= (int)$row->garcons ?></td>
                <td class="px-4 py-3 text-center font-semibold text-pink-600"><?= (int)$row->filles ?></td>
                <td class="px-4 py-3" style="min-width:140px">
                    <?php if ($nb > 0): ?>
                    <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden flex mb-1">
                        <div class="h-full bg-indigo-500 rounded-l-full" style="width:<?= $pg ?>%"></div>
                        <div class="h-full bg-pink-400 rounded-r-full" style="width:<?= 100-$pg ?>%"></div>
                    </div>
                    <p class="text-xs text-slate-400"><?= $pg ?>% G / <?= 100-$pg ?>% F</p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($elevesParClasse)): ?>
            <tr>
                <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                    <p>Aucune donnée</p>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($elevesParClasse)): ?>
            <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold text-sm">
                <tr>
                    <td class="px-4 py-3 text-slate-600" colspan="2">Total</td>
                    <td class="px-4 py-3 text-center text-slate-800"><?= $f($kpiGlobal['eleves_actifs'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-center text-indigo-600"><?= $f($kpiGlobal['garcons'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-center text-pink-600"><?= $f($kpiGlobal['filles'] ?? 0) ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "system-ui, sans-serif";
    var PALETTE = ['#7c3aed','#10b981','#0ea5e9','#f59e0b','#ef4444','#6366f1','#f97316','#14b8a6'];

    var ed = <?= json_encode(array_map(fn($r) => ['l'=>$r->nom,'g'=>(int)$r->garcons,'f'=>(int)$r->filles], $elevesParClasse), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('elevesChart'), {
        type: 'bar',
        data: {
            labels: ed.map(d=>d.l),
            datasets: [
                { label:'Garçons', data:ed.map(d=>d.g), backgroundColor:'#6366f1', borderRadius:3, stack:'s' },
                { label:'Filles',  data:ed.map(d=>d.f), backgroundColor:'#f472b6', borderRadius:3, stack:'s' }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{beginAtZero:true,stacked:true,grid:{color:'#f1f5f9'}},x:{stacked:true,grid:{display:false}}} }
    });

    <?php if ($distribution): ?>
    new Chart(document.getElementById('distribChart'), {
        type: 'bar',
        data: {
            labels: ['< 5','5–10','10–12','12–14','14–16','≥ 16'],
            datasets: [{ label:'Nb élèves',
                data: [<?= (int)($distribution->t0_5??0) ?>,<?= (int)($distribution->t5_10??0) ?>,<?= (int)($distribution->t10_12??0) ?>,<?= (int)($distribution->t12_14??0) ?>,<?= (int)($distribution->t14_16??0) ?>,<?= (int)($distribution->t16_20??0) ?>],
                backgroundColor:['#ef4444','#f59e0b','#0ea5e9','#10b981','#7c3aed','#6366f1'], borderRadius:4
            }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });
    <?php endif; ?>
})();
</script>
