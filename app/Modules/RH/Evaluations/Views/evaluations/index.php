<?php
/** @var array $items, $filters, $stats, $campagnes */
/** @var int $total, $pages */
/** @var string $model */
/** @var \App\Modules\RH\Evaluations\Policies\EvaluationPolicy $policy */
/** @var array $user */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Évaluations RH — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <div class="flex items-center justify-between mb-8">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Évaluations du personnel</h1>
      <p class="text-sm text-slate-500 mt-1"><?= $total ?> évaluation<?= $total > 1 ? 's' : '' ?></p>
    </div>
    <div class="flex gap-3">
      <a href="/v2/rh/evaluations/campagnes" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Campagnes
      </a>
      <?php if ($policy->canExport($user)): ?>
      <a href="/v2/rh/evaluations/export?<?= http_build_query($_GET) ?>"
         class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Export CSV
      </a>
      <?php endif; ?>
      <?php if ($policy->canCreate($user)): ?>
      <a href="/v2/rh/evaluations/create"
         class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        + Nouvelle évaluation
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
      ['En attente',       $stats['en_attente']      ?? 0, 'bg-amber-50 text-amber-700',   'border-amber-200'],
      ['Validées',         $stats['validees']         ?? 0, 'bg-emerald-50 text-emerald-700','border-emerald-200'],
      ['Publiées',         $stats['publiees']         ?? 0, 'bg-green-50 text-green-700',   'border-green-200'],
      ['Total',            $stats['total']            ?? 0, 'bg-slate-50 text-slate-700',   'border-slate-200'],
      ['Score moyen',      number_format((float)($stats['score_moyen'] ?? 0), 2) . '/5', 'bg-violet-50 text-violet-700', 'border-violet-200'],
      ['Campagnes actives',$stats['campagnes_actives']?? 0, 'bg-blue-50 text-blue-700',    'border-blue-200'],
    ]; foreach ($kpis as [$label, $val, $color, $border]): ?>
    <div class="bg-white border <?= $border ?> rounded-xl p-4 text-center shadow-sm">
      <div class="text-2xl font-bold <?= $color ?>"><?= is_numeric($val) ? $val : e((string)$val) ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Filtres -->
  <form method="GET" class="flex flex-wrap gap-3 mb-6">
    <input type="text" name="q" value="<?= e($filters->q) ?>" placeholder="Rechercher…"
           class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none w-52">
    <select name="campagne_id" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
      <option value="">Toutes les campagnes</option>
      <?php foreach ($campagnes as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $filters->campagneId === (int)$c['id'] ? 'selected' : '' ?>>
          <?= e($c['libelle']) ?> (<?= (int)$c['annee'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="statut" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
      <option value="">Tous statuts</option>
      <?php foreach ($model::STATUTS as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $filters->statut === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="mention" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
      <option value="">Toutes mentions</option>
      <?php foreach ($model::MENTIONS as $m): ?>
        <option value="<?= e($m) ?>" <?= $filters->mention === $m ? 'selected' : '' ?>><?= e($m) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Filtrer</button>
    <a href="/v2/rh/evaluations" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">Réinitialiser</a>
  </form>

  <!-- Tableau -->
  <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
    <?php if (empty($items)): ?>
      <div class="text-center py-12 text-slate-400">Aucune évaluation trouvée.</div>
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
          <td class="px-5 py-3">
            <div class="font-medium text-slate-900"><?= e($e['employe_nom_complet']) ?></div>
            <div class="text-xs text-slate-400"><?= e($e['employe_matricule'] ?? '') ?></div>
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
            <a href="/v2/rh/evaluations/<?= (int)$e['id'] ?>" class="text-violet-600 hover:underline text-xs">Détail</a>
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

</main>
</body>
</html>
