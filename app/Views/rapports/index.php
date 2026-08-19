<?php
$annee             = $annee             ?? '';
$annees            = $annees            ?? [];
$periodes          = $periodes          ?? [];
$periodeId         = $periodeId         ?? null;
$kpiGlobal         = $kpiGlobal         ?? [];
$kpiFinance        = $kpiFinance        ?? [];
$kpiReussite       = $kpiReussite       ?? [];
$elevesParClasse   = $elevesParClasse   ?? [];
$absencesHebdo     = $absencesHebdo     ?? [];
$financeParMois    = $financeParMois    ?? [];
$reussiteParClasse = $reussiteParClasse ?? [];
$mentionsDistrib   = $mentionsDistrib   ?? [];

$f = fn($n) => number_format((float)$n, 0, ',', ' ');
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-800">Dashboard analytique</h2>
    </div>
    <form class="flex items-center gap-2 flex-wrap" method="GET">
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
        <a href="<?= BASE_URL ?>/rapports/export/pdf?annee=<?= urlencode($annee) ?>&periode_id=<?= (int)$periodeId ?>"
           class="btn btn-outline text-red-600 border-red-200 hover:bg-red-50" target="_blank">
            <i data-lucide="file-text" class="w-4 h-4"></i>PDF
        </a>
    </form>
</div>

