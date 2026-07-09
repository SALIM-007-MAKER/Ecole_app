<?php
/** @var array $presences, $pagination, $filters, $stats, $departements */
/** @var string $model */
/** @var bool $canCreate, $canExport, $canValidate */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$aujtd     = $stats['aujourd_hui'] ?? [];
$enAttente = (int)($aujtd['en_attente'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Présences Personnel — EduNova</title>
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
        <span>/</span><span>Présences</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Présences du personnel</h1>
    </div>
    <div class="flex gap-3">
      <a href="/v2/rh/presences/statistiques"
         class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Statistiques
      </a>
      <?php if ($canValidate && $enAttente > 0): ?>
      <a href="/v2/rh/presences/validation"
         class="flex items-center gap-2 px-4 py-2 bg-amber-100 border border-amber-200 text-amber-800 rounded-lg text-sm hover:bg-amber-200">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Validation (<?= $enAttente ?>)
      </a>
      <?php endif; ?>
      <?php if ($canExport): ?>
      <a href="/v2/rh/presences/export?<?= http_build_query($_GET) ?>"
         class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">CSV</a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
      <a href="/v2/rh/presences/create"
         class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
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
    <div class="col-span-2 bg-white border border-slate-100 rounded-xl p-4 shadow-sm">
      <div class="text-xs text-slate-500 font-medium uppercase tracking-wide mb-1">Aujourd'hui</div>
      <div class="text-3xl font-bold text-slate-900"><?= (int)($aujtd['total'] ?? 0) ?></div>
      <div class="text-sm text-slate-500">pointages enregistrés</div>
    </div>
    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4">
      <div class="text-xs text-emerald-600 font-medium uppercase tracking-wide mb-1">Présents</div>
      <div class="text-2xl font-bold text-emerald-700"><?= (int)($aujtd['presents'] ?? 0) ?></div>
    </div>
    <div class="bg-red-50 border border-red-100 rounded-xl p-4">
      <div class="text-xs text-red-600 font-medium uppercase tracking-wide mb-1">Absents</div>
      <div class="text-2xl font-bold text-red-700"><?= (int)($aujtd['absents'] ?? 0) ?></div>
    </div>
    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
      <div class="text-xs text-amber-600 font-medium uppercase tracking-wide mb-1">Retards</div>
      <div class="text-2xl font-bold text-amber-700"><?= (int)($aujtd['retards'] ?? 0) ?></div>
    </div>
    <div class="bg-violet-50 border border-violet-100 rounded-xl p-4">
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
               class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-violet-300 outline-none">
      </div>
      <select name="statut" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        <option value="">Tous les statuts</option>
        <?php foreach ($model::STATUTS as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters->statut ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="statut_validation" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        <option value="">Toute validation</option>
        <?php foreach ($model::VALIDATION as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters->statutValidation ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_presence" value="<?= e($filters->datePresence ?? '') ?>"
             class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
      <div class="flex gap-2">
        <button type="submit"
                class="flex-1 px-3 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
          Filtrer
        </button>
        <a href="/v2/rh/presences" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">✕</a>
      </div>
    </form>
  </div>

  <!-- Tableau -->
  <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
    <?php if (empty($presences)): ?>
      <div class="text-center py-16 text-slate-400">
        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
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
          <td class="px-5 py-3">
            <div class="font-medium text-slate-900"><?= e($p['employe_nom']) ?></div>
            <div class="text-xs text-slate-400"><?= e($p['employe_matricule'] ?? '') ?></div>
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
            <a href="/v2/rh/presences/<?= (int)$p['id'] ?>"
               class="text-violet-600 hover:text-violet-800 text-xs font-medium">Détail</a>
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

</main>
</body>
</html>
