<?php /** @var array $emprunts @var int $page @var string|null $filtre @var string $titre */ ?>
<div class="max-w-6xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($titre ?? '') ?></h1>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/bibliotheque/emprunts" class="text-sm px-3 py-1.5 rounded-lg border <?= empty($filtre) ? 'bg-violet-600 text-white border-violet-600' : 'bg-white border-slate-200 text-slate-600' ?>">En cours</a>
      <a href="<?= BASE_URL ?>/v2/bibliotheque/emprunts/en-retard" class="text-sm px-3 py-1.5 rounded-lg border <?= ($filtre ?? '') === 'retard' ? 'bg-red-600 text-white border-red-600' : 'bg-white border-slate-200 text-slate-600' ?>">En retard</a>
      <a href="<?= BASE_URL ?>/v2/bibliotheque/emprunts/create" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">+ Nouvel emprunt</a>
    </div>
  </div>

  <?php $liste = $emprunts['data'] ?? $emprunts; ?>
  <?php if (empty($liste)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p class="text-4xl mb-3">📋</p>
      <p>Aucun emprunt</p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Emprunteur</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Ouvrage</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Emprunté le</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Retour prévu</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($liste as $e): ?>
          <?php $retard = $e['statut'] === 'en_retard'; $jours = $retard ? (int)(new \DateTime())->diff(new \DateTime($e['date_retour_prevue']))->days : 0; ?>
          <tr class="hover:bg-slate-50 <?= $retard ? 'bg-red-50' : '' ?>">
            <td class="px-4 py-3">
              <p class="font-medium text-slate-800"><?= htmlspecialchars(($e['prenom'] ?? '') . ' ' . ($e['user_nom'] ?? '')) ?></p>
            </td>
            <td class="px-4 py-3">
              <p class="text-slate-800"><?= htmlspecialchars($e['ouvrage_titre'] ?? '—') ?></p>
              <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($e['numero_inventaire'] ?? '') ?></p>
            </td>
            <td class="px-4 py-3 text-slate-600"><?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></td>
            <td class="px-4 py-3 <?= $retard ? 'text-red-600 font-semibold' : 'text-slate-600' ?>">
              <?= date('d/m/Y', strtotime($e['date_retour_prevue'])) ?>
              <?php if ($retard): ?><span class="text-xs ml-1">(+<?= $jours ?>j)</span><?php endif ?>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs <?= $retard ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' ?>">
                <?= $e['statut'] === 'en_retard' ? 'En retard' : 'En cours' ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2 justify-end">
                <a href="<?= BASE_URL ?>/v2/bibliotheque/emprunts/<?= $e['id'] ?>" class="text-xs text-violet-600 hover:underline">Détail</a>
                <button onclick="retour(<?= $e['id'] ?>)" class="text-xs text-green-600 hover:underline">Retour</button>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

</div>
<script>
function retour(id) {
  if (!confirm('Enregistrer le retour ?')) return;
  fetch('<?= BASE_URL ?>/v2/bibliotheque/emprunts/' + id + '/retour', {method:'POST'}).then(()=>location.reload());
}
</script>
