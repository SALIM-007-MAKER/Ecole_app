<?php
/**
 * @var object $stats
 * @var array  $evolution
 * @var array  $topDebiteurs
 * @var array  $statsParMode
 * @var array  $anneesSco
 * @var object|null $statsComptable
 * @var string $date
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Tableau de bord financier — Finance V2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#7c3aed'}}}}</script>
</head>
<body class="bg-slate-50 min-h-screen">
<?php include BASE_PATH . '/app/Modules/Finance/Views/partials/sidebar.php'; ?>

<main class="ml-64 p-8">
    <!-- En-tête + filtre date -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <nav class="text-sm text-slate-400 mb-1">
                <a href="/v2/finance/rapports" class="hover:text-violet-600">Rapports</a> /
                <span class="text-slate-700">Tableau de bord</span>
            </nav>
            <h1 class="text-3xl font-bold text-slate-800">Tableau de bord financier</h1>
        </div>
        <div class="flex gap-2">
            <form method="GET" class="flex gap-2">
                <input type="date" name="date_fin" value="<?= htmlspecialchars($date) ?>"
                       class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300">
                <button class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">Actualiser</button>
            </form>
            <a href="/v2/finance/rapports/export?type=dashboard&format=pdf"
               class="border border-slate-200 bg-white text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 flex items-center gap-2">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimer
            </a>
        </div>
    </div>

    <!-- KPIs principaux -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $kpis = [
            ['label'=>"Recettes aujourd'hui",'value'=>$fmt($stats->recettes_jour),'sub'=>$stats->nb_paiements_jour.' paiements','color'=>'emerald','icon'=>'trending-up'],
            ['label'=>'Recettes ce mois','value'=>$fmt($stats->recettes_mois),'sub'=>$stats->nb_paiements_mois.' paiements','color'=>'blue','icon'=>'calendar'],
            ['label'=>'Impayés total','value'=>$fmt($stats->impayes_total),'sub'=>$stats->impayes_nb.' factures','color'=>'amber','icon'=>'alert-triangle'],
            ['label'=>'Trésorerie caisse','value'=>$fmt($stats->tresorerie_caisse),'sub'=>'sessions actives','color'=>'violet','icon'=>'archive'],
        ];
        $colorMap2 = [
            'emerald'=>['bg'=>'bg-emerald-50','border'=>'border-emerald-200','icon'=>'text-emerald-600','val'=>'text-emerald-700'],
            'blue'   =>['bg'=>'bg-blue-50','border'=>'border-blue-200','icon'=>'text-blue-600','val'=>'text-blue-700'],
            'amber'  =>['bg'=>'bg-amber-50','border'=>'border-amber-200','icon'=>'text-amber-600','val'=>'text-amber-700'],
            'violet' =>['bg'=>'bg-violet-50','border'=>'border-violet-200','icon'=>'text-violet-600','val'=>'text-violet-700'],
        ];
        foreach ($kpis as $k):
            $c = $colorMap2[$k['color']];
        ?>
        <div class="bg-white rounded-xl border <?= $c['border'] ?> p-5 <?= $c['bg'] ?>">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-slate-600"><?= $k['label'] ?></span>
                <i data-lucide="<?= $k['icon'] ?>" class="w-5 h-5 <?= $c['icon'] ?>"></i>
            </div>
            <div class="text-2xl font-bold <?= $c['val'] ?> mb-1"><?= $k['value'] ?></div>
            <div class="text-xs text-slate-500"><?= $k['sub'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Taux de recouvrement -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold text-slate-700">Taux de recouvrement global</h2>
            <span class="text-2xl font-bold text-violet-700"><?= $stats->taux_recouvrement ?>%</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3">
            <div class="bg-violet-500 h-3 rounded-full transition-all"
                 style="width:<?= min(100,$stats->taux_recouvrement) ?>%"></div>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-4 text-sm text-slate-600">
            <div>Émis : <strong><?= $fmt($stats->impayes_total + $stats->recettes_annee) ?></strong></div>
            <div>Encaissé : <strong class="text-emerald-600"><?= $fmt($stats->recettes_annee) ?></strong></div>
            <div>Restant : <strong class="text-amber-600"><?= $fmt($stats->impayes_total) ?></strong></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Évolution mensuelle -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h2 class="font-semibold text-slate-700 mb-4">Évolution mensuelle <?= $filters->annee ?: date('Y') ?></h2>
            <canvas id="chartEvolution" height="200"></canvas>
        </div>

        <!-- Répartition par mode de paiement -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h2 class="font-semibold text-slate-700 mb-4">Répartition par mode de paiement</h2>
            <?php if (empty($statsParMode)): ?>
                <p class="text-slate-400 text-sm text-center py-10">Aucune donnée</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php
                    $totalModes = array_sum(array_column($statsParMode, 'total'));
                    foreach ($statsParMode as $m):
                        $pct = $totalModes > 0 ? round($m->total / $totalModes * 100) : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-slate-700"><?= htmlspecialchars($m->mode_nom) ?> <span class="text-slate-400 text-xs">(<?= $m->nb ?>)</span></span>
                            <span class="text-slate-600"><?= $fmt((float)$m->total) ?> — <?= $pct ?>%</span>
                        </div>
                        <div class="bg-slate-100 rounded-full h-2">
                            <div class="bg-violet-400 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top débiteurs -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-700">Top 5 débiteurs</h2>
            <a href="/v2/finance/rapports/impayes" class="text-violet-600 text-sm hover:underline">Voir tous les impayés →</a>
        </div>
        <?php if (empty($topDebiteurs)): ?>
            <p class="text-slate-400 text-sm text-center py-6">Aucun impayé</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-slate-400 text-left border-b border-slate-100">
                    <th class="pb-2 font-medium">Élève</th>
                    <th class="pb-2 font-medium">Matricule</th>
                    <th class="pb-2 font-medium">Classe</th>
                    <th class="pb-2 font-medium text-right">Montant dû</th>
                    <th class="pb-2 font-medium text-right">Factures</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                <?php foreach ($topDebiteurs as $d): ?>
                <tr class="hover:bg-slate-50">
                    <td class="py-2 font-medium text-slate-800"><?= htmlspecialchars($d->nom . ' ' . $d->prenom) ?></td>
                    <td class="py-2 text-slate-500"><?= htmlspecialchars($d->matricule) ?></td>
                    <td class="py-2 text-slate-500"><?= htmlspecialchars($d->classe_nom) ?></td>
                    <td class="py-2 text-right text-red-600 font-semibold"><?= $fmt((float)$d->montant_du) ?></td>
                    <td class="py-2 text-right text-slate-500"><?= (int)$d->nb_factures ?></td>
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

// Chart évolution mensuelle
const evoData = <?= json_encode(array_values($evolution)) ?>;
if (evoData.length > 0) {
    new Chart(document.getElementById('chartEvolution'), {
        type: 'bar',
        data: {
            labels: evoData.map(d => d.mois_label),
            datasets: [{
                label: 'Recettes (XOF)',
                data: evoData.map(d => parseFloat(d.recettes)),
                backgroundColor: 'rgba(124,58,237,0.7)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { callback: v => v.toLocaleString('fr') + ' XOF' } }
            }
        }
    });
}
</script>
</body>
</html>
