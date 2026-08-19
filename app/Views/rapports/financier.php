<?php
$annee          = $annee          ?? '';
$annees         = $annees         ?? [];
$kpiFinance     = $kpiFinance     ?? [];
$financeParMois = $financeParMois ?? [];
$depensesCat    = $depensesCat    ?? [];
$recouvrement   = $recouvrement   ?? [];

$f  = fn($n) => number_format((float)$n, 0, ',', ' ');
$fc = fn($n) => $f($n) . ' F';

$rec  = (float)($kpiFinance['recettes']          ?? 0);
$dep  = (float)($kpiFinance['depenses']          ?? 0);
$sol  = (float)($kpiFinance['solde']             ?? 0);
$imp  = (float)($kpiFinance['impayes']           ?? 0);
$ft   = (float)($kpiFinance['frais_total']       ?? 0);
$taux = (float)($kpiFinance['taux_recouvrement'] ?? 0);
?>

<!-- Header -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="banknote" class="w-5 h-5 text-violet-600"></i>
        </div>
        <h2 class="text-xl font-bold text-slate-800 pt-2">Statistiques financières</h2>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <form class="flex gap-2" method="GET">
            <select name="annee" class="form-select" onchange="this.form.submit()">
                <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= BASE_URL ?>/rapports/export/excel/financier?annee=<?= urlencode($annee) ?>" class="btn btn-outline text-emerald-600 border-emerald-200 hover:bg-emerald-50">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>Excel
        </a>
    </div>
</div>

