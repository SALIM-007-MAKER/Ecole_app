<?php /** @var array $penalites @var float|null $total @var string|null $mode @var string $titre */ ?>
<div class="max-w-5xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($titre ?? '') ?></h1>
    <?php if (!empty($total) && $total > 0): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-lg text-sm font-semibold">
      Total impayé : <?= number_format((float)$total, 2) ?> €
    </div>
    <?php endif ?>
  </div>

  <?php if (empty($penalites)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p class="text-4xl mb-3">✅</p>
      <p>Aucune pénalité</p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Personne</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Type</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Montant</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Jours retard</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($penalites as $p): ?>
          <?php $c = $p['statut'] === 'en_attente' ? 'red' : ($p['statut'] === 'payee' ? 'green' : 'slate'); ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800"><?= htmlspecialchars(($p['prenom'] ?? '') . ' ' . ($p['user_nom'] ?? '')) ?></p>
              <p class="text-xs text-slate-400">Emprunt #<?= $p['emprunt_id'] ?></p>
            </td>
            <td class="px-4 py-3 capitalize"><?= htmlspecialchars($p['type']) ?></td>
            <td class="px-4 py-3 font-semibold text-slate-800"><?= number_format((float)$p['montant'], 2) ?> €</td>
            <td class="px-4 py-3 text-slate-600"><?= !empty($p['jours_retard']) ? $p['jours_retard'] . ' j' : '—' ?></td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs bg-<?= $c ?>-100 text-<?= $c ?>-700 capitalize"><?= $p['statut'] === 'en_attente' ? 'Impayée' : ucfirst($p['statut']) ?></span>
            </td>
            <td class="px-4 py-3">
              <?php if ($p['statut'] === 'en_attente' && ($mode ?? '') !== 'mes'): ?>
              <div class="flex gap-2 justify-end">
                <button onclick="payer(<?= $p['id'] ?>)" class="text-xs text-green-600 hover:underline">Payer</button>
                <button onclick="annuler(<?= $p['id'] ?>)" class="text-xs text-red-500 hover:underline">Annuler</button>
              </div>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

</div>
<script>
function payer(id) {
  if (!confirm('Marquer comme payée ?')) return;
  fetch('<?= BASE_URL ?>/v2/bibliotheque/penalites/' + id + '/payer', {method:'POST'}).then(()=>location.reload());
}
function annuler(id) {
  if (!confirm('Annuler cette pénalité ?')) return;
  fetch('<?= BASE_URL ?>/v2/bibliotheque/penalites/' + id + '/annuler', {method:'POST'}).then(()=>location.reload());
}
</script>
