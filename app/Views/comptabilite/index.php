<?php
$annee         = $annee         ?? '';
$anneesOptions = $anneesOptions ?? [];
$statsPai      = $statsPai      ?? [];
$statsDep      = $statsDep      ?? [];
$statsRec      = $statsRec      ?? [];
$totalRecettes = $totalRecettes ?? 0;
$totalDepenses = $totalDepenses ?? 0;
$soldeNet      = $soldeNet      ?? 0;
$chartBar      = $chartBar      ?? ['labels'=>[],'recettes'=>[],'depenses'=>[]];
$chartDep      = $chartDep      ?? ['labels'=>[],'data'=>[],'colors'=>[]];
$recentPaiements = $recentPaiements ?? [];
$recentDepenses  = $recentDepenses  ?? [];

$currentUser = \Core\Session::getUser();
$perms = $currentUser['permissions'] ?? [];

function fmtFCFA(float $n): string {
    return number_format($n, 2, ',', ' ') . ' FCFA';
}

$tauxRec = 0;
if (!empty($statsRec['total_attendu']) && (float)$statsRec['total_attendu'] > 0) {
    $tauxRec = round((float)($statsRec['total_encaisse'] ?? 0) / (float)$statsRec['total_attendu'] * 100, 1);
}
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="banknote" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-800">Finance — Tableau de bord</h2>
            <p class="text-sm text-slate-500 mt-0.5">Année scolaire <?= htmlspecialchars($annee, ENT_QUOTES) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <form method="GET" class="flex gap-2">
            <select name="annee" class="form-select py-1.5 text-sm" onchange="this.form.submit()" style="min-width:130px">
                <?php foreach ($anneesOptions as $a): ?>
                <option value="<?= $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if (in_array('comptabilite.create', $perms, true)): ?>
        <a href="<?= BASE_URL ?>/paiements/create" class="btn btn-primary">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>Paiement
        </a>
        <a href="<?= BASE_URL ?>/depenses/create" class="btn btn-outline">
            <i data-lucide="minus-circle" class="w-4 h-4"></i>Dépense
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-slate-500 mb-1">Recettes totales</p>
                <p class="text-lg font-bold text-emerald-600"><?= fmtFCFA($totalRecettes) ?></p>
                <p class="text-xs text-slate-400 mt-0.5">Auj.: <?= fmtFCFA((float)($statsPai['total_aujourd_hui'] ?? 0)) ?></p>
            </div>
            <div class="w-11 h-11 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="trending-up" class="w-5 h-5 text-emerald-600"></i>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-slate-500 mb-1">Dépenses totales</p>
                <p class="text-lg font-bold text-red-600"><?= fmtFCFA($totalDepenses) ?></p>
                <p class="text-xs text-slate-400 mt-0.5">Auj.: <?= fmtFCFA((float)($statsDep['total_aujourd_hui'] ?? 0)) ?></p>
            </div>
            <div class="w-11 h-11 rounded-full bg-red-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="trending-down" class="w-5 h-5 text-red-600"></i>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-slate-500 mb-1">Solde net</p>
                <p class="text-lg font-bold <?= $soldeNet >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                    <?= ($soldeNet >= 0 ? '+' : '') . fmtFCFA($soldeNet) ?>
                </p>
                <p class="text-xs text-slate-400 mt-0.5">Ce mois</p>
            </div>
            <div class="w-11 h-11 rounded-full bg-violet-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="scale" class="w-5 h-5 text-violet-600"></i>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-slate-500 mb-1">Taux de recouvrement</p>
                <p class="text-lg font-bold text-sky-600"><?= $tauxRec ?>%</p>
                <p class="text-xs text-slate-400 mt-0.5">
                    <?= (int)($statsRec['nb_payes'] ?? 0) ?>/<?= (int)($statsRec['nb_total'] ?? 0) ?> frais payés
                </p>
            </div>
            <div class="w-11 h-11 rounded-full bg-sky-50 flex items-center justify-center flex-shrink-0">
                <i data-lucide="pie-chart" class="w-5 h-5 text-sky-600"></i>
            </div>
        </div>
        <div class="mt-3 w-full bg-slate-200 rounded-full h-1.5">
            <div class="bg-sky-500 h-1.5 rounded-full" style="width:<?= min(100,$tauxRec) ?>%"></div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <span class="font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-600"></i>
                Recettes vs Dépenses — <?= $annee ?>
            </span>
            <a href="<?= BASE_URL ?>/comptabilite/rapport?annee=<?= $anneeNum ?? date('Y') ?>"
               class="btn btn-outline py-1.5 px-3 text-xs">
                <i data-lucide="file-text" class="w-3.5 h-3.5"></i>Rapport
            </a>
        </div>
        <div class="p-4" style="height:300px">
            <canvas id="chartBar"></canvas>
        </div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <span class="font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="pie-chart" class="w-4 h-4 text-red-500"></i>Dépenses par catégorie
            </span>
        </div>
        <div class="p-4" style="height:300px">
            <?php if (empty($chartDep['data']) || max($chartDep['data']) == 0): ?>
            <div class="flex items-center justify-center h-full text-sm text-slate-400">Aucune dépense enregistrée</div>
            <?php else: ?>
            <canvas id="chartDep"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Raccourcis -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <a href="<?= BASE_URL ?>/paiements" class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center no-underline hover:shadow-md transition-shadow block">
        <i data-lucide="receipt" class="w-8 h-8 text-emerald-500 mx-auto mb-1"></i>
        <p class="text-xs font-semibold text-slate-600 mb-1">Paiements</p>
        <p class="text-2xl font-bold text-emerald-600"><?= (int)($statsPai['nb'] ?? 0) ?></p>
    </a>
    <a href="<?= BASE_URL ?>/depenses" class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center no-underline hover:shadow-md transition-shadow block">
        <i data-lucide="credit-card" class="w-8 h-8 text-red-500 mx-auto mb-1"></i>
        <p class="text-xs font-semibold text-slate-600 mb-1">Dépenses</p>
        <p class="text-2xl font-bold text-red-600"><?= (int)($statsDep['nb'] ?? 0) ?></p>
    </a>
    <a href="<?= BASE_URL ?>/comptabilite/impayes?annee=<?= urlencode($annee) ?>"
       class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center no-underline hover:shadow-md transition-shadow block">
        <i data-lucide="alert-circle" class="w-8 h-8 text-amber-500 mx-auto mb-1"></i>
        <p class="text-xs font-semibold text-slate-600 mb-1">Impayés</p>
        <p class="text-2xl font-bold text-amber-600"><?= (int)($statsRec['nb_impayer'] ?? 0) ?></p>
    </a>
    <a href="<?= BASE_URL ?>/comptabilite/caisse"
       class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 text-center no-underline hover:shadow-md transition-shadow block">
        <i data-lucide="vault" class="w-8 h-8 text-violet-500 mx-auto mb-1"></i>
        <p class="text-xs font-semibold text-slate-600 mb-1">Caisse du jour</p>
        <p class="text-xl font-bold text-violet-600"><?= fmtFCFA((float)($statsPai['total_aujourd_hui'] ?? 0)) ?></p>
    </a>
