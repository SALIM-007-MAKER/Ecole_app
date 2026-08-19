<?php
/** @var array $affectations, $pagination, $filters, $stats, $departements, $postes */
/** @var string $model */
/** @var bool $canCreate, $canExport */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$parType   = array_column($stats['par_type']   ?? [], 'nb', 'type');
$parStatut = array_column($stats['par_statut'] ?? [], 'nb', 'statut');
?>

  <!-- En-tête -->
  <div class="flex items-start gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <span class="text-slate-700">Affectations</span>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="shuffle" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Affectations du personnel</h1>
        <p class="text-sm text-slate-500 mt-0.5">Postes, classes et départements assignés</p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap flex-shrink-0">
      <a href="<?= BASE_URL ?>/v2/rh/affectations/statistiques"
         class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
        <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
        Statistiques
      </a>
      <?php if ($canExport): ?>
        <a href="<?= BASE_URL ?>/v2/rh/affectations/export?<?= http_build_query($_GET) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/affectations/create"
           class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
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
    <div class="col-span-2 bg-emerald-50 border border-emerald-100 rounded-xl shadow-sm p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
      </div>
      <div>
        <p class="text-2xl font-bold text-emerald-600"><?= $stats['total_actives'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Affectations actives</p>
      </div>
    </div>
    <?php
    $typeIcons = ['principale' => 'star', 'secondaire' => 'link', 'temporaire' => 'clock'];
    foreach ($model::TYPES as $k => $label):
      $nb = $parType[$k] ?? 0;
      $typeColorClass = str_contains($model::typeColor($k), 'violet') ? 'violet' : (str_contains($model::typeColor($k), 'blue') ? 'blue' : 'amber');
    ?>
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="w-7 h-7 rounded-lg bg-<?= $typeColorClass ?>-100 flex items-center justify-center mb-1.5">
          <i data-lucide="<?= $typeIcons[$k] ?? 'shuffle' ?>" class="w-3.5 h-3.5 text-<?= $typeColorClass ?>-600"></i>
        </div>
        <p class="text-xl font-bold text-<?= $typeColorClass ?>-600"><?= $nb ?></p>
        <p class="text-xs text-slate-500 mt-0.5"><?= $label ?></p>
      </div>
    <?php endforeach; ?>
    <div class="bg-blue-50 border border-blue-100 rounded-xl shadow-sm p-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="graduation-cap" class="w-4 h-4 text-blue-600"></i>
      </div>
      <div>
        <p class="text-xl font-bold text-blue-600"><?= $stats['total_enseignants_affectes'] ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Enseignants affectés</p>
      </div>
    </div>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
      <input type="text" name="q" value="<?= e($filters->q) ?>" placeholder="Nom ou matricule..."
             class="form-input">
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
      <select name="type" class="form-select">
        <option value="">Tous</option>
        <?php foreach ($model::TYPES as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $filters->type === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
      <select name="statut" class="form-select">
        <option value="">Tous</option>
        <?php foreach ($model::STATUTS as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $filters->statut === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
      <select name="departement_id" class="form-select">
        <option value="">Tous</option>
        <?php foreach ($departements as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= $filters->departementId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">
      <i data-lucide="filter" class="w-4 h-4"></i> Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/rh/affectations" class="text-sm text-slate-500 hover:text-slate-700 self-end py-2">Réinitialiser</a>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
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
          <tr>
            <td colspan="7" class="py-14">
              <div class="flex flex-col items-center gap-2 text-slate-400">
                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center">
                  <i data-lucide="shuffle" class="w-5 h-5"></i>
                </div>
                <p class="text-sm">Aucune affectation trouvée.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($affectations as $a): ?>
          <?php
            $nameParts = array_filter(explode(' ', trim($a['employe_nom']), 2));
            $initiales = implode('', array_map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)), $nameParts));
          ?>
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                  <?= e($initiales) ?>
                </div>
                <div>
                  <span class="font-medium text-slate-800 block"><?= e($a['employe_nom']) ?></span>
                  <span class="text-xs text-slate-400"><?= e($a['employe_matricule']) ?></span>
                </div>
              </div>
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
              <a href="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$a['id'] ?>"
                 class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                <i data-lucide="eye" class="w-3.5 h-3.5"></i> Voir
              </a>
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
