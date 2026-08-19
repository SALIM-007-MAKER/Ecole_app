<?php
$title = 'Statistiques retards';
?>
<div class="max-w-7xl mx-auto px-4 py-6">

  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Statistiques des retards</h1>
      <p class="text-slate-500 text-sm mt-1">Par classe et par élève</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards"
       class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour à la liste
    </a>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 mb-6">
    <div class="flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Classe</label>
        <select name="classe_id"
                class="form-select">
          <option value="">Sélectionner une classe</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c->id ?>" <?= $classeId == $c->id ? 'selected' : '' ?>>
            <?= htmlspecialchars($c->nom) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Année scolaire</label>
        <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($annee) ?>"
               placeholder="2024-2025"
               class="form-input">
      </div>
      <button type="submit"
              class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        Voir les stats
      </button>
    </div>
  </form>

  <!-- Indicateur seuil -->
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-700 flex items-center gap-2">
    <i data-lucide="alert-triangle" class="w-4 h-4 flex-shrink-0"></i>
    <span>Seuil d'alerte retards : <strong><?= $seuil ?> retards</strong> par élève par année scolaire.</span>
  </div>

  <?php if (empty($stats) && $classeId === 0): ?>
  <div class="bg-white border border-slate-200 rounded-xl py-16 text-center text-slate-400">
    <i data-lucide="filter" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">Sélectionnez une classe pour afficher les statistiques.</p>
  </div>
  <?php elseif (empty($stats)): ?>
  <div class="bg-white border border-slate-200 rounded-xl py-16 text-center text-slate-400">
    <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p class="text-sm">Aucun retard enregistré pour cette classe sur cette période.</p>
  </div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
          <th class="text-center px-4 py-3 font-medium text-slate-600">Total retards</th>
          <th class="text-center px-4 py-3 font-medium text-slate-600">Justifiés</th>
          <th class="text-center px-4 py-3 font-medium text-slate-600">Non justifiés</th>
          <th class="text-center px-4 py-3 font-medium text-slate-600">Total (min)</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Progression</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($stats as $s): ?>
        <?php
          $total   = (int)$s['total_retards'];
          $pct     = min(100, round($total / $seuil * 100));
          $barColor = $total >= $seuil  ? 'bg-red-500'
                   : ($total >= $seuil - 1 ? 'bg-amber-400' : 'bg-violet-500');
          $rowClass = $total >= $seuil ? 'bg-red-50' : '';
        ?>
        <tr class="hover:bg-slate-50 <?= $rowClass ?>">
          <td class="px-4 py-3 font-medium text-slate-800">
            <?= htmlspecialchars($s['eleve_prenom'] . ' ' . $s['eleve_nom']) ?>
            <?php if ($total >= $seuil): ?>
            <span class="ml-2 inline-flex px-1.5 py-0.5 bg-red-100 text-red-600 text-xs rounded-full font-medium">⚠ Seuil</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-center font-bold <?= $total >= $seuil ? 'text-red-600' : 'text-slate-700' ?>">
            <?= $total ?>
          </td>
          <td class="px-4 py-3 text-center text-green-600"><?= (int)$s['justifies'] ?></td>
          <td class="px-4 py-3 text-center text-red-500"><?= (int)$s['non_justifies'] ?></td>
          <td class="px-4 py-3 text-center text-slate-600"><?= (int)$s['total_minutes'] ?></td>
          <td class="px-4 py-3 min-w-[140px]">
            <div class="flex items-center gap-2">
              <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full <?= $barColor ?> transition-all"
                     style="width: <?= $pct ?>%"></div>
              </div>
              <span class="text-xs text-slate-500 w-8 text-right"><?= $total ?>/<?= $seuil ?></span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php
    $enSeuil = array_filter($stats, fn($s) => (int)$s['total_retards'] >= $seuil);
  ?>
  <?php if (count($enSeuil) > 0): ?>
  <div class="mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
    <strong><?= count($enSeuil) ?> élève(s)</strong> ont atteint ou dépassé le seuil de <?= $seuil ?> retards.
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>