<!-- Nav -->
<div class="flex items-center gap-1 mb-6 overflow-x-auto pb-1">
    <a href="<?= BASE_URL ?>/rapports" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-violet-600 text-white">
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
    <a href="<?= BASE_URL ?>/rapports/reussite" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="trophy" class="w-4 h-4"></i>Réussite
    </a>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <?php
    $kpis = [
        ['label'=>'Élèves actifs',     'val'=>$f($kpiGlobal['eleves_actifs'] ?? 0),       'icon'=>'users',       'bg'=>'bg-violet-100',  'tc'=>'text-violet-600'],
        ['label'=>'Classes',           'val'=>$f($kpiGlobal['classes'] ?? 0),              'icon'=>'building-2',  'bg'=>'bg-sky-100',     'tc'=>'text-sky-600'],
        ['label'=>'Enseignants',       'val'=>$f($kpiGlobal['professeurs'] ?? 0),          'icon'=>'user-cog',    'bg'=>'bg-indigo-100',  'tc'=>'text-indigo-600'],
        ['label'=>'Taux de réussite',  'val'=>($kpiReussite['taux_reussite'] ?? 0).'%',   'icon'=>'trophy',      'bg'=>'bg-emerald-100', 'tc'=>'text-emerald-600'],
        ['label'=>'Recettes '.$annee,  'val'=>$f($kpiFinance['recettes'] ?? 0).' F',      'icon'=>'coins',       'bg'=>'bg-amber-100',   'tc'=>'text-amber-600'],
        ['label'=>'Recouvrement',      'val'=>($kpiFinance['taux_recouvrement'] ?? 0).'%','icon'=>'trending-up', 'bg'=>'bg-red-100',     'tc'=>'text-red-600'],
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

<!-- Charts row 1 -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="bar-chart-horizontal" class="w-4 h-4 text-violet-600"></i>Élèves par classe
        </div>
        <div class="p-4 relative" style="height:280px">
            <canvas id="elevesChart"></canvas>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="pie-chart" class="w-4 h-4 text-violet-600"></i>Garçons / Filles
        </div>
        <div class="p-4 flex items-center justify-center" style="height:280px">
            <canvas id="sexeChart"></canvas>
        </div>
    </div>
</div>

<!-- Charts row 2 -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="calendar-x" class="w-4 h-4 text-amber-500"></i>Absences — 12 dernières semaines
        </div>
        <div class="p-4 relative" style="height:260px">
            <canvas id="absencesChart"></canvas>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="trending-up" class="w-4 h-4 text-emerald-600"></i>Recettes vs Dépenses (<?= $annee ?>)
        </div>
        <div class="p-4 relative" style="height:260px">
            <canvas id="financeChart"></canvas>
        </div>
    </div>
</div>

<!-- Charts row 3 -->
<div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
    <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>Taux de réussite par classe
        </div>
        <div class="p-4 relative" style="height:260px">
            <canvas id="reussiteChart"></canvas>
        </div>
    </div>
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
            <i data-lucide="award" class="w-4 h-4 text-violet-600"></i>Répartition des mentions
        </div>
        <div class="p-4 flex items-center justify-center" style="height:260px">
            <canvas id="mentionsChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "system-ui, sans-serif";

    var PALETTE = ['#7c3aed','#10b981','#0ea5e9','#f59e0b','#ef4444','#6366f1',
                   '#f97316','#14b8a6','#ec4899','#06b6d4','#64748b','#1e293b'];

    var elevesData = <?= json_encode(array_map(fn($r) => ['label'=>$r->nom.' ('.$r->niveau.')', 'n'=>(int)$r->nb_eleves], $elevesParClasse), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('elevesChart'), {
        type: 'bar',
        data: {
            labels: elevesData.map(d=>d.label),
            datasets: [{ label:'Élèves', data:elevesData.map(d=>d.n), backgroundColor:PALETTE[0], borderRadius:4 }]
        },
        options: { indexAxis:'y', responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{x:{beginAtZero:true,grid:{color:'#f1f5f9'}},y:{ticks:{font:{size:11}}}} }
    });

    new Chart(document.getElementById('sexeChart'), {
        type: 'doughnut',
        data: {
            labels: ['Garçons','Filles'],
            datasets: [{ data:[<?= (int)($kpiGlobal['garcons'] ?? 0) ?>,<?= (int)($kpiGlobal['filles'] ?? 0) ?>],
                backgroundColor:[PALETTE[0],'#f472b6'], borderWidth:2 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'bottom'},tooltip:{callbacks:{label:function(c){
                var t=c.dataset.data.reduce((a,b)=>a+b,0);
                return c.label+': '+c.parsed+' ('+(t?Math.round(c.parsed/t*100):0)+'%)';
            }}}} }
    });

    var absData = <?= json_encode(array_map(fn($r) => ['label'=>$r->label,'abs'=>(int)$r->absences,'ret'=>(int)$r->retards], $absencesHebdo), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('absencesChart'), {
        type: 'line',
        data: {
            labels: absData.map(d=>d.label),
            datasets: [
                { label:'Absences', data:absData.map(d=>d.abs), borderColor:PALETTE[4], backgroundColor:PALETTE[4]+'33', fill:true, tension:.3, pointRadius:3 },
                { label:'Retards',  data:absData.map(d=>d.ret), borderColor:PALETTE[2], backgroundColor:PALETTE[2]+'33', fill:true, tension:.3, pointRadius:3 }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });

    var finData = <?= json_encode(array_map(fn($r) => ['label'=>$r['label'],'rec'=>$r['recettes'],'dep'=>$r['depenses']], $financeParMois), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: finData.map(d=>d.label),
            datasets: [
                { label:'Recettes', data:finData.map(d=>d.rec), backgroundColor:PALETTE[1], borderRadius:3 },
                { label:'Dépenses', data:finData.map(d=>d.dep), backgroundColor:PALETTE[4], borderRadius:3 }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });

    var reussData = <?= json_encode(array_map(function($r) {
        $taux = $r->total > 0 ? round($r->reussis / $r->total * 100, 1) : 0;
        return ['label'=>$r->classe, 'taux'=>$taux];
    }, $reussiteParClasse), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('reussiteChart'), {
        type: 'bar',
        data: {
            labels: reussData.map(d=>d.label),
            datasets: [{ label:'Taux réussite (%)', data:reussData.map(d=>d.taux),
                backgroundColor:reussData.map(d=>d.taux>=50?'#10b981':'#ef4444'), borderRadius:4 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false}},
            scales:{y:{beginAtZero:true,max:100,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });

    var mentData = <?= json_encode(array_map(fn($r) => ['label'=>$r->mention??'N/A','n'=>(int)$r->nb], $mentionsDistrib), JSON_THROW_ON_ERROR) ?>;
    var mentColors = {'Excellent':'#10b981','Très bien':'#7c3aed','Bien':'#0ea5e9','Assez bien':'#f59e0b','Passable':'#f97316','Insuffisant':'#ef4444'};
    new Chart(document.getElementById('mentionsChart'), {
        type: 'doughnut',
        data: {
            labels: mentData.map(d=>d.label),
            datasets: [{ data:mentData.map(d=>d.n),
                backgroundColor:mentData.map((d,i)=>mentColors[d.label]||PALETTE[i%PALETTE.length]),
                borderWidth:2 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'right',labels:{font:{size:11}}}} }
    });
})();
</script>