<!-- Nav -->
<div class="flex items-center gap-1 mb-6 overflow-x-auto pb-1">
    <a href="<?= BASE_URL ?>/rapports" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>Dashboard
    </a>
    <a href="<?= BASE_URL ?>/rapports/scolaire" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap text-slate-600 hover:bg-slate-100 transition-colors">
        <i data-lucide="graduation-cap" class="w-4 h-4"></i>Scolaire
    </a>
    <a href="<?= BASE_URL ?>/rapports/financier" class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold whitespace-nowrap bg-violet-600 text-white">
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
    $cards = [
        ['label'=>'Recettes',            'val'=>$fc($rec), 'icon'=>'coins',          'bg'=>'bg-emerald-100', 'tc'=>'text-emerald-600'],
        ['label'=>'Dépenses',            'val'=>$fc($dep), 'icon'=>'shopping-cart',  'bg'=>'bg-red-100',     'tc'=>'text-red-600'],
        ['label'=>'Solde net',           'val'=>$fc($sol), 'icon'=>'wallet',         'bg'=>$sol>=0?'bg-violet-100':'bg-amber-100', 'tc'=>$sol>=0?'text-violet-600':'text-amber-600'],
        ['label'=>'Frais prévisionnels', 'val'=>$fc($ft),  'icon'=>'receipt',        'bg'=>'bg-sky-100',     'tc'=>'text-sky-600'],
        ['label'=>'Impayés',             'val'=>$fc($imp), 'icon'=>'alert-triangle', 'bg'=>'bg-amber-100',   'tc'=>'text-amber-600'],
        ['label'=>'Recouvrement',        'val'=>$taux.'%', 'icon'=>'trending-up',    'bg'=>$taux>=80?'bg-emerald-100':'bg-red-100', 'tc'=>$taux>=80?'text-emerald-600':'text-red-600'],
    ];
    foreach ($cards as $c): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center">
        <div class="mx-auto mb-2 w-10 h-10 rounded-full <?= $c['bg'] ?> flex items-center justify-center">
            <i data-lucide="<?= $c['icon'] ?>" class="w-5 h-5 <?= $c['tc'] ?>"></i>
        </div>
        <p class="text-lg font-bold text-slate-800 leading-tight"><?= $c['val'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5"><?= $c['label'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Chart Recettes vs Dépenses (pleine largeur) -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="trending-up" class="w-4 h-4 text-emerald-600"></i>Recettes vs Dépenses — mensuel
    </div>
    <div class="p-4 relative" style="height:280px">
        <canvas id="financeChart"></canvas>
    </div>
</div>

<!-- Dépenses par catégorie : donut + liste -->
<?php if ($depensesCat):
    $totalDep = array_sum(array_map(fn($r) => (float)$r->total, $depensesCat));
    $catPalette = ['#7c3aed','#10b981','#0ea5e9','#f59e0b','#ef4444','#6366f1','#f97316','#14b8a6','#ec4899','#06b6d4','#64748b'];
?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6 overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="pie-chart" class="w-4 h-4 text-amber-500"></i>Dépenses par catégorie
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100">
        <!-- Donut -->
        <div class="flex items-center justify-center p-4" style="height:280px">
            <canvas id="depCatChart"></canvas>
        </div>
        <!-- Liste catégories -->
        <div class="p-4 overflow-y-auto" style="max-height:280px">
            <?php foreach ($depensesCat as $i => $cat):
                $pct = $totalDep > 0 ? round((float)$cat->total / $totalDep * 100, 1) : 0;
                $color = $cat->couleur ?? $catPalette[$i % count($catPalette)];
            ?>
            <div class="flex items-center gap-3 py-2 <?= $i < count($depensesCat) - 1 ? 'border-b border-slate-50' : '' ?>">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:<?= htmlspecialchars($color, ENT_QUOTES) ?>"></span>
                <span class="text-sm text-slate-700 flex-1 min-w-0 truncate" title="<?= htmlspecialchars($cat->nom, ENT_QUOTES) ?>">
                    <?= htmlspecialchars($cat->nom, ENT_QUOTES) ?>
                </span>
                <span class="text-sm font-semibold text-slate-800 whitespace-nowrap">
                    <?= number_format((float)$cat->total, 0, ',', ' ') ?> F
                </span>
                <span class="text-xs font-medium text-slate-400 w-10 text-right flex-shrink-0"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
            <?php if ($totalDep > 0): ?>
            <div class="flex items-center gap-3 pt-2 mt-1 border-t border-slate-200 font-semibold text-sm">
                <span class="w-3 h-3 flex-shrink-0"></span>
                <span class="flex-1 text-slate-600">Total dépenses</span>
                <span class="text-slate-800 whitespace-nowrap"><?= number_format($totalDep, 0, ',', ' ') ?> F</span>
                <span class="w-10 text-right flex-shrink-0 text-slate-400">100%</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-6">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="pie-chart" class="w-4 h-4 text-amber-500"></i>Dépenses par catégorie
    </div>
    <div class="flex flex-col items-center justify-center p-10 text-slate-400">
        <i data-lucide="info" class="w-10 h-10 mb-2 opacity-40"></i>
        <p class="text-sm">Aucune dépense enregistrée</p>
    </div>
</div>
<?php endif; ?>

<!-- Tableau recouvrement -->
<?php if ($recouvrement): ?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
        <i data-lucide="percent" class="w-4 h-4 text-violet-600"></i>Taux de recouvrement par type de frais
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Type de frais</th>
                    <th class="px-4 py-3 text-right font-semibold">Élèves</th>
                    <th class="px-4 py-3 text-right font-semibold">À collecter</th>
                    <th class="px-4 py-3 text-right font-semibold">Collecté</th>
                    <th class="px-4 py-3 text-left font-semibold" style="min-width:160px">Progression</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($recouvrement as $r):
                $mtotal = (float)$r->montant_total;
                $mpaye  = (float)$r->montant_paye;
                $t2     = $mtotal > 0 ? round($mpaye / $mtotal * 100) : 0;
                $barBg  = $t2 >= 80 ? 'bg-emerald-500' : ($t2 >= 50 ? 'bg-amber-400' : 'bg-red-500');
                $barTc  = $t2 >= 80 ? 'text-emerald-600' : ($t2 >= 50 ? 'text-amber-600' : 'text-red-600');
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-semibold text-slate-800"><?= htmlspecialchars($r->nom, ENT_QUOTES) ?></td>
                <td class="px-4 py-3 text-right text-slate-600"><?= (int)$r->nb_eleves ?></td>
                <td class="px-4 py-3 text-right text-slate-600"><?= $fc($mtotal) ?></td>
                <td class="px-4 py-3 text-right font-semibold <?= $t2 < 100 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $fc($mpaye) ?></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full <?= $barBg ?>" style="width:<?= $t2 ?>%"></div>
                        </div>
                        <span class="text-xs font-bold <?= $barTc ?> w-9 text-right"><?= $t2 ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold text-sm">
                <tr>
                    <td class="px-4 py-3 text-slate-600" colspan="2">Total</td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $fc(array_sum(array_column((array)$recouvrement,'montant_total'))) ?></td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $fc(array_sum(array_column((array)$recouvrement,'montant_paye'))) ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $taux >= 80 ? 'bg-emerald-100 text-emerald-700' : ($taux >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') ?>">
                            <?= $taux ?>% global
                        </span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    Chart.defaults.font.family = "system-ui, sans-serif";
    var PALETTE = ['#7c3aed','#10b981','#0ea5e9','#f59e0b','#ef4444','#6366f1','#f97316','#14b8a6','#ec4899','#06b6d4','#64748b'];

    var fd = <?= json_encode(array_map(fn($r) => ['l'=>$r['label'],'rec'=>$r['recettes'],'dep'=>$r['depenses']], $financeParMois), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: fd.map(d=>d.l),
            datasets: [
                { label:'Recettes', data:fd.map(d=>d.rec), backgroundColor:'#10b981', borderRadius:3 },
                { label:'Dépenses', data:fd.map(d=>d.dep), backgroundColor:'#ef4444', borderRadius:3 }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'top'}},
            scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'}},x:{grid:{display:false}}} }
    });

    <?php if ($depensesCat): ?>
    var dc = <?= json_encode(array_map(fn($r) => ['l'=>$r->nom,'v'=>(float)$r->total,'c'=>$r->couleur??null], $depensesCat), JSON_THROW_ON_ERROR) ?>;
    new Chart(document.getElementById('depCatChart'), {
        type: 'doughnut',
        data: {
            labels: dc.map(d=>d.l),
            datasets: [{ data:dc.map(d=>d.v),
                backgroundColor:dc.map((d,i)=>d.c||PALETTE[i%PALETTE.length]),
                borderWidth:2, hoverOffset:6 }]
        },
        options: { responsive:true, maintainAspectRatio:false,
            plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){
                var total=c.dataset.data.reduce((a,b)=>a+b,0);
                return c.label+' : '+parseFloat(c.parsed).toLocaleString('fr-FR')+' F ('+(total?Math.round(c.parsed/total*100):0)+'%)';
            }}}}
        }
    });
    <?php endif; ?>
})();
</script>
