<?php /** @var array $groupes */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Diffusion') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-4xl mx-auto py-8 px-4">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Diffusion & Groupes</h1>
    <a href="/v2/communication/diffuser" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700">
      + Diffuser un message
    </a>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex justify-between items-center">
      <h2 class="font-semibold text-slate-700">Groupes de diffusion (<?= count($groupes) ?>)</h2>
      <button onclick="document.getElementById('modal-groupe').classList.remove('hidden')"
              class="text-sm text-violet-600 hover:underline">+ Nouveau groupe</button>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="text-left px-4 py-3">Nom</th>
          <th class="text-left px-4 py-3">Type</th>
          <th class="text-left px-4 py-3">Statut</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($groupes as $g): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($g['nom']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($g['type']) ?></td>
          <td class="px-4 py-3">
            <span class="<?= $g['actif'] ? 'text-green-600' : 'text-red-500' ?> text-xs font-medium">
              <?= $g['actif'] ? 'Actif' : 'Inactif' ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <button onclick="supprimerGroupe(<?= $g['id'] ?>)" class="text-red-400 text-xs hover:text-red-600">Supprimer</button>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($groupes)): ?>
        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Aucun groupe</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nouveau groupe -->
<div id="modal-groupe" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
    <h3 class="font-semibold text-slate-800 mb-4">Nouveau groupe</h3>
    <form id="form-groupe" class="space-y-3">
      <input name="nom" placeholder="Nom du groupe" required
             class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
      <select name="type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        <option value="manuel">Manuel</option>
        <option value="role">Par rôle</option>
        <option value="classe">Par classe</option>
      </select>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 bg-violet-600 text-white py-2 rounded-lg text-sm font-medium">Créer</button>
        <button type="button" onclick="document.getElementById('modal-groupe').classList.add('hidden')"
                class="flex-1 border border-slate-200 text-slate-600 py-2 rounded-lg text-sm">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

document.getElementById('form-groupe').addEventListener('submit', async (e) => {
  e.preventDefault();
  const r = await fetch('/v2/communication/groupes', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: new URLSearchParams(new FormData(e.target))
  });
  if ((await r.json()).success) location.reload();
});

async function supprimerGroupe(id) {
  if (!confirm('Supprimer ce groupe ?')) return;
  await fetch(`/v2/communication/groupes/${id}`, { method: 'DELETE', headers: { 'X-CSRF-Token': csrf } });
  location.reload();
}
</script>
</body>
</html>
