<?php
/** @var array $stats, $fonctions */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$maxDept = max(array_column($stats['par_departement'], 'nb_employes') ?: [1]);
$maxCat  = max(array_column($stats['par_categorie'],   'nb')          ?: [1]);
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
    <a href="<?= BASE_URL ?>/v2/rh/organisation" class="hover:text-violet-600">Organisation</a>
    <span>/</span>
    <span>Statistiques</span>
  </div>
  <h1 class="text-2xl font-bold text-slate-900 mb-8">Statistiques organisationnelles</h1>

  <!-- Compteurs -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <?php $cards = [
      ['Départements',   $stats['nb_departements'],   'text-violet-600'],
      ['Services',       $stats['nb_services'],       'text-blue-600'],
      ['Postes',         $stats['nb_postes'],         'text-amber-600'],
      ['Postes occupés', $stats['nb_postes_occupes'], 'text-emerald-600'],
      ['Fonctions',      $stats['nb_fonctions'],      'text-rose-600'],
    ]; ?>
    <?php foreach ($cards as [$label, $val, $color]): ?>
      <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-3xl font-bold <?= $color ?>"><?= $val ?></p>
        <p class="text-xs text-slate-500 mt-1"><?= $label ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-2 gap-6 mb-6">

    <!-- Par département -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-800 mb-4">Employés par département (top 10)</h2>
      <?php foreach ($stats['par_departement'] as $row): ?>
        <?php $pct = $maxDept > 0 ? round(100 * $row['nb_employes'] / $maxDept) : 0; ?>
        <div class="mb-3">
          <div class="flex justify-between text-sm mb-1">
            <span class="text-slate-700 truncate"><?= e($row['nom']) ?></span>
            <span class="font-semibold text-slate-900 ml-2"><?= $row['nb_employes'] ?></span>
          </div>
          <div class="h-2 bg-slate-100 rounded-full">
            <div class="h-2 bg-violet-500 rounded-full" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Par catégorie -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-800 mb-4">Postes par catégorie</h2>
      <?php
      $catColors = ['direction' => 'bg-purple-500', 'enseignant' => 'bg-blue-500', 'administratif' => 'bg-slate-500', 'support' => 'bg-amber-500', 'technique' => 'bg-green-500'];
      foreach ($stats['par_categorie'] as $row):
        $pct = $maxCat > 0 ? round(100 * $row['nb'] / $maxCat) : 0;
        $col = $catColors[$row['categorie']] ?? 'bg-gray-400';
      ?>
        <div class="mb-3">
          <div class="flex justify-between text-sm mb-1">
            <span class="text-slate-700"><?= e(ucfirst($row['categorie'])) ?></span>
            <span class="font-semibold text-slate-900"><?= $row['nb'] ?></span>
          </div>
          <div class="h-2 bg-slate-100 rounded-full">
            <div class="h-2 <?= $col ?> rounded-full" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Fonctions -->
  <?php if (!empty($fonctions)): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h2 class="font-semibold text-slate-800 mb-4">Fonctions transversales (<?= count($fonctions) ?>)</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-slate-200">
            <tr>
              <th class="pb-2 text-left font-medium text-slate-600">Fonction</th>
              <th class="pb-2 text-left font-medium text-slate-600">Code</th>
              <th class="pb-2 text-left font-medium text-slate-600">Périmètre</th>
              <th class="pb-2 text-center font-medium text-slate-600">Affectations</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach ($fonctions as $f): ?>
              <tr class="hover:bg-slate-50">
                <td class="py-2 text-slate-700"><?= e($f['nom']) ?></td>
                <td class="py-2 font-mono text-xs text-slate-500"><?= e($f['code']) ?></td>
                <td class="py-2 text-xs text-slate-500"><?= e(ucfirst($f['perimetre'])) ?></td>
                <td class="py-2 text-center font-semibold text-slate-700"><?= (int)$f['nb_affectations'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
