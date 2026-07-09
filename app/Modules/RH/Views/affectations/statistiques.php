<?php
/** @var array $stats */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$parType   = array_column($stats['par_type']   ?? [], 'nb', 'type');
$parStatut = array_column($stats['par_statut'] ?? [], 'nb', 'statut');
$parDept   = $stats['par_departement'] ?? [];
$totalType = array_sum($parType) ?: 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stats affectations — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="/v2/rh/affectations" class="hover:text-violet-600">Affectations</a>
        <span>/</span><span>Statistiques</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Statistiques des affectations</h1>
    </div>
    <a href="/v2/rh/affectations" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">← Retour</a>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-3 gap-4 mb-8">
    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-5">
      <p class="text-3xl font-bold text-emerald-600"><?= (int)$stats['total_actives'] ?></p>
      <p class="text-xs text-slate-500 mt-1">Affectations actives</p>
    </div>
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
      <p class="text-3xl font-bold text-blue-600"><?= (int)$stats['total_enseignants_affectes'] ?></p>
      <p class="text-xs text-slate-500 mt-1">Enseignants affectés</p>
    </div>
    <div class="bg-violet-50 border border-violet-100 rounded-xl p-5">
      <p class="text-3xl font-bold text-violet-600"><?= (int)$stats['total_matieres_actives'] ?></p>
      <p class="text-xs text-slate-500 mt-1">Affectations matières actives</p>
    </div>
  </div>

  <div class="grid grid-cols-2 gap-6 mb-6">

    <!-- Par type -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-700 mb-4">Répartition par type (actives)</h2>
      <div class="space-y-3">
        <?php foreach ($model::TYPES as $k => $label):
          $nb  = $parType[$k] ?? 0;
          $pct = round($nb / $totalType * 100);
        ?>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full inline-block <?= str_contains($model::typeColor($k), 'violet') ? 'bg-violet-500' : (str_contains($model::typeColor($k), 'blue') ? 'bg-blue-500' : 'bg-amber-500') ?>"></span>
                <?= e($label) ?>
              </span>
              <span class="font-medium"><?= $nb ?> <span class="text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full">
              <div class="h-2 bg-violet-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Par statut -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-700 mb-4">Répartition par statut (toutes)</h2>
      <div class="space-y-3">
        <?php
        $totalStatut = array_sum($parStatut) ?: 1;
        foreach ($model::STATUTS as $k => $label):
          $nb  = $parStatut[$k] ?? 0;
          $pct = round($nb / $totalStatut * 100);
        ?>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span><?= e($label) ?></span>
              <span class="font-medium"><?= $nb ?> <span class="text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full">
              <div class="h-2 bg-emerald-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Top départements -->
  <?php if (!empty($parDept)): ?>
  <div class="bg-white rounded-xl border border-slate-200 p-6">
    <h2 class="font-semibold text-slate-700 mb-4">Top 10 départements (affectations actives)</h2>
    <div class="space-y-2">
      <?php
      $maxDept = max(array_column($parDept, 'nb')) ?: 1;
      foreach ($parDept as $row):
        $pct = round($row['nb'] / $maxDept * 100);
      ?>
        <div class="flex items-center gap-3">
          <span class="text-sm w-40 truncate text-slate-600"><?= e($row['departement']) ?></span>
          <div class="flex-1 h-2 bg-slate-100 rounded-full">
            <div class="h-2 bg-blue-500 rounded-full" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="text-sm font-medium text-slate-700 w-8 text-right"><?= (int)$row['nb'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</main>
</body>
</html>
