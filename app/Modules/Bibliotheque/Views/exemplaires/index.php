<?php /** @var int $ouvrageId @var array $exemplaires @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Exemplaires') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-4xl mx-auto py-8 px-4">

  <div class="mb-4 flex items-center justify-between">
    <a href="/v2/bibliotheque/catalogue/<?= $ouvrageId ?>" class="text-sm text-violet-600 hover:underline">← Retour à l'ouvrage</a>
    <button onclick="showAddForm()" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">+ Ajouter un exemplaire</button>
  </div>

  <h1 class="text-xl font-bold text-slate-800 mb-4"><?= htmlspecialchars($titre ?? '') ?></h1>

  <!-- Formulaire ajout -->
  <div id="addForm" class="hidden bg-white rounded-xl shadow-sm p-4 mb-4">
    <form method="POST" action="/v2/bibliotheque/exemplaires/<?= $ouvrageId ?>" class="grid grid-cols-2 gap-3">
      <input type="hidden" name="csrf_token" value="">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">N° inventaire (auto si vide)</label>
        <input type="text" name="numero_inventaire" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none font-mono">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Code-barres</label>
        <input type="text" name="code_barre" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none font-mono">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Localisation</label>
        <input type="text" name="localisation" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">État</label>
        <select name="etat" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
          <option value="bon" selected>Bon</option>
          <option value="use">Usé</option>
          <option value="deteriore">Détérioré</option>
        </select>
      </div>
      <div class="col-span-2 flex gap-2">
        <button type="submit" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">Ajouter</button>
        <button type="button" onclick="document.getElementById('addForm').classList.add('hidden')" class="text-sm px-4 py-2 rounded-lg border hover:border-slate-400">Annuler</button>
      </div>
    </form>
  </div>

  <!-- Liste -->
  <?php if (empty($exemplaires)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p>Aucun exemplaire enregistré</p>
    </div>
  <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">N° inventaire</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Code-barres</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Localisation</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">État</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($exemplaires as $ex): ?>
          <?php
            $colors = ['disponible'=>'green','emprunte'=>'blue','reserve'=>'amber','maintenance'=>'orange','perdu'=>'red','retire'=>'slate'];
            $c = $colors[$ex['statut']] ?? 'slate';
            $etatLabels = ['bon' => 'Bon', 'use' => 'Usé', 'deteriore' => 'Détérioré'];
            $etatLabel = $etatLabels[$ex['etat'] ?? ''] ?? ucfirst($ex['etat'] ?? '');
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($ex['numero_inventaire']) ?></td>
            <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($ex['code_barre'] ?? '—') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($ex['localisation'] ?? '—') ?></td>
            <td class="px-4 py-3"><?= htmlspecialchars($etatLabel) ?></td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?= $c ?>-100 text-<?= $c ?>-700 capitalize">
                <?= $ex['statut'] ?>
              </span>
            </td>
            <td class="px-4 py-3 flex gap-2 justify-end">
              <a href="/v2/bibliotheque/exemplaires/<?= $ex['id'] ?>/qrcode" target="_blank" class="text-xs text-violet-600 hover:underline">QR</a>
              <a href="/v2/bibliotheque/exemplaires/<?= $ex['id'] ?>/barcode" target="_blank" class="text-xs text-violet-600 hover:underline">BC</a>
              <button onclick="archiver(<?= $ex['id'] ?>)" class="text-xs text-red-500 hover:underline">Archiver</button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

</div>
<script>
function showAddForm() { document.getElementById('addForm').classList.remove('hidden'); }
function archiver(id) {
  if (!confirm('Archiver cet exemplaire ?')) return;
  fetch('/v2/bibliotheque/exemplaires/' + id + '/archive', {method:'POST'}).then(()=>location.reload());
}
</script>
</body>
</html>
