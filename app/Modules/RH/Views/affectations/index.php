<?php
/** @var array $affectations, $pagination, $filters, $stats, $departements, $postes */
/** @var string $model */
/** @var bool $canCreate, $canExport */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$parType   = array_column($stats['par_type']   ?? [], 'nb', 'type');
$parStatut = array_column($stats['par_statut'] ?? [], 'nb', 'statut');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Affectations — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <!-- En-tête -->
  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="/v2/rh/employes" class="hover:text-violet-600">RH</a>
        <span>/</span><span>Affectations</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Affectations du personnel</h1>
    </div>
    <div class="flex gap-3">
      <a href="/v2/rh/affectations/statistiques"
         class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Statistiques
      </a>
      <?php if ($canExport): ?>
        <a href="/v2/rh/affectations/export?<?= http_build_query($_GET) ?>"
           class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
          CSV
        </a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
        <a href="/v2/rh/affectations/create"
           class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Nouvelle affectation
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-8">
    <div class="col-span-2 bg-emerald-50 border border-emerald-100 rounded-xl p-4">
      <p class="text-2xl font-bold text-emerald-600"><?= $stats['total_actives'] ?></p>
      <p class="text-xs text-slate-500 mt-0.5">Affectations actives</p>
    </div>
    <?php foreach ($model::TYPES as $k => $label):
      $nb = $parType[$k] ?? 0;
    ?>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <p class="text-xl font-bold <?= str_contains($model::typeColor($k), 'violet') ? 'text-violet-600' : (str_contains($model::typeColor($k), 'blue') ? 'text-blue-600' : 'text-amber-600') ?>"><?= $nb ?></p>
        <p class="text-xs text-slate-500 mt-0.5"><?= $label ?></p>
      </div>
    <?php endforeach; ?>
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
      <p class="text-xl font-bold text-blue-600"><?= $stats['total_enseignants_affectes'] ?></p>
      <p class="text-xs text-slate-500 mt-0.5">Enseignants affectés</p>
    </div>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
      <input type="text" name="q" value="<?= e($filters->q) ?>" placeholder="Nom ou matricule..."
             class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
      <select name="type" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">Tous</option>
        <?php foreach ($model::TYPES as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $filters->type === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
      <select name="statut" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">Tous</option>
        <?php foreach ($model::STATUTS as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $filters->statut === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
      <select name="departement_id" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">Tous</option>
        <?php foreach ($departements as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= $filters->departementId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="px-4 py-2 bg-violet-600 text-white text-sm rounded-lg hover:bg-violet-700">Filtrer</button>
    <a href="/v2/rh/affectations" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg hover:bg-slate-200">Réinitialiser</a>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Employé</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Type</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Statut</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Poste</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Département</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Période</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($affectations)): ?>
          <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune affectation trouvée.</td></tr>
        <?php endif; ?>
        <?php foreach ($affectations as $a): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <span class="font-medium text-slate-800"><?= e($a['employe_nom']) ?></span>
              <span class="block text-xs text-slate-400"><?= e($a['employe_matricule']) ?></span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $model::typeColor($a['type']) ?>">
                <?= e($model::typeLabel($a['type'])) ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $model::statutColor($a['statut']) ?>">
                <?= e($model::statutLabel($a['statut'])) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs"><?= e($a['poste_intitule'] ?? '—') ?></td>
            <td class="px-4 py-3">
              <span class="text-xs text-slate-600"><?= e($a['departement_nom'] ?? '—') ?></span>
              <?php if ($a['service_nom']): ?>
                <span class="block text-xs text-slate-400"><?= e($a['service_nom']) ?></span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-xs text-slate-500">
              <?= e(date('d/m/Y', strtotime($a['date_debut']))) ?>
              <?= $a['date_fin'] ? '→ ' . e(date('d/m/Y', strtotime($a['date_fin']))) : '<span class="text-slate-300">→ ∞</span>' ?>
            </td>
            <td class="px-4 py-3 text-right">
              <a href="/v2/rh/affectations/<?= (int)$a['id'] ?>"
                 class="px-2 py-1 text-xs text-violet-600 bg-violet-50 rounded hover:bg-violet-100">Voir</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($pagination['pages'] > 1): ?>
      <div class="border-t border-slate-200 px-4 py-3 flex items-center justify-between">
        <span class="text-sm text-slate-500">Total : <?= $pagination['total'] ?> affectation(s)</span>
        <div class="flex gap-1">
          <?php for ($pg = 1; $pg <= $pagination['pages']; $pg++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg])) ?>"
               class="px-3 py-1.5 text-sm rounded-lg <?= $pg === $pagination['page'] ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
              <?= $pg ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
