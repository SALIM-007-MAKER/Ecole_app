<?php /** @var array $sessions @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Inventaire') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-4xl mx-auto py-8 px-4">

  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Inventaire</h1>
    <button onclick="showNewSession()" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">+ Nouvelle session</button>
  </div>

  <!-- Formulaire nouvelle session -->
  <div id="newSession" class="hidden bg-white rounded-xl shadow-sm p-4 mb-6">
    <h2 class="font-semibold text-slate-800 mb-4">Nouvelle session d'inventaire</h2>
    <div id="sessionError" class="hidden text-red-600 text-sm mb-3"></div>
    <div class="grid grid-cols-2 gap-3">
      <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-600 mb-1">Nom <span class="text-red-500">*</span></label>
        <input type="text" id="inv_nom" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none" placeholder="Inventaire annuel 2026">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Date début</label>
        <input type="date" id="inv_debut" value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Date fin prévue</label>
        <input type="date" id="inv_fin" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
      </div>
      <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
        <textarea id="inv_desc" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none"></textarea>
      </div>
      <div class="col-span-2 flex gap-2">
        <button onclick="lancerSession()" class="bg-violet-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-violet-700">Lancer</button>
        <button type="button" onclick="document.getElementById('newSession').classList.add('hidden')" class="text-sm px-4 py-2 rounded-lg border hover:border-slate-400">Annuler</button>
      </div>
    </div>
  </div>

  <!-- Liste sessions -->
  <?php if (empty($sessions)): ?>
    <div class="bg-white rounded-xl p-12 text-center text-slate-400 shadow-sm">
      <p class="text-4xl mb-3">📦</p>
      <p>Aucune session d'inventaire</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($sessions as $s): ?>
      <?php $en_cours = $s['statut'] === 'en_cours'; ?>
      <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:border-violet-200 transition">
        <div class="flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <p class="font-semibold text-slate-800"><?= htmlspecialchars($s['nom']) ?></p>
              <span class="text-xs px-2 py-0.5 rounded-full <?= $en_cours ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>">
                <?= $en_cours ? 'En cours' : 'Terminé' ?>
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
              Du <?= date('d/m/Y', strtotime($s['date_debut'])) ?>
              <?php if (!empty($s['date_fin_prevue'])): ?> au <?= date('d/m/Y', strtotime($s['date_fin_prevue'])) ?><?php endif ?>
            </p>
          </div>
          <a href="/v2/bibliotheque/inventaire/<?= $s['id'] ?>" class="text-sm text-violet-600 hover:underline">
            <?= $en_cours ? 'Scanner →' : 'Voir rapport' ?>
          </a>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

</div>
<script>
function showNewSession() { document.getElementById('newSession').classList.remove('hidden'); }
function lancerSession() {
  const nom = document.getElementById('inv_nom').value.trim();
  if (!nom) { document.getElementById('sessionError').textContent = 'Le nom est requis'; document.getElementById('sessionError').classList.remove('hidden'); return; }
  const data = new URLSearchParams({
    nom, description: document.getElementById('inv_desc').value,
    date_debut: document.getElementById('inv_debut').value,
    date_fin_prevue: document.getElementById('inv_fin').value,
    csrf_token: '',
  });
  fetch('/v2/bibliotheque/inventaire', {method:'POST', body:data})
    .then(r=>r.json())
    .then(d => {
      if (d.success) window.location.href = '/v2/bibliotheque/inventaire/' + d.id;
      else { document.getElementById('sessionError').textContent = d.error; document.getElementById('sessionError').classList.remove('hidden'); }
    });
}
</script>
</body>
</html>
