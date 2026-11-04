<?php
/** @var array $presences, $pagination, $filters, $stats, $departements */
/** @var string $model */
/** @var bool $canCreate, $canExport, $canValidate */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$aujtd     = $stats['aujourd_hui'] ?? [];
$enAttente = (int)($aujtd['en_attente'] ?? 0);
?>

  <!-- En-tête -->
  <div class="flex items-start gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <span class="text-slate-700">Présences</span>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="clock" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Présences du personnel</h1>
        <p class="text-sm text-slate-500 mt-0.5">Pointages, retards et validation des heures</p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap flex-shrink-0">
      <a href="<?= BASE_URL ?>/v2/rh/presences/statistiques" class="btn btn-outline">
        <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
        Statistiques
      </a>
      <?php if ($canValidate && $enAttente > 0): ?>
      <a href="<?= BASE_URL ?>/v2/rh/presences/validation" class="btn bg-amber-100 border-amber-200 text-amber-800 hover:bg-amber-200">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        Validation (<?= $enAttente ?>)
      </a>
      <?php endif; ?>
      <?php if ($canExport): ?>
      <a href="<?= BASE_URL ?>/v2/rh/presences/export?<?= http_build_query($_GET) ?>" class="btn btn-outline">
        <i data-lucide="download" class="w-4 h-4"></i>
        Export CSV
      </a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
      <a href="<?= BASE_URL ?>/v2/rh/presences/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nouveau pointage
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

  <!-- KPIs aujourd'hui -->
  <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
    <div class="col-span-2 bg-white border border-slate-100 rounded-xl p-4 shadow-sm flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="calendar-check" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <div class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-1">Aujourd'hui</div>
        <div class="text-3xl font-bold text-slate-900"><?= (int)($aujtd['total'] ?? 0) ?></div>
        <div class="text-sm text-slate-500">pointages enregistrés</div>
      </div>
    </div>
    <div class="bg-emerald-50 border border-emerald-100 rounded-xl shadow-sm p-4">
      <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center mb-1.5">
        <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
      </div>
      <div class="text-xs text-emerald-600 font-medium uppercase tracking-wide mb-1">Présents</div>
      <div class="text-2xl font-bold text-emerald-700"><?= (int)($aujtd['presents'] ?? 0) ?></div>
    </div>
    <div class="bg-red-50 border border-red-100 rounded-xl shadow-sm p-4">
      <div class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center mb-1.5">
        <i data-lucide="x-circle" class="w-3.5 h-3.5 text-red-600"></i>
      </div>
      <div class="text-xs text-red-600 font-medium uppercase tracking-wide mb-1">Absents</div>
      <div class="text-2xl font-bold text-red-700"><?= (int)($aujtd['absents'] ?? 0) ?></div>
    </div>
    <div class="bg-amber-50 border border-amber-100 rounded-xl shadow-sm p-4">
      <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center mb-1.5">
        <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
      </div>
      <div class="text-xs text-amber-600 font-medium uppercase tracking-wide mb-1">Retards</div>
      <div class="text-2xl font-bold text-amber-700"><?= (int)($aujtd['retards'] ?? 0) ?></div>
    </div>
    <div class="bg-violet-50 border border-violet-100 rounded-xl shadow-sm p-4">
      <div class="w-7 h-7 rounded-lg bg-violet-100 flex items-center justify-center mb-1.5">
        <i data-lucide="hourglass" class="w-3.5 h-3.5 text-violet-600"></i>
      </div>
      <div class="text-xs text-violet-600 font-medium uppercase tracking-wide mb-1">En attente</div>
      <div class="text-2xl font-bold text-violet-700"><?= $enAttente ?></div>
    </div>
  </div>

  <!-- Filtres -->
  <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm mb-6">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-6 gap-3">
      <div class="col-span-2">
        <input type="text" name="q" value="<?= e($filters->q ?? '') ?>"
               placeholder="Rechercher un employé…"
               class="form-input">
      </div>
      <select name="statut" class="form-select">
        <option value="">Tous les statuts</option>
        <?php foreach ($model::STATUTS as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters->statut ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="statut_validation" class="form-select">
        <option value="">Toute validation</option>
        <?php foreach ($model::VALIDATION as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters->statutValidation ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_presence" value="<?= e($filters->datePresence ?? '') ?>"
             class="form-input">
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary flex-1">
          <i data-lucide="filter" class="w-4 h-4"></i> Filtrer
        </button>
        <a href="<?= BASE_URL ?>/v2/rh/presences" class="btn btn-ghost" title="Réinitialiser">
          <i data-lucide="x" class="w-4 h-4"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- Tableau -->
  <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
    <?php if (empty($presences)): ?>
      <div class="text-center py-16 text-slate-400">
        <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="calendar-clock" class="w-5 h-5"></i>
        </div>
        <p class="font-medium">Aucun pointage trouvé</p>
      </div>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-100">
        <tr>
          <th class="text-left px-5 py-3 font-medium text-slate-600">Employé</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Arrivée</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Départ</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Durée</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Mode</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Retard</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Validation</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($presences as $p): ?>
        <tr class="hover:bg-slate-50 transition-colors">
          <?php
            $nameParts = array_filter(explode(' ', trim($p['employe_nom']), 2));
            $initiales = implode('', array_map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)), $nameParts));
          ?>
          <td class="px-5 py-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                <?= e($initiales) ?>
              </div>
              <div>
                <div class="font-medium text-slate-900"><?= e($p['employe_nom']) ?></div>
                <div class="text-xs text-slate-400"><?= e($p['employe_matricule'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 text-slate-700"><?= e(date('d/m/Y', strtotime($p['date_presence']))) ?></td>
          <td class="px-4 py-3 text-slate-700"><?= e($p['heure_arrivee'] ? substr($p['heure_arrivee'], 0, 5) : '—') ?></td>
          <td class="px-4 py-3 text-slate-700"><?= e($p['heure_depart']  ? substr($p['heure_depart'],  0, 5) : '—') ?></td>
          <td class="px-4 py-3 text-slate-700"><?= e($model::formatDuree((int)($p['duree_minutes'] ?? 0))) ?></td>
          <td class="px-4 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::statutColor($p['statut']) ?>">
              <?= e($model::statutLabel($p['statut'])) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::modeColor($p['mode_pointage']) ?>">
              <?= e($model::modeLabel($p['mode_pointage'])) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <?php $retard = (int)($p['retard_minutes'] ?? 0); ?>
            <?php if ($retard > 0): ?>
              <span class="<?= $model::alerteRetard($retard) ?>">+<?= $model::formatDuree($retard) ?></span>
            <?php else: ?>
              <span class="text-slate-300">—</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::validationColor($p['statut_validation']) ?>">
              <?= e($model::validationLabel($p['statut_validation'])) ?>
            </span>
          </td>
          <td class="px-4 py-3">
            <a href="<?= BASE_URL ?>/v2/rh/presences/<?= (int)$p['id'] ?>"
               class="inline-flex items-center gap-1 text-violet-600 hover:text-violet-800 text-xs font-medium">
              <i data-lucide="eye" class="w-3.5 h-3.5"></i> Détail
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if (($pagination['pages'] ?? 1) > 1): ?>
  <div class="flex items-center justify-between mt-4 text-sm text-slate-600">
    <span><?= number_format($pagination['total']) ?> résultats</span>
    <div class="flex gap-2">
      <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
        <?php $q = http_build_query(array_merge($_GET, ['page' => $i])); ?>
        <a href="?<?= $q ?>"
           class="px-3 py-1 rounded-lg border <?= $i === $pagination['page'] ? 'bg-violet-600 text-white border-violet-600' : 'border-slate-200 hover:bg-slate-50' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