</div>

<!-- Dernières transactions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <span class="font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="receipt" class="w-4 h-4 text-emerald-500"></i>Derniers paiements
            </span>
            <a href="<?= BASE_URL ?>/paiements" class="btn btn-outline py-1 px-2.5 text-xs">Tous</a>
        </div>
        <ul class="divide-y divide-slate-100">
        <?php if (empty($recentPaiements)): ?>
        <li class="px-5 py-4 text-sm text-center text-slate-400">Aucun paiement</li>
        <?php else: ?>
        <?php foreach ($recentPaiements as $p): ?>
        <li class="flex items-center justify-between px-5 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($p->eleve_nom ?? '', ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400">
                    <?= date('d/m/Y', strtotime($p->date_paiement)) ?>
                    · <?= htmlspecialchars($p->frais_nom ?? 'Divers', ENT_QUOTES) ?>
                </p>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-700">
                <?= fmtFCFA((float)$p->montant) ?>
            </span>
        </li>
        <?php endforeach; ?>
        <?php endif; ?>
        </ul>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <span class="font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="minus-circle" class="w-4 h-4 text-red-500"></i>Dernières dépenses
            </span>
            <a href="<?= BASE_URL ?>/depenses" class="btn btn-outline py-1 px-2.5 text-xs">Toutes</a>
        </div>
        <ul class="divide-y divide-slate-100">
        <?php if (empty($recentDepenses)): ?>
        <li class="px-5 py-4 text-sm text-center text-slate-400">Aucune dépense</li>
        <?php else: ?>
        <?php foreach ($recentDepenses as $d): ?>
        <li class="flex items-center justify-between px-5 py-3">
            <div>
                <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($d->libelle, ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400">
                    <?= date('d/m/Y', strtotime($d->date_depense)) ?>
                    · <span style="color:<?= $d->categorie_couleur ?>">●</span>
                    <?= htmlspecialchars($d->categorie_nom ?? 'Divers', ENT_QUOTES) ?>
                </p>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-red-100 text-red-700">
                <?= fmtFCFA((float)$d->montant) ?>
            </span>
        </li>
        <?php endforeach; ?>
        <?php endif; ?>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function() {
    var barData = <?= json_encode($chartBar, JSON_UNESCAPED_UNICODE) ?>;
    var depData = <?= json_encode($chartDep, JSON_UNESCAPED_UNICODE) ?>;

    new Chart(document.getElementById('chartBar').getContext('2d'), {
        type: 'bar',
        data: {
            labels: barData.labels,
            datasets: [
                { label: 'Recettes', data: barData.recettes, backgroundColor: 'rgba(16,185,129,.7)',  borderColor:'#10b981', borderWidth:1, borderRadius:4 },
                { label: 'Dépenses', data: barData.depenses, backgroundColor: 'rgba(239,68,68,.65)', borderColor:'#ef4444', borderWidth:1, borderRadius:4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position:'top' } },
            scales: {
                y: { beginAtZero:true, ticks: { callback: function(v){ return v.toLocaleString('fr-FR') + ' F'; } } }
            }
        }
    });

    var ctxDep = document.getElementById('chartDep');
    if (ctxDep && depData.data.length > 0) {
        new Chart(ctxDep.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: depData.labels,
                datasets: [{ data: depData.data, backgroundColor: depData.colors, borderWidth: 2 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels:{ font:{size:11} } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ' : ' + ctx.parsed.toLocaleString('fr-FR') + ' FCFA';
                            }
                        }
                    }
                }
            }
        });
    }
})();
</script>
