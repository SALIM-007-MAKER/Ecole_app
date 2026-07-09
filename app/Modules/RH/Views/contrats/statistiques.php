<?php
/** @var array $stats */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$parStatut = array_column($stats['par_statut'] ?? [], 'nb', 'statut');
$parType   = array_column($stats['par_type']   ?? [], 'nb', 'type');
$totalType = array_sum($parType) ?: 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistiques contrats — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
        <span>/</span><span>Statistiques</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Statistiques des contrats</h1>
    </div>
    <a href="/v2/rh/contrats" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">← Retour</a>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php $kpis = [
      ['Contrats actifs',    $stats['actifs_total'],    'text-emerald-600', 'bg-emerald-50 border-emerald-100'],
      ['CDI actifs',         $stats['cdi_total'],        'text-violet-600',  'bg-violet-50 border-violet-100'],
      ['Expirent dans 90j',  $stats['echeances_90'],    'text-amber-600',   'bg-amber-50 border-amber-100'],
      ['Expirent dans 30j',  $stats['echeances_30'],    'text-red-600',     'bg-red-50 border-red-100'],
    ]; ?>
    <?php foreach ($kpis as [$label, $val, $textColor, $bg]): ?>
      <div class="<?= $bg ?> rounded-xl border p-5">
        <p class="text-2xl font-bold <?= $textColor ?>"><?= (int)$val ?></p>
        <p class="text-xs text-slate-500 mt-0.5"><?= $label ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Masse salariale -->
  <div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
    <h2 class="font-semibold text-slate-700 mb-1">Masse salariale mensuelle brute</h2>
    <p class="text-3xl font-bold text-slate-900"><?= number_format((float)$stats['masse_salariale'], 2, ',', ' ') ?> DZD</p>
    <p class="text-xs text-slate-400 mt-1">Somme des salaires bruts des contrats actifs</p>
  </div>

  <div class="grid grid-cols-2 gap-6">

    <!-- Par statut -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-700 mb-4">Répartition par statut</h2>
      <div class="space-y-3">
        <?php
        $totalStatut = array_sum($parStatut) ?: 1;
        foreach ($model::STATUTS as $k => $label):
          $nb  = $parStatut[$k] ?? 0;
          $pct = round($nb / $totalStatut * 100);
        ?>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full inline-block <?= str_replace(['text-', 'bg-', '100 ', '700'], ['bg-', '', '', '500'], $model::statutColor($k)) ?>"></span>
                <?= e($label) ?>
              </span>
              <span class="font-medium"><?= $nb ?> <span class="text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-2 bg-violet-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Par type -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-700 mb-4">Répartition par type</h2>
      <div class="space-y-3">
        <?php foreach ($model::TYPES as $k => $label):
          $nb  = $parType[$k] ?? 0;
          $pct = round($nb / $totalType * 100);
        ?>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span><?= e($label) ?></span>
              <span class="font-medium"><?= $nb ?> <span class="text-slate-400">(<?= $pct ?>%)</span></span>
            </div>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-2 bg-emerald-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Alertes échéances -->
  <?php if ($stats['echeances_30'] > 0): ?>
    <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800 flex items-center gap-3">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      <span><strong><?= $stats['echeances_30'] ?> contrat(s)</strong> arrivent à échéance dans moins de 30 jours.
        <a href="/v2/rh/contrats/echeances?jours=30" class="underline hover:text-red-900">Voir la liste →</a>
      </span>
    </div>
  <?php endif; ?>

</main>
</body>
</html>
