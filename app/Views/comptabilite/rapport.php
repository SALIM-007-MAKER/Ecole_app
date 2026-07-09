<?php
$anneeNum  = $anneeNum  ?? (int)date('Y');
$mois      = $mois      ?? 0;
$type      = $type      ?? 'annuel';
$paiements = $paiements ?? [];
$depenses  = $depenses  ?? [];
$catsDep   = $catsDep   ?? [];
$totRec    = $totRec    ?? 0;
$totDep    = $totDep    ?? 0;
$solde     = $solde     ?? 0;
$parPeriode= $parPeriode?? [];
$moisNoms  = $moisNoms  ?? [];

function fmtRP(float $n): string {
    return number_format($n, 2, ',', ' ') . ' FCFA';
}

$moisOptions = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$exportUrl = BASE_URL . '/comptabilite/rapport/export?annee=' . $anneeNum . ($mois ? '&mois='.$mois : '');
$printUrl  = BASE_URL . '/comptabilite/rapport/print?annee=' . $anneeNum . ($mois ? '&mois='.$mois : '');
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i data-lucide="file-bar-chart" class="w-6 h-6 text-violet-600"></i>
            Rapport financier
            <?= $mois ? $moisOptions[$mois] . ' ' : '' ?><?= $anneeNum ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5"><?= $type === 'mensuel' ? 'Rapport mensuel' : 'Rapport annuel' ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= $printUrl ?>" target="_blank" class="btn btn-outline">
            <i data-lucide="printer" class="w-4 h-4"></i>PDF
        </a>
        <a href="<?= $exportUrl ?>&type=paiements" class="btn btn-outline">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>Excel Rec.
        </a>
        <a href="<?= $exportUrl ?>&type=depenses" class="btn btn-outline">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>Excel Dep.
        </a>
        <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-outline">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/comptabilite/rapport" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-32">
            <label class="form-label">Année</label>
            <select name="annee" class="form-select">
                <?php for ($y = $anneeNum - 2; $y <= $anneeNum + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y === $anneeNum ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="flex-1 min-w-44">
            <label class="form-label">Mois (optionnel)</label>
            <select name="mois" class="form-select">
                <option value="0">— Rapport annuel —</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $mois ? 'selected' : '' ?>><?= $moisOptions[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <button class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>Afficher
            </button>
        </div>
    </form>
</div>

<!-- KPI -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-center">
        <p class="text-xs text-slate-500 mb-1">Total recettes</p>
        <p class="text-2xl font-bold text-emerald-600"><?= fmtRP((float)$totRec) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= count($paiements) ?> paiement(s)</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-center">
        <p class="text-xs text-slate-500 mb-1">Total dépenses</p>
        <p class="text-2xl font-bold text-red-600"><?= fmtRP((float)$totDep) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= count($depenses) ?> dépense(s)</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-center">
        <p class="text-xs text-slate-500 mb-1">Solde net</p>
        <p class="text-2xl font-bold <?= $solde >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
            <?= ($solde >= 0 ? '+' : '') . fmtRP((float)$solde) ?>
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Gauche : tableau par période + paiements -->
    <div class="lg:col-span-2 space-y-6">
        <?php if (!empty($parPeriode)): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-violet-600"></i>
                Détail <?= $type === 'mensuel' ? 'par jour' : 'par mois' ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Période</th>
                            <th class="px-4 py-3 text-right font-semibold text-emerald-600">Recettes</th>
                            <th class="px-4 py-3 text-right font-semibold text-red-600">Dépenses</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Solde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php
                    $cumRec = 0; $cumDep = 0;
                    foreach ($parPeriode as $periode => $vals):
                        $rec = (float)($vals['recettes'] ?? 0);
                        $dep = (float)($vals['depenses'] ?? 0);
                        $sol = $rec - $dep;
                        $cumRec += $rec; $cumDep += $dep;
                        $label = $type === 'mensuel'
                            ? date('d/m/Y', strtotime($periode))
                            : ($vals['label'] ?? $periode);
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 font-semibold text-slate-700"><?= htmlspecialchars($label, ENT_QUOTES) ?></td>
                        <td class="px-4 py-2.5 text-right text-emerald-600"><?= $rec > 0 ? fmtRP($rec) : '—' ?></td>
                        <td class="px-4 py-2.5 text-right text-red-600"><?= $dep > 0 ? fmtRP($dep) : '—' ?></td>
                        <td class="px-4 py-2.5 text-right font-bold <?= $sol >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                            <?= ($sol >= 0 ? '+' : '') . fmtRP($sol) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold">
                        <tr>
                            <td class="px-4 py-3 text-slate-700">Total</td>
                            <td class="px-4 py-3 text-right text-emerald-600"><?= fmtRP($cumRec) ?></td>
                            <td class="px-4 py-3 text-right text-red-600"><?= fmtRP($cumDep) ?></td>
                            <td class="px-4 py-3 text-right <?= ($cumRec-$cumDep)>=0?'text-emerald-600':'text-red-600' ?>">
                                <?= (($cumRec-$cumDep)>=0?'+':'') . fmtRP($cumRec-$cumDep) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Paiements reçus -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="receipt" class="w-4 h-4 text-emerald-500"></i>Paiements reçus
            </div>
            <div class="overflow-x-auto" style="max-height:350px;overflow-y:auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600">Date</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600">Élève</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600">Frais</th>
                            <th class="px-4 py-2.5 text-left font-semibold text-slate-600">Mode</th>
                            <th class="px-4 py-2.5 text-right font-semibold text-slate-600">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($paiements as $p): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 text-slate-500"><?= date('d/m', strtotime($p->date_paiement)) ?></td>
                        <td class="px-4 py-2 text-slate-700"><?= htmlspecialchars($p->eleve_nom ?? '', ENT_QUOTES) ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($p->frais_nom ?? 'Divers', ENT_QUOTES) ?></td>
                        <td class="px-4 py-2 text-slate-500"><?= ucfirst($p->mode_paiement) ?></td>
                        <td class="px-4 py-2 text-right font-bold text-emerald-600"><?= fmtRP((float)$p->montant) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($paiements)): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Aucun paiement</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Droite : dépenses par catégorie -->
    <div>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="pie-chart" class="w-4 h-4 text-red-500"></i>Dépenses par catégorie
            </div>
            <?php if (!empty($catsDep)): ?>
            <div class="p-4" style="height:220px">
                <canvas id="chartCats"></canvas>
            </div>
            <ul class="divide-y divide-slate-100">
            <?php foreach ($catsDep as $cat): ?>
            <?php if ((float)$cat->total > 0): ?>
            <li class="flex items-center justify-between px-4 py-2 text-sm">
                <span class="text-slate-700">
                    <span style="color:<?= $cat->couleur ?>">●</span>
                    <?= htmlspecialchars($cat->nom, ENT_QUOTES) ?>
                </span>
                <span class="font-bold text-red-600"><?= fmtRP((float)$cat->total) ?></span>
            </li>
            <?php endif; ?>
            <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="px-5 py-8 text-center text-slate-400 text-sm">Aucune dépense</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($catsDep)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    var cats = <?= json_encode(array_filter((array)$catsDep, fn($c) => (float)$c->total > 0)) ?>;
    var data = cats.map(function(c) { return parseFloat(c.total); });
    var labels = cats.map(function(c) { return c.nom; });
    var colors = cats.map(function(c) { return c.couleur; });
    if (data.length && Math.max.apply(null,data) > 0) {
        new Chart(document.getElementById('chartCats').getContext('2d'), {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth:2 }] },
            options: {
                responsive:true, maintainAspectRatio:false,
                plugins: { legend:{ display:false } }
            }
        });
    }
})();
</script>
<?php endif; ?>
