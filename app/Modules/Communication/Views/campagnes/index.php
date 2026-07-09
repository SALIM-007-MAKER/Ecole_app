<?php
/** @var array $campagnes @var int $page */
use App\Modules\Communication\Models\CampagneModel;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Campagnes') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-5xl mx-auto py-8 px-4">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Campagnes de communication</h1>
    <button onclick="document.getElementById('modal-campagne').classList.remove('hidden')"
            class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">
      + Nouvelle campagne
    </button>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="text-left px-4 py-3">Nom</th>
          <th class="text-left px-4 py-3">Statut</th>
          <th class="text-left px-4 py-3">Destinataires</th>
          <th class="text-left px-4 py-3">Date</th>
          <th class="px-4 py-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($campagnes as $c): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($c['nom']) ?></td>
          <td class="px-4 py-3">
            <span class="text-xs px-2 py-0.5 rounded-full
              <?= match($c['statut']) {
                'terminee'  => 'bg-green-100 text-green-700',
                'en_cours'  => 'bg-blue-100 text-blue-700',
                'planifiee' => 'bg-yellow-100 text-yellow-700',
                'annulee'   => 'bg-red-100 text-red-700',
                default     => 'bg-slate-100 text-slate-600'
              } ?>">
              <?= CampagneModel::labelStatut($c['statut']) ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-600">
            <?= $c['total_envoyes'] ?>/<?= $c['total_destinataires'] ?>
          </td>
          <td class="px-4 py-3 text-slate-400 text-xs">
            <?= $c['lance_at'] ? date('d/m/Y', strtotime($c['lance_at'])) : '—' ?>
          </td>
          <td class="px-4 py-3 text-right space-x-2">
            <a href="/v2/communication/campagnes/<?= $c['id'] ?>" class="text-violet-600 text-xs hover:underline">Détails</a>
            <?php if (CampagneModel::peutEtreModifiee($c['statut'])): ?>
            <button onclick="lancerCampagne(<?= $c['id'] ?>)" class="text-green-600 text-xs hover:underline">Lancer</button>
            <?php endif ?>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($campagnes)): ?>
        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucune campagne</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nouvelle campagne -->
<div id="modal-campagne" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-lg">
    <h3 class="font-semibold text-slate-800 mb-4">Nouvelle campagne</h3>
    <form id="form-campagne" class="space-y-3">
      <input name="nom" placeholder="Nom de la campagne" required
             class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
      <select name="cible_type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        <option value="tous">Tous les utilisateurs</option>
        <option value="role">Par rôle</option>
        <option value="groupe">Groupe de diffusion</option>
      </select>
      <div class="flex gap-2">
        <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="canaux[]" value="internal" checked> Interne</label>
        <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="canaux[]" value="email"> Email</label>
        <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="canaux[]" value="push"> Push</label>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-violet-600 text-white py-2 rounded-lg text-sm font-medium">Créer</button>
        <button type="button" onclick="document.getElementById('modal-campagne').classList.add('hidden')"
                class="flex-1 border border-slate-200 text-slate-600 py-2 rounded-lg text-sm">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

document.getElementById('form-campagne').addEventListener('submit', async (e) => {
  e.preventDefault();
  const r = await fetch('/v2/communication/campagnes', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: new URLSearchParams(new FormData(e.target))
  });
  if ((await r.json()).success) location.reload();
});

async function lancerCampagne(id) {
  if (!confirm('Lancer cette campagne ?')) return;
  const r = await fetch(`/v2/communication/campagnes/${id}/launch`, {
    method: 'POST', headers: { 'X-CSRF-Token': csrf }
  });
  const j = await r.json();
  if (j.success) location.reload();
  else alert('Erreur lors du lancement.');
}
</script>
</body>
</html>
