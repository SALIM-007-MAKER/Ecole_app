<?php /** @var array $emprunts_en_cours @var array $reservations @var array $penalites @var int $nb_impayees @var array|null $historique @var string|null $mode @var string $titre */ ?>
<div class="max-w-4xl mx-auto py-8 px-4">

  <h1 class="text-2xl font-bold text-slate-800 mb-6">Mon compte bibliothèque</h1>

  <!-- Nav -->
  <div class="flex gap-2 mb-6">
    <a href="<?= BASE_URL ?>/v2/bibliotheque/mon-compte" class="text-sm px-4 py-2 rounded-lg border <?= empty($mode) ? 'bg-violet-600 text-white border-violet-600' : 'bg-white border-slate-200 text-slate-600' ?>">Tableau de bord</a>
    <a href="<?= BASE_URL ?>/v2/bibliotheque/mon-compte/historique" class="text-sm px-4 py-2 rounded-lg border <?= ($mode ?? '') === 'historique' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white border-slate-200 text-slate-600' ?>">Historique</a>
    <a href="<?= BASE_URL ?>/v2/bibliotheque/mon-compte/penalites" class="text-sm px-4 py-2 rounded-lg border <?= ($mode ?? '') === 'penalites' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white border-slate-200 text-slate-600' ?>">
      Mes pénalités
      <?php if (($nb_impayees ?? 0) > 0): ?><span class="ml-1 bg-red-500 text-white text-xs rounded-full px-1.5"><?= $nb_impayees ?></span><?php endif ?>
    </a>
  </div>

  <?php if (($mode ?? '') === 'historique' && isset($historique)): ?>
  <!-- Historique -->
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b font-semibold text-slate-700">Historique des emprunts (<?= count($historique) ?>)</div>
    <?php if (empty($historique)): ?>
    <div class="p-8 text-center text-slate-400">Aucun emprunt</div>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b">
        <tr>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">Ouvrage</th>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">Emprunté le</th>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">Retourné le</th>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">Statut</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($historique as $e): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2 text-slate-800"><?= htmlspecialchars($e['ouvrage_titre'] ?? '—') ?></td>
          <td class="px-4 py-2 text-slate-600"><?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></td>
          <td class="px-4 py-2 text-slate-600"><?= !empty($e['date_retour_effectif']) ? date('d/m/Y', strtotime($e['date_retour_effectif'])) : '—' ?></td>
          <td class="px-4 py-2">
            <span class="text-xs px-2 py-0.5 rounded-full <?= $e['statut'] === 'retourne' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?> capitalize"><?= $e['statut'] ?></span>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <?php else: ?>
  <!-- Tableau de bord -->
  <div class="space-y-6">

    <?php if (!empty($nb_impayees) && $nb_impayees > 0): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between">
      <p class="text-red-700 font-medium"><?= $nb_impayees ?> pénalité(s) impayée(s)</p>
      <a href="<?= BASE_URL ?>/v2/bibliotheque/mon-compte/penalites" class="text-sm text-red-700 underline">Voir →</a>
    </div>
    <?php endif ?>

    <!-- Emprunts en cours -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b font-semibold text-slate-700">Mes emprunts en cours (<?= count($emprunts_en_cours ?? []) ?>)</div>
      <?php if (empty($emprunts_en_cours)): ?>
      <div class="p-8 text-center text-slate-400">Aucun emprunt en cours</div>
      <?php else: ?>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($emprunts_en_cours as $e): ?>
          <?php $retard = $e['statut'] === 'en_retard'; ?>
          <tr class="hover:bg-slate-50 <?= $retard ? 'bg-red-50' : '' ?>">
            <td class="px-4 py-3">
              <p class="text-slate-800"><?= htmlspecialchars($e['ouvrage_titre'] ?? '—') ?></p>
              <p class="text-xs text-slate-400 font-mono"><?= $e['numero_inventaire'] ?? '' ?></p>
            </td>
            <td class="px-4 py-3 text-right text-sm <?= $retard ? 'text-red-600 font-semibold' : 'text-slate-600' ?>">
              <?= date('d/m/Y', strtotime($e['date_retour_prevue'])) ?>
              <?php if ($retard): ?><br><span class="text-xs">En retard</span><?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
      <?php endif ?>
    </div>

    <!-- Réservations -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b font-semibold text-slate-700">Mes réservations (<?= count($reservations ?? []) ?>)</div>
      <?php if (empty($reservations)): ?>
      <div class="p-8 text-center text-slate-400">Aucune réservation</div>
      <?php else: ?>
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($reservations as $r): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <p class="text-slate-800"><?= htmlspecialchars($r['ouvrage_titre'] ?? '—') ?></p>
              <p class="text-xs text-slate-400">Position #<?= $r['position_file'] ?? '—' ?></p>
            </td>
            <td class="px-4 py-3 text-right">
              <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $r['statut'] === 'disponible' ? 'green' : 'amber' ?>-100 text-<?= $r['statut'] === 'disponible' ? 'green' : 'amber' ?>-700 capitalize">
                <?= str_replace('_', ' ', $r['statut']) ?>
              </span>
              <?php if ($r['statut'] === 'disponible'): ?>
              <button onclick="confirmerResa(<?= $r['id'] ?>)" class="ml-2 text-xs text-green-600 hover:underline">Confirmer</button>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
      <?php endif ?>
    </div>
  </div>
  <?php endif ?>

</div>
<script>
function confirmerResa(id) {
  fetch('<?= BASE_URL ?>/v2/bibliotheque/reservations/' + id + '/confirmer', {method:'POST'}).then(()=>location.reload());
}
</script>
