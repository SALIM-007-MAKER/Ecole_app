<?php
/** @var array $stats */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$aujtd   = $stats['aujourd_hui']  ?? [];
$mensuel = $stats['mensuel']      ?? [];
$sept    = $stats['sept_jours']   ?? [];
$pStatut = $stats['par_statut']   ?? [];
$pMode   = $stats['par_mode']     ?? [];
$topAbs  = $stats['top_absents']  ?? [];
$topRet  = $stats['top_retards']  ?? [];

// Labels Chart.js pour les 7 derniers jours
$labels7j = array_column($sept, 'date');
$presents7j = array_column($sept, 'presents');
$absents7j  = array_column($sept, 'absents');
$retards7j  = array_column($sept, 'retards');

// Taux de présence mensuel
$totalMens  = (int)($mensuel['total']    ?? 0);
$presentsMens = (int)($mensuel['presents'] ?? 0);
$tauxPresence = $totalMens > 0 ? round($presentsMens / $totalMens * 100, 1) : 0;
?>

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="<?= BASE_URL ?>/v2/rh/presences" class="hover:text-violet-600">Présences</a>
        <span>/</span><span>Statistiques</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Statistiques de présence</h1>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/presences" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
      ← Retour à la liste
    </a>
  </div>

  <!-- KPIs mois en cours -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <div class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-1">Taux présence (mois)</div>
      <div class="text-3xl font-bold <?= $tauxPresence >= 90 ? 'text-emerald-600' : ($tauxPresence >= 75 ? 'text-amber-600' : 'text-red-600') ?>">
        <?= number_format($tauxPresence, 1) ?>%
      </div>
      <div class="text-xs text-slate-400 mt-1"><?= $presentsMens ?>/<?= $totalMens ?> pointages</div>
    </div>
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <div class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-1">Total retards (mois)</div>
      <div class="text-3xl font-bold text-amber-600"><?= (int)($mensuel['retards'] ?? 0) ?></div>
      <div class="text-xs text-slate-400 mt-1">occurrences</div>
    </div>
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <div class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-1">Heures sup totales</div>
      <div class="text-3xl font-bold text-violet-600">
        <?= e($model::formatDuree((int)($mensuel['total_heures_supp'] ?? 0))) ?>
      </div>
      <div class="text-xs text-slate-400 mt-1">ce mois-ci</div>
    </div>
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <div class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-1">Absences (mois)</div>
      <div class="text-3xl font-bold text-red-600"><?= (int)($mensuel['absents'] ?? 0) ?></div>
      <div class="text-xs text-slate-400 mt-1">jours enregistrés</div>
    </div>
    <div class="bg-amber-50 border border-amber-100 rounded-xl p-5">
      <div class="text-xs text-amber-600 font-medium uppercase tracking-wide mb-1">En attente validation</div>
      <div class="text-3xl font-bold text-amber-700"><?= (int)($aujtd['en_attente'] ?? 0) ?></div>
      <a href="<?= BASE_URL ?>/v2/rh/presences/validation" class="text-xs text-amber-600 underline hover:text-amber-800">Traiter →</a>
    </div>
  </div>

  <!-- Graphiques ligne 1 -->
  <div class="grid grid-cols-2 gap-6 mb-6">
    <!-- 7 derniers jours -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">7 derniers jours</h2>
      <canvas id="chart-7j" height="200"></canvas>
    </div>
    <!-- Par statut -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">Répartition par statut (mois)</h2>
      <canvas id="chart-statut" height="200"></canvas>
    </div>
  </div>

  <!-- Graphique modes -->
  <div class="grid grid-cols-3 gap-6 mb-6">
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm col-span-1">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">Modes de pointage</h2>
      <canvas id="chart-mode" height="200"></canvas>
    </div>

    <!-- Top absents -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm col-span-1">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">Top absences (mois)</h2>
      <?php if (empty($topAbs)): ?>
        <p class="text-slate-400 text-sm">Aucune donnée</p>
      <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($topAbs as $i => $row): ?>
        <div class="flex items-center gap-3">
          <span class="w-5 text-xs text-slate-400 text-right"><?= $i + 1 ?></span>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800 truncate"><?= e($row['nom_complet'] ?? '—') ?></div>
            <div class="text-xs text-slate-400"><?= e($row['departement_nom'] ?? '') ?></div>
          </div>
          <span class="text-sm font-bold text-red-600"><?= (int)$row['nb_absences'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Top retards -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm col-span-1">
      <h2 class="text-sm font-semibold text-slate-700 mb-4">Top retards (mois)</h2>
      <?php if (empty($topRet)): ?>
        <p class="text-slate-400 text-sm">Aucune donnée</p>
      <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($topRet as $i => $row): ?>
        <div class="flex items-center gap-3">
          <span class="w-5 text-xs text-slate-400 text-right"><?= $i + 1 ?></span>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-slate-800 truncate"><?= e($row['nom_complet'] ?? '—') ?></div>
            <div class="text-xs text-slate-400"><?= e($row['departement_nom'] ?? '') ?></div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold text-amber-600"><?= (int)$row['nb_retards'] ?></div>
            <div class="text-xs text-slate-400"><?= e($model::formatDuree((int)($row['total_retard_min'] ?? 0))) ?> total</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

<script>
// Chart 7 jours — barres groupées
new Chart(document.getElementById('chart-7j'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d)), $labels7j)) ?>,
        datasets: [
            { label: 'Présents', data: <?= json_encode($presents7j) ?>, backgroundColor: '#10b981' },
            { label: 'Absents',  data: <?= json_encode($absents7j) ?>,  backgroundColor: '#ef4444' },
            { label: 'Retards',  data: <?= json_encode($retards7j) ?>,  backgroundColor: '#f59e0b' },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
// Chart par statut — doughnut
new Chart(document.getElementById('chart-statut'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($pStatut, 'statut_label')) ?>,
        datasets: [{ data: <?= json_encode(array_column($pStatut, 'total')) ?>,
            backgroundColor: ['#10b981','#ef4444','#f59e0b','#f97316','#0ea5e9','#6366f1','#8b5cf6'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
// Chart modes — barres horizontales
new Chart(document.getElementById('chart-mode'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($pMode, 'mode_label')) ?>,
        datasets: [{ label: 'Pointages', data: <?= json_encode(array_column($pMode, 'total')) ?>,
            backgroundColor: '#8b5cf6' }]
    },
    options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
