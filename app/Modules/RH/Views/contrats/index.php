<?php
/** @var array $contrats, $pagination, $filters, $stats, $departements, $canCreate, $canExport */
/** @var string $model — ContractModel FQCN */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <!-- En-tête -->
  <div class="flex items-start gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <span class="text-slate-700">Contrats</span>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="file-signature" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Contrats du personnel</h1>
        <p class="text-sm text-slate-500 mt-0.5">Suivi des contrats, types et échéances</p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap flex-shrink-0">
      <a href="<?= BASE_URL ?>/v2/rh/contrats/echeances"
         class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg text-sm font-medium hover:bg-amber-100 transition-colors">
        <i data-lucide="clock" class="w-4 h-4"></i>
        Échéances
        <?php if ($stats['echeances_30'] > 0): ?>
          <span class="bg-red-500 text-white text-xs rounded-full px-1.5"><?= $stats['echeances_30'] ?></span>
        <?php endif; ?>
      </a>
      <?php if ($canExport): ?>
        <a href="<?= BASE_URL ?>/v2/rh/contrats/export?<?= http_build_query($_GET) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/contrats/create"
           class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Nouveau contrat
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

  <!-- Compteurs rapides -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php
    $statutCounts = array_column($stats['par_statut'] ?? [], 'nb', 'statut');
    $cards = [
      ['Actifs',    $stats['actifs_total'],     'text-emerald-600', 'bg-emerald-50', 'bg-emerald-100', 'text-emerald-600', 'check-circle'],
      ['CDI',       $stats['cdi_total'],         'text-violet-600',  'bg-violet-50',  'bg-violet-100',  'text-violet-600',  'file-signature'],
      ['Éch. 90j',  $stats['echeances_90'],      'text-amber-600',   'bg-amber-50',   'bg-amber-100',   'text-amber-600',   'clock'],
      ['Éch. 30j',  $stats['echeances_30'],      'text-red-600',     'bg-red-50',     'bg-red-100',     'text-red-600',     'alert-triangle'],
    ];
    ?>
    <?php foreach ($cards as [$label, $val, $textColor, $bg, $iconBg, $iconColor, $icon]): ?>
      <div class="<?= $bg ?> rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
        <div class="w-9 h-9 rounded-lg <?= $iconBg ?> flex items-center justify-center flex-shrink-0">
          <i data-lucide="<?= $icon ?>" class="w-4 h-4 <?= $iconColor ?>"></i>
        </div>
        <div class="flex-1">
          <p class="text-2xl font-bold <?= $textColor ?>"><?= $val ?></p>
          <p class="text-xs text-slate-500 mt-0.5"><?= $label ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
      <input type="text" name="q" value="<?= e($filters->q) ?>" placeholder="Numéro ou employé..."
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
      <label class="block text-xs font-medium text-slate-600 mb-1">Échéance dans</label>
      <select name="echeance_dans" class="form-select">
        <option value="">Toutes</option>
        <option value="30"  <?= $filters->echeanceDans === '30' ? 'selected' : '' ?>>30 jours</option>
        <option value="60"  <?= $filters->echeanceDans === '60' ? 'selected' : '' ?>>60 jours</option>
        <option value="90"  <?= $filters->echeanceDans === '90' ? 'selected' : '' ?>>90 jours</option>
      </select>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white text-sm font-medium rounded-lg hover:bg-violet-700 transition-colors">
      <i data-lucide="filter" class="w-4 h-4"></i> Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/rh/contrats" class="text-sm text-slate-500 hover:text-slate-700 self-end py-2">Réinitialiser</a>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Numéro</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Employé</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Type</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Statut</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Période</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Échéance</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($contrats)): ?>
          <tr>
            <td colspan="7" class="py-14">
              <div class="flex flex-col items-center gap-2 text-slate-400">
                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center">
                  <i data-lucide="file-signature" class="w-5 h-5"></i>
                </div>
                <p class="text-sm">Aucun contrat trouvé.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($contrats as $c): ?>
          <?php
          $jours      = $c['jours_restants'] !== null ? (int)$c['jours_restants'] : null;
          $alertClass = $model::alerteColor($jours);
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$c['id'] ?>"
                 class="font-mono text-sm text-violet-700 hover:underline"><?= e($c['numero_contrat']) ?></a>
              <?php if ($c['renouvelle_depuis_numero']): ?>
                <span class="block text-xs text-slate-400">↻ <?= e($c['renouvelle_depuis_numero']) ?></span>
              <?php endif; ?>
            </td>
            <?php
              $nameParts = array_filter(explode(' ', trim($c['employe_nom']), 2));
              $initiales = implode('', array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), $nameParts));
            ?>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                  <?= e($initiales) ?>
                </div>
                <div>
                  <span class="font-medium text-slate-700 block"><?= e($c['employe_nom']) ?></span>
                  <span class="text-xs text-slate-400"><?= e($c['employe_matricule']) ?></span>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $model::typeColor($c['type']) ?>">
                <?= e($model::typeLabel($c['type'])) ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $model::statutColor($c['statut']) ?>">
                <?= e($model::statutLabel($c['statut'])) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs">
              <?= e(date('d/m/Y', strtotime($c['date_debut']))) ?>
              <span class="text-slate-400">→</span>
              <?= $c['date_fin'] ? e(date('d/m/Y', strtotime($c['date_fin']))) : '<span class="text-slate-400">Indéterminé</span>' ?>
            </td>
            <td class="px-4 py-3">
              <?php if ($jours !== null && $c['statut'] === 'actif'): ?>
                <span class="px-2 py-0.5 text-xs rounded <?= $alertClass ?: 'text-slate-500' ?>">
                  <?= $jours > 0 ? "J-$jours" : 'Expiré' ?>
                </span>
              <?php elseif ($c['date_fin'] === null): ?>
                <span class="text-xs text-slate-300">CDI</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right">
              <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$c['id'] ?>"
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
        <span class="text-sm text-slate-500">Total : <?= $pagination['total'] ?> contrat(s)</span>
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
