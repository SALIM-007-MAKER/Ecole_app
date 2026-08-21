<?php
/** @var array $items, $filters, $stats, $campagnes */
/** @var int $total, $pages */
/** @var string $model */
/** @var \App\Modules\RH\Evaluations\Policies\EvaluationPolicy $policy */
/** @var array $user */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-start gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/employes" class="hover:text-violet-600">RH</a>
    <i data-lucide="chevron-right" class="w-3 h-3 mt-0.5"></i>
    <span class="text-slate-700">Évaluations</span>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="star" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Évaluations du personnel</h1>
        <p class="text-sm text-slate-500 mt-0.5"><?= $total ?> évaluation<?= $total > 1 ? 's' : '' ?></p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap flex-shrink-0">
      <a href="<?= BASE_URL ?>/v2/rh/evaluations/campagnes" class="btn btn-outline">
        <i data-lucide="flag" class="w-4 h-4"></i>
        Campagnes
      </a>
      <?php if ($policy->canExport($user)): ?>
      <a href="<?= BASE_URL ?>/v2/rh/evaluations/export?<?= http_build_query($_GET) ?>" class="btn btn-outline">
        <i data-lucide="download" class="w-4 h-4"></i>
        Export CSV
      </a>
      <?php endif; ?>
      <?php if ($policy->canCreate($user)): ?>
      <a href="<?= BASE_URL ?>/v2/rh/evaluations/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nouvelle évaluation
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
  <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
    <?php $kpis = [
      ['En attente',       $stats['en_attente']      ?? 0, 'text-amber-700',   'border-amber-200',   'bg-amber-100',   'text-amber-600',   'hourglass'],
      ['Validées',         $stats['validees']         ?? 0, 'text-emerald-700','border-emerald-200', 'bg-emerald-100', 'text-emerald-600', 'check-circle'],
      ['Publiées',         $stats['publiees']         ?? 0, 'text-green-700',  'border-green-200',   'bg-green-100',   'text-green-600',   'megaphone'],
      ['Total',            $stats['total']            ?? 0, 'text-slate-700',  'border-slate-200',   'bg-slate-100',   'text-slate-600',   'list'],
      ['Score moyen',      number_format((float)($stats['score_moyen'] ?? 0), 2) . '/5', 'text-violet-700', 'border-violet-200', 'bg-violet-100', 'text-violet-600', 'star'],
      ['Campagnes actives',$stats['campagnes_actives']?? 0, 'text-blue-700',   'border-blue-200',    'bg-blue-100',    'text-blue-600',    'flag'],
    ]; foreach ($kpis as [$label, $val, $color, $border, $iconBg, $iconColor, $icon]): ?>
    <div class="bg-white border <?= $border ?> rounded-xl p-4 text-center shadow-sm">
      <div class="w-8 h-8 rounded-lg <?= $iconBg ?> flex items-center justify-center mx-auto mb-2">
        <i data-lucide="<?= $icon ?>" class="w-4 h-4 <?= $iconColor ?>"></i>
      </div>
      <div class="text-2xl font-bold <?= $color ?>"><?= is_numeric($val) ? $val : e((string)$val) ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 flex flex-wrap gap-3 mb-6 items-end">
    <input type="text" name="q" value="<?= e($filters->q) ?>" placeholder="Rechercher…"
           class="form-input w-52">
    <select name="campagne_id" class="form-select">
      <option value="">Toutes les campagnes</option>
      <?php foreach ($campagnes as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $filters->campagneId === (int)$c['id'] ? 'selected' : '' ?>>
          <?= e($c['libelle']) ?> (<?= (int)$c['annee'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="statut" class="form-select">
      <option value="">Tous statuts</option>
      <?php foreach ($model::STATUTS as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $filters->statut === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="mention" class="form-select">
      <option value="">Toutes mentions</option>
      <?php foreach ($model::MENTIONS as $m): ?>
        <option value="<?= e($m) ?>" <?= $filters->mention === $m ? 'selected' : '' ?>><?= e($m) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">
      <i data-lucide="filter" class="w-4 h-4"></i> Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="text-sm text-slate-500 hover:text-slate-700 self-end py-2">Réinitialiser</a>
  </form>

  <!-- Tableau -->
  <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
    <?php if (empty($items)): ?>
      <div class="text-center py-14 text-slate-400">
        <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="star" class="w-5 h-5"></i>
        </div>
        <p class="text-sm">Aucune évaluation trouvée.</p>
      </div>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-100">
        <tr>
          <th class="text-left px-5 py-3 font-medium text-slate-600">Employé</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Campagne</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Département</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
          <th class="text-right px-4 py-3 font-medium text-slate-600">Score</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Mention</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($items as $e): ?>
        <tr class="hover:bg-slate-50">
          <?php
            $nameParts = array_filter(explode(' ', trim($e['employe_nom_complet']), 2));
            $initiales = implode('', array_map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)), $nameParts));
          ?>
          <td class="px-5 py-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                <?= e($initiales) ?>
              </div>
              <div>
                <div class="font-medium text-slate-900"><?= e($e['employe_nom_complet']) ?></div>
                <div class="text-xs text-slate-400"><?= e($e['employe_matricule'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td class="px-4 py-3">
            <div><?= e($e['campagne_libelle']) ?></div>
            <div class="text-xs text-slate-400"><?= (int)$e['campagne_annee'] ?></div>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= e($e['departement_nom'] ?? '—') ?></td>
          <td class="px-4 py-3">
            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $model::statutColor($e['statut']) ?>">
              <?= $model::statutLabel($e['statut']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right font-bold <?= $e['score_final'] !== null ? $model::scoreColor((float)$e['score_final']) : 'text-slate-300' ?>">
            <?= $e['score_final'] !== null ? $model::formatScore((float)$e['score_final']) : '—' ?>
          </td>
          <td class="px-4 py-3">
            <?php if ($e['mention']): ?>
              <span class="px-2 py-0.5 rounded text-xs font-medium <?= $model::mentionColor($e['mention']) ?>">
                <?= e($e['mention']) ?>
              </span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$e['id'] ?>"
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
  <?php if ($pages > 1): ?>
  <div class="flex justify-center gap-2 mt-6">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
         class="px-3 py-1.5 rounded text-sm <?= $filters->page === $p ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
