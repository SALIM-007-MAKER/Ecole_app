<?php
/** @var array $stats */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$parType   = array_column($stats['par_type']   ?? [], 'nb', 'type');
$parStatut = array_column($stats['par_statut'] ?? [], 'nb', 'statut');
$parDept   = $stats['par_departement'] ?? [];
$totalType = array_sum($parType) ?: 1;

$typeDot = [
    'principale' => 'bg-violet-500',
    'secondaire' => 'bg-blue-500',
    'temporaire' => 'bg-amber-500',
];
$statutBar = [
    'active'    => 'bg-emerald-500',
    'terminee'  => 'bg-slate-400',
    'suspendue' => 'bg-amber-500',
];
?>

  <!-- Fil d'ariane -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <a href="<?= BASE_URL ?>/v2/rh/affectations" class="hover:text-violet-600">Affectations</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <span class="text-slate-700">Statistiques</span>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="bar-chart-2" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Statistiques des affectations</h1>
        <p class="text-sm text-slate-500 mt-0.5">Répartition par type, statut et département</p>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/affectations" class="btn btn-outline flex-shrink-0">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
      Retour aux affectations
    </a>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <div class="bg-emerald-50 rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
      <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
      </div>
      <div class="flex-1">
        <p class="text-2xl font-bold text-emerald-600"><?= (int)$stats['total_actives'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Affectations actives</p>
      </div>
    </div>
    <div class="bg-blue-50 rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
      <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
      </div>
      <div class="flex-1">
        <p class="text-2xl font-bold text-blue-600"><?= (int)$stats['total_enseignants_affectes'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Enseignants affectés</p>
      </div>
    </div>
    <div class="bg-violet-50 rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
      <div class="w-9 h-9 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="book-open" class="w-4 h-4 text-violet-600"></i>
      </div>
      <div class="flex-1">
        <p class="text-2xl font-bold text-violet-600"><?= (int)$stats['total_matieres_actives'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Affectations matières actives</p>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

    <!-- Par type -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="flex items-center gap-2 font-semibold text-slate-700 mb-4">
        <i data-lucide="shuffle" class="w-4 h-4 text-slate-400"></i>
        Répartition par type <span class="text-xs font-normal text-slate-400">(actives)</span>
      </h2>
      <div class="space-y-4">
        <?php foreach ($model::TYPES as $k => $label):
          $nb  = $parType[$k] ?? 0;
          $pct = round($nb / $totalType * 100);
        ?>
          <div>
            <div class="flex justify-between text-sm mb-1.5">
              <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full inline-block <?= $typeDot[$k] ?? 'bg-slate-400' ?>"></span>
                <?= e($label) ?>
              </span>
              <span class="font-medium text-slate-700"><?= $nb ?> <span class="text-slate-400 font-normal">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-2 <?= $typeDot[$k] ?? 'bg-slate-400' ?> rounded-full transition-all" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Par statut -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <h2 class="flex items-center gap-2 font-semibold text-slate-700 mb-4">
        <i data-lucide="pie-chart" class="w-4 h-4 text-slate-400"></i>
        Répartition par statut <span class="text-xs font-normal text-slate-400">(toutes)</span>
      </h2>
      <div class="space-y-4">
        <?php
        $totalStatut = array_sum($parStatut) ?: 1;
        foreach ($model::STATUTS as $k => $label):
          $nb  = $parStatut[$k] ?? 0;
          $pct = round($nb / $totalStatut * 100);
        ?>
          <div>
            <div class="flex justify-between text-sm mb-1.5">
              <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full inline-block <?= $statutBar[$k] ?? 'bg-slate-400' ?>"></span>
                <?= e($label) ?>
              </span>
              <span class="font-medium text-slate-700"><?= $nb ?> <span class="text-slate-400 font-normal">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-2 <?= $statutBar[$k] ?? 'bg-slate-400' ?> rounded-full transition-all" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Top départements -->
  <?php if (!empty($parDept)): ?>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h2 class="flex items-center gap-2 font-semibold text-slate-700 mb-4">
      <i data-lucide="building-2" class="w-4 h-4 text-slate-400"></i>
      Top 10 départements <span class="text-xs font-normal text-slate-400">(affectations actives)</span>
    </h2>
    <div class="space-y-3">
      <?php
      $maxDept = max(array_column($parDept, 'nb')) ?: 1;
      foreach ($parDept as $row):
        $pct = round($row['nb'] / $maxDept * 100);
      ?>
        <div class="flex items-center gap-3">
          <span class="text-sm w-40 truncate text-slate-600" title="<?= e($row['departement']) ?>"><?= e($row['departement']) ?></span>
          <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-2 bg-blue-500 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="text-sm font-semibold text-slate-700 w-8 text-right"><?= (int)$row['nb'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
    <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
      <i data-lucide="building-2" class="w-6 h-6 text-slate-300"></i>
    </div>
    <p class="text-sm text-slate-400">Aucune donnée de département disponible.</p>
  </div>
  <?php endif; ?>

<script>
if (window.lucide) lucide.createIcons();
</script>
