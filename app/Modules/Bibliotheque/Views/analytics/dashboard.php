<?php /** @var array $stats @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Analytique') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Analytique Bibliothèque</h1>
    <a href="/v2/bibliotheque/analytics/export?format=json" class="text-sm border border-slate-200 bg-white px-4 py-2 rounded-lg hover:border-violet-400">Exporter</a>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php $kpis = [
      ['label' => 'Ouvrages', 'val' => $stats['total_ouvrages'] ?? 0, 'color' => 'violet'],
      ['label' => 'Exemplaires', 'val' => $stats['total_exemplaires'] ?? 0, 'color' => 'slate'],
      ['label' => 'Emprunts actifs', 'val' => $stats['emprunts_en_cours'] ?? 0, 'color' => 'blue'],
      ['label' => 'En retard', 'val' => $stats['emprunts_en_retard'] ?? 0, 'color' => 'red'],
      ['label' => 'Réservations', 'val' => $stats['reservations_actives'] ?? 0, 'color' => 'amber'],
      ['label' => 'Pénalités (€)', 'val' => number_format((float)($stats['penalites_impayees'] ?? 0), 2), 'color' => 'orange'],
      ['label' => 'Taux retard', 'val' => ($stats['taux_retard'] ?? 0) . '%', 'color' => 'rose'],
      ['label' => 'Réservations actives', 'val' => $stats['reservations_actives'] ?? 0, 'color' => 'teal'],
    ]; ?>
    <?php foreach (array_slice($kpis, 0, 8) as $kpi): ?>
    <div class="bg-white rounded-xl shadow-sm p-4">
      <p class="text-2xl font-bold text-<?= $kpi['color'] ?>-600"><?= $kpi['val'] ?></p>
      <p class="text-xs text-slate-500 mt-1"><?= $kpi['label'] ?></p>
    </div>
    <?php endforeach ?>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Emprunts par mois -->
    <div class="bg-white rounded-xl shadow-sm p-4">
      <h2 class="font-semibold text-slate-700 mb-4">Emprunts par mois</h2>
      <canvas id="chartMois" height="200"></canvas>
    </div>

    <!-- Exemplaires par statut -->
    <div class="bg-white rounded-xl shadow-sm p-4">
      <h2 class="font-semibold text-slate-700 mb-4">Exemplaires par statut</h2>
      <canvas id="chartStatut" height="200"></canvas>
    </div>
  </div>

  <!-- Top ouvrages -->
  <?php if (!empty($stats['ouvrages_populaires'])): ?>
  <div class="bg-white rounded-xl shadow-sm p-4">
    <h2 class="font-semibold text-slate-700 mb-4">Ouvrages les plus empruntés</h2>
    <div class="space-y-2">
      <?php foreach ($stats['ouvrages_populaires'] as $i => $o): ?>
      <div class="flex items-center gap-3">
        <span class="w-6 h-6 bg-violet-100 text-violet-700 rounded-full flex items-center justify-center text-xs font-bold"><?= $i + 1 ?></span>
        <div class="flex-1">
          <div class="flex items-center justify-between">
            <p class="text-sm text-slate-800"><?= htmlspecialchars($o['titre']) ?></p>
            <p class="text-sm font-semibold text-violet-600"><?= $o['nb_emprunts'] ?></p>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1">
            <div class="bg-violet-500 h-1.5 rounded-full" style="width:<?= min(100, (int)$o['nb_emprunts'] * 10) ?>%"></div>
          </div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

</div>

<script>
<?php $parMois = $stats['emprunts_par_mois'] ?? []; ?>
new Chart(document.getElementById('chartMois'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($parMois, 'mois')) ?>,
    datasets: [{
      label: 'Emprunts',
      data: <?= json_encode(array_column($parMois, 'nb')) ?>,
      backgroundColor: 'rgba(139,92,246,0.7)',
      borderRadius: 4,
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } } }
});

<?php $parStatut = $stats['exemplaires_par_statut'] ?? []; ?>
new Chart(document.getElementById('chartStatut'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($parStatut)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($parStatut)) ?>,
      backgroundColor: ['#22c55e','#3b82f6','#f59e0b','#f97316','#ef4444','#94a3b8'],
    }]
  },
  options: { responsive: true }
});
</script>
</body>
</html>
