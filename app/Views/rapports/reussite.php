<?php
$periodes            = $periodes            ?? [];
$periodeId           = $periodeId           ?? null;
$classes             = $classes             ?? [];
$classeId            = $classeId            ?? null;
$kpiReussite         = $kpiReussite         ?? [];
$reussiteParClasse   = $reussiteParClasse   ?? [];
$mentionsDistrib     = $mentionsDistrib     ?? [];
$moyennesMatiere     = $moyennesMatiere     ?? [];
$progressionPeriodes = $progressionPeriodes ?? [];

$total   = (int)($kpiReussite['total']          ?? 0);
$reussis = (int)($kpiReussite['reussis']        ?? 0);
$taux    = (float)($kpiReussite['taux_reussite'] ?? 0);
$moy     = (float)($kpiReussite['moy_generale']  ?? 0);
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="trophy" class="w-5 h-5 text-violet-600"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-800 pt-2">Rapport de réussite</h2>
    </div>
    <a href="<?= BASE_URL ?>/rapports/export/excel/reussite?periode_id=<?= (int)$periodeId ?>" class="btn btn-outline text-emerald-600 border-emerald-200 hover:bg-emerald-50">
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
    <a href="<?= BASE_URL ?>/rapports/presences" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="calendar-check" class="w-4 h-4"></i>Présences
    </a>
    <a href="<?= BASE_URL ?>/rapports/reussite" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-violet-600 text-white">
        <i data-lucide="trophy" class="w-4 h-4"></i>Réussite
    </a>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6">
    <form class="flex flex-wrap gap-4 items-center" method="GET">
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-slate-600 whitespace-nowrap">Période :</label>
            <select name="periode_id" class="form-select" onchange="this.form.submit()">
                <option value="">Toutes</option>
                <?php foreach ($periodes as $p): ?>
                <option value="<?= $p->id ?>" <?= $periodeId == $p->id ? 'selected' : '' ?>><?= htmlspecialchars($p->nom, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-slate-600 whitespace-nowrap">Classe :</label>
            <select name="classe_id" class="form-select" onchange="this.form.submit()">
                <option value="">Toutes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c->id ?>" <?= $classeId == $c->id ? 'selected' : '' ?>><?= htmlspecialchars($c->nom, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- KPIs -->
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
    <?php
    $kpis = [
        ['val'=>$total,                              'label'=>'Bulletins évalués', 'icon'=>'book-open',      'bg'=>'bg-violet-100',  'tc'=>'text-violet-600'],
        ['val'=>$reussis,                            'label'=>'Réussis (moy ≥ 10)','icon'=>'check-circle',  'bg'=>'bg-emerald-100', 'tc'=>'text-emerald-600'],
        ['val'=>($total - $reussis),                 'label'=>'En difficulté',     'icon'=>'x-circle',       'bg'=>'bg-red-100',     'tc'=>'text-red-600'],
        ['val'=>$taux.'%',                           'label'=>'Taux de réussite',  'icon'=>'trending-up',    'bg'=>$taux>=60?'bg-emerald-100':'bg-amber-100', 'tc'=>$taux>=60?'text-emerald-600':'text-amber-600'],
        ['val'=>number_format($moy,2,',',''),        'label'=>'Moyenne générale',  'icon'=>'calculator',     'bg'=>'bg-sky-100',     'tc'=>'text-sky-600'],
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
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-emerald-600"></i>Taux de réussite par classe
        </div>
        <div class="p-4 relative" style="height:280px">
            <?php if ($reussiteParClasse): ?>
            <canvas id="reussiteChart"></canvas>
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
            <i data-lucide="award" class="w-4 h-4 text-amber-500"></i>Répartition des mentions
        </div>
        <div class="p-4 flex items-center justify-center" style="height:280px">
            <?php if ($mentionsDistrib): ?>
            <canvas id="mentionsChart"></canvas>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center text-slate-400">
                <i data-lucide="info" class="w-10 h-10 mb-2 opacity-40"></i>
                <p class="text-sm">Aucune donnée disponible</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tableau réussite par classe -->
<?php if ($reussiteParClasse): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="table" class="w-4 h-4 text-violet-600"></i>Détail par classe
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Classe</th>
                    <th class="px-4 py-3 text-left font-semibold">Niveau</th>
                    <th class="px-4 py-3 text-center font-semibold">Total</th>
                    <th class="px-4 py-3 text-center font-semibold">Réussis</th>
                    <th class="px-4 py-3 text-center font-semibold">Taux</th>
                    <th class="px-4 py-3 text-center font-semibold">Moy.</th>
                    <th class="px-4 py-3 text-center font-semibold">Max</th>
                    <th class="px-4 py-3 text-center font-semibold">Min</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($reussiteParClasse as $r):
                $t2 = $r->total > 0 ? round($r->reussis / $r->total * 100) : 0;
                $barBg = $t2 >= 80 ? 'bg-emerald-500' : ($t2 >= 50 ? 'bg-amber-400' : 'bg-red-500');
                $barTc = $t2 >= 80 ? 'text-emerald-600' : ($t2 >= 50 ? 'text-amber-600' : 'text-red-600');
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($r->classe, ENT_QUOTES) ?></td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                        <?= htmlspecialchars($r->niveau ?? '', ENT_QUOTES) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-center text-slate-600"><?= (int)$r->total ?></td>
                <td class="px-4 py-3 text-center font-bold text-emerald-600"><?= (int)$r->reussis ?></td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <div class="w-14 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full <?= $barBg ?>" style="width:<?= $t2 ?>%"></div>
                        </div>
                        <span class="text-xs font-bold <?= $barTc ?>"><?= $t2 ?>%</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-center text-slate-700"><?= number_format((float)$r->moy_classe,2,',','') ?></td>
                <td class="px-4 py-3 text-center text-emerald-600"><?= number_format((float)$r->moy_max,2,',','') ?></td>
                <td class="px-4 py-3 text-center text-red-500"><?= number_format((float)$r->moy_min,2,',','') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Moyennes par matière -->
<?php if ($moyennesMatiere): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="bar-chart-horizontal" class="w-4 h-4 text-sky-600"></i>Moyennes par matière
    </div>
    <div class="p-4 relative" style="height:<?= max(200, count($moyennesMatiere) * 32) ?>px">
        <canvas id="matiereChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Progression par période -->
<?php if ($progressionPeriodes): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="trending-up" class="w-4 h-4 text-violet-600"></i>Progression par période
    </div>
    <div class="p-4 relative" style="height:240px">
        <canvas id="progressChart"></canvas>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "system-ui, sans-serif";
    var PALETTE = ['#7c3aed','#10b981','#0ea5e9','#f59e0b','#ef4444','#6366f1','#f97316','#14b8a6','#ec4899','#06b6d4','#64748b'];
    var mentColors = {'Excellent':'#10b981','Très bien':'#7c3aed','Bien':'#0ea5e9','Assez bien':'#f59e0b','Passable':'#f97316','Insuffisant':'#ef4444'};

    <?php if ($reussiteParClasse): ?>
    var rd = <?= json_encode(array_map(function($r) {
        $t = $r->total > 0 ? round($r->reussis / $r->total * 100, 1) : 0;
        return ['l'=>$r->classe,'t'=>$t];
    }, $reussiteParClasse), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('reussiteChart'), {
        type:'bar',
        data:{
            labels:rd.map(d=>d.l),
            datasets:[{ label:'Taux réussite (%)', data:rd.map(d=>d.t),
                backgroundColor:rd.map(d=>d.t>=60?'#10b981':d.t>=40?'#f59e0b':'#ef4444'), borderRadius:4 }]
        },
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true,max:100,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });
    <?php endif; ?>

    <?php if ($mentionsDistrib): ?>
    var md = <?= json_encode(array_map(fn($r) => ['l'=>$r->mention??'N/A','n'=>(int)$r->nb], $mentionsDistrib), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('mentionsChart'), {
        type:'doughnut',
        data:{
            labels:md.map(d=>d.l),
            datasets:[{ data:md.map(d=>d.n),
                backgroundColor:md.map((d,i)=>mentColors[d.l]||PALETTE[i%PALETTE.length]), borderWidth:2 }]
        },
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:'right',labels:{font:{size:11}}}} }
    });
    <?php endif; ?>

    <?php if ($moyennesMatiere): ?>
    var mat = <?= json_encode(array_map(fn($r) => ['l'=>$r->matiere,'m'=>(float)$r->moy_matiere], $moyennesMatiere), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('matiereChart'), {
        type:'bar',
        data:{
            labels:mat.map(d=>d.l),
            datasets:[{ label:'Moyenne', data:mat.map(d=>d.m),
                backgroundColor:mat.map(d=>d.m>=10?'#10b981':'#ef4444'), borderRadius:3 }]
        },
        options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{x:{beginAtZero:true,max:20,grid:{color:'#f1f5f9'}},y:{ticks:{font:{size:11}}}} }
    });
    <?php endif; ?>

    <?php if ($progressionPeriodes): ?>
    var prog = <?= json_encode(array_map(fn($r) => [
        'l'=>$r->periode,
        'm'=>(float)$r->moy_generale,
        't'=>$r->total > 0 ? round($r->reussis / $r->total * 100, 1) : 0
    ], $progressionPeriodes), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('progressChart'), {
        type:'line',
        data:{
            labels:prog.map(d=>d.l),
            datasets:[
                {label:'Moyenne générale',data:prog.map(d=>d.m),borderColor:PALETTE[0],backgroundColor:PALETTE[0]+'33',yAxisID:'y',fill:true,tension:.3,pointRadius:5},
                {label:'Taux réussite (%)',data:prog.map(d=>d.t),borderColor:PALETTE[1],backgroundColor:PALETTE[1]+'33',yAxisID:'y1',fill:false,tension:.3,pointRadius:5}
            ]
        },
        options:{responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{
                y:{beginAtZero:true,max:20,title:{display:true,text:'Moyenne'},grid:{color:'#f1f5f9'}},
                y1:{beginAtZero:true,max:100,position:'right',title:{display:true,text:'Réussite %'},grid:{drawOnChartArea:false}}
            }
        }
    });
    <?php endif; ?>
})();
</script>
