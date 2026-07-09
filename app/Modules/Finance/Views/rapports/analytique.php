<?php
/**
 * @var array  $comparatifAnnuel
 * @var array  $comparatifClasse
 * @var array  $projectionMens
 * @var array  $evolution
 * @var array  $statsParMode
 * @var int    $annee
 * @var array  $annees
 * @var string $anneeSco
 * @var array  $anneesSco
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Rapport analytique — Finance V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#7c3aed'}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen">
<?php include BASE_PATH . '/app/Modules/Finance/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="/v2/finance/rapports" class="hover:text-violet-600">Rapports</a> / Analytique
            </nav>
            <h1 class="text-2xl font-bold text-slate-800">Rapport analytique</h1>
        </div>
        <form method="GET" class="flex gap-2 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Année</label>
                <select name="annee" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $annee == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
                <select name="annee_scolaire" class="border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($anneesSco as $as): ?>
                    <option value="<?= $as ?>" <?= $anneeSco === $as ? 'selected' : '' ?>><?= $as ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Actualiser</button>
            <a href="/v2/finance/rapports/export?type=analytique&format=excel&<?= http_build_query($_GET) ?>"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="table" class="w-4 h-4"></i> Excel
            </a>
        </form>
    </div>

    <!-- Comparatif annuel -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-slate-700 mb-4">Comparatif annuel (<?= implode(', ', $annees) ?>)</h2>
        <canvas id="chartComparatif" height="120"></canvas>
    </div>

    <!-- Évolution mensuelle + Projection -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <h2 class="font-semibold text-slate-700 mb-4">Évolution mensuelle <?= $annee ?></h2>
            <canvas id="chartEvolution" height="200"></canvas>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <h2 class="font-semibold text-slate-700 mb-4">Tendance & projection <?= $annee ?></h2>
            <canvas id="chartProjection" height="200"></canvas>
        </div>
    </div>

    <!-- Par classe -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-slate-700 mb-4">Recouvrement par classe — <?= htmlspecialchars($anneeSco) ?></h2>
        <?php if (empty($comparatifClasse)): ?>
            <p class="text-slate-400 text-sm text-center py-6">Aucune donnée pour cette année scolaire</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Classe</th>
                    <th class="px-4 py-3 text-left">Niveau</th>
                    <th class="px-4 py-3 text-right">Élèves</th>
                    <th class="px-4 py-3 text-right">Total émis</th>
                    <th class="px-4 py-3 text-right">Payé</th>
                    <th class="px-4 py-3 text-right">Restant</th>
                    <th class="px-4 py-3 text-left">Taux</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($comparatifClasse as $cl): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($cl->classe_nom) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($cl->niveau) ?></td>
                    <td class="px-4 py-3 text-right text-slate-600"><?= (int)$cl->nb_eleves ?></td>
                    <td class="px-4 py-3 text-right text-slate-700"><?= $fmt((float)$cl->montant_total) ?></td>
                    <td class="px-4 py-3 text-right text-emerald-600"><?= $fmt((float)$cl->montant_paye) ?></td>
                    <td class="px-4 py-3 text-right text-red-600"><?= $fmt((float)$cl->montant_restant) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-slate-100 rounded-full h-2">
                                <div class="bg-violet-500 h-2 rounded-full" style="width:<?= min(100,$cl->taux_paiement) ?>%"></div>
                            </div>
                            <span class="text-xs font-medium text-slate-600 w-10"><?= $cl->taux_paiement ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
lucide.createIcons();

const annees      = <?= json_encode(array_column($comparatifAnnuel, 'annee')) ?>;
const recettes    = <?= json_encode(array_column($comparatifAnnuel, 'recettes')) ?>;
const evoLabels   = <?= json_encode(array_column($evolution, 'mois_label')) ?>;
const evoData     = <?= json_encode(array_column($evolution, 'recettes')) ?>;
const projLabels  = <?= json_encode(array_column($projectionMens, 'mois')) ?>;
const projReelles = <?= json_encode(array_column($projectionMens, 'recettes_reelles')) ?>;
const projTendance= <?= json_encode(array_column($projectionMens, 'tendance')) ?>;

new Chart(document.getElementById('chartComparatif'), {
    type: 'bar',
    data: {
        labels: annees,
        datasets: [{
            label: 'Recettes (XOF)',
            data: recettes.map(v => parseFloat(v)),
            backgroundColor: ['rgba(124,58,237,0.6)','rgba(59,130,246,0.6)','rgba(16,185,129,0.6)'],
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { ticks: { callback: v => v.toLocaleString('fr') } } }
    }
});

if (evoData.length > 0) {
    new Chart(document.getElementById('chartEvolution'), {
        type: 'line',
        data: {
            labels: evoLabels,
            datasets: [{
                label: 'Recettes',
                data: evoData.map(v => parseFloat(v)),
                borderColor: 'rgb(124,58,237)',
                backgroundColor: 'rgba(124,58,237,0.1)',
                fill: true,
                tension: 0.4,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
}

if (projReelles.length > 0) {
    new Chart(document.getElementById('chartProjection'), {
        type: 'line',
        data: {
            labels: projLabels.map(m => m + '/' + <?= $annee ?>),
            datasets: [
                { label: 'Réalisé', data: projReelles.map(v => parseFloat(v)), borderColor: 'rgb(16,185,129)', tension: 0.4 },
                { label: 'Tendance', data: projTendance.map(v => parseFloat(v)), borderColor: 'rgb(245,158,11)', borderDash: [5,5], tension: 0.4 },
            ]
        },
        options: { responsive: true }
    });
}
</script>
</body>
</html>
