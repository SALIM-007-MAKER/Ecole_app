<?php /** @var int $nb_articles @var int $nb_alertes_actives @var int $nb_affectations_en_cours @var int $nb_maintenances_dues @var float $valeur_stock @var float $valeur_nette @var array $mouvements_stats @var array $articles_alerte */ ?>
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Dashboard Inventaire</h1>
        <p class="text-slate-500 text-sm mt-0.5">Vue d'ensemble de la gestion des stocks et équipements</p>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <?php $kpis = [
            ['icon'=>'package',         'label'=>'Articles',         'value'=>$nb_articles,              'color'=>'violet'],
            ['icon'=>'alert-triangle',  'label'=>'Alertes actives',  'value'=>$nb_alertes_actives,       'color'=>'red'],
            ['icon'=>'user-check',      'label'=>'Affectations EC',  'value'=>$nb_affectations_en_cours, 'color'=>'blue'],
            ['icon'=>'wrench',          'label'=>'Maintenances/mois','value'=>$nb_maintenances_dues,     'color'=>'amber'],
            ['icon'=>'layers',          'label'=>'Valeur stock',     'value'=>number_format($valeur_stock,0).' u.','color'=>'green'],
            ['icon'=>'bar-chart-2',     'label'=>'VNC totale',       'value'=>number_format($valeur_nette,0).' €','color'=>'indigo'],
        ]; ?>
        <?php foreach ($kpis as $kpi): ?>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs text-slate-500 font-medium"><?= $kpi['label'] ?></span>
                <span class="w-7 h-7 rounded-lg bg-<?=$kpi['color']?>-100 flex items-center justify-center">
                    <i data-lucide="<?=$kpi['icon']?>" class="w-4 h-4 text-<?=$kpi['color']?>-600"></i>
                </span>
            </div>
            <div class="text-xl font-bold text-slate-800"><?= $kpi['value'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Graphique mouvements -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-slate-700 mb-4">Mouvements de stock (<?= $from ?> → <?= $to ?>)</h3>
            <canvas id="chartMouvements" height="220"></canvas>
        </div>

        <!-- Articles en alerte -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>
                Articles sous seuil d'alerte
            </h3>
            <?php if (empty($articles_alerte)): ?>
            <p class="text-slate-400 text-sm text-center py-8">Aucun article en alerte</p>
            <?php else: ?>
            <div class="space-y-3">
            <?php foreach (array_slice($articles_alerte, 0, 8) as $a): ?>
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div>
                        <p class="font-medium text-slate-800 text-sm"><?= htmlspecialchars($a['designation']) ?></p>
                        <p class="text-xs text-slate-500 font-mono"><?= htmlspecialchars($a['reference']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-red-600 font-bold text-sm"><?= number_format((float)$a['stock_total'], 1) ?></p>
                        <p class="text-xs text-slate-400">/ <?= $a['seuil_alerte'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
lucide.createIcons();
const stats = <?= json_encode(array_column($mouvements_stats, null, 'type')) ?>;
const types = ['entree','sortie','transfert','ajustement','consommation','affectation'];
const labels = types.map(t => t.charAt(0).toUpperCase() + t.slice(1));
const values = types.map(t => parseFloat(stats[t]?.total || 0));
new Chart(document.getElementById('chartMouvements'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Quantité',
            data: values,
            backgroundColor: ['#10b981','#ef4444','#6366f1','#f59e0b','#64748b','#8b5cf6'],
            borderRadius: 6,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
