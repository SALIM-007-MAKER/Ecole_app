<?php
/** @var array $stats */
$titre = 'Administration — Statistiques';
$fmt = function(int $o): string {
    return $o >= 1073741824 ? round($o/1073741824,1).' Go'
         : ($o >= 1048576 ? round($o/1048576,1).' Mo'
         : ($o >= 1024 ? round($o/1024,1).' Ko' : $o.' o'));
};
?>
<div class="p-6 space-y-6">

  <h1 class="text-2xl font-bold text-slate-800">Statistiques Documents</h1>

  <!-- KPIs -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php $kpis = [
      'Total documents' => number_format($stats['total'] ?? 0),
      'Actifs'          => number_format($stats['actifs'] ?? 0),
      'Archivés'        => number_format($stats['archives'] ?? 0),
      'Expirés'         => number_format($stats['expires'] ?? 0),
    ]; foreach ($kpis as $label => $val): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
      <div class="text-2xl font-bold text-violet-700"><?= $val ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Stockage -->
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="text-base font-semibold text-slate-700 mb-3">Stockage total</h2>
    <div class="text-3xl font-bold text-slate-800"><?= $fmt((int)($stats['taille_totale'] ?? 0)) ?></div>
    <p class="text-sm text-slate-500 mt-1">
      Taille moyenne : <?= $fmt((int)($stats['taille_moyenne'] ?? 0)) ?> / document
    </p>
  </div>

  <!-- Par module -->
  <?php if (!empty($stats['par_module'])): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="text-base font-semibold text-slate-700 mb-3">Répartition par module</h2>
    <div class="space-y-3">
      <?php foreach ($stats['par_module'] as $row): ?>
      <div class="flex items-center justify-between text-sm">
        <span class="text-slate-700 font-medium"><?= htmlspecialchars($row['module_source']) ?></span>
        <span class="text-slate-500"><?= number_format($row['total']) ?> docs · <?= $fmt((int)$row['taille']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="flex gap-4 text-sm">
    <a href="<?= BASE_URL ?>/v2/documents/admin/quotas" class="text-violet-600 hover:text-violet-800 font-medium">← Quotas</a>
    <a href="<?= BASE_URL ?>/v2/documents/admin/expirations" class="text-violet-600 hover:text-violet-800 font-medium">Expirations →</a>
  </div>
</div>
