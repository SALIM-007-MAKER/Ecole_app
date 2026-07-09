<?php /** @var array $session @var array $lignes @var array $rapport @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Inventaire') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-4xl mx-auto py-8 px-4">

  <div class="mb-4 flex items-center justify-between">
    <a href="/v2/bibliotheque/inventaire" class="text-sm text-violet-600 hover:underline">← Sessions</a>
    <?php if ($session['statut'] === 'en_cours'): ?>
    <button onclick="terminer()" class="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700">Clôturer la session</button>
    <?php endif ?>
  </div>

  <h1 class="text-xl font-bold text-slate-800 mb-4"><?= htmlspecialchars($titre ?? '') ?></h1>

  <!-- Rapport résumé -->
  <div class="grid grid-cols-4 gap-3 mb-6">
    <?php
    $stats = [
      ['label' => 'Présents', 'val' => $rapport['present'] ?? 0, 'color' => 'green'],
      ['label' => 'Manquants', 'val' => $rapport['manquant'] ?? 0, 'color' => 'red'],
      ['label' => 'Détériorés', 'val' => $rapport['deteriore'] ?? 0, 'color' => 'orange'],
      ['label' => 'Total scanné', 'val' => array_sum($rapport), 'color' => 'violet'],
    ];
    foreach ($stats as $s): ?>
    <div class="bg-white rounded-xl shadow-sm p-4 text-center">
      <p class="text-2xl font-bold text-<?= $s['color'] ?>-600"><?= $s['val'] ?></p>
      <p class="text-xs text-slate-500 mt-1"><?= $s['label'] ?></p>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Zone scan -->
  <?php if ($session['statut'] === 'en_cours'): ?>
  <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
    <h2 class="font-semibold text-slate-800 mb-3">Scanner un exemplaire</h2>
    <div id="scanError" class="hidden text-red-600 text-sm mb-3"></div>
    <div id="scanSuccess" class="hidden text-green-600 text-sm mb-3"></div>
    <div class="flex gap-3 items-end">
      <div class="flex-1">
        <label class="block text-xs font-medium text-slate-600 mb-1">Code-barres / N° inventaire</label>
        <input type="text" id="scanCode" autofocus
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-violet-400 focus:outline-none"
               placeholder="Scanner le code...">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">État constaté</label>
        <select id="scanStatut" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
          <option value="present">Présent</option>
          <option value="deteriore">Détérioré</option>
          <option value="perdu">Perdu</option>
        </select>
      </div>
      <button onclick="scanner()" class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-violet-700 h-9">Scanner</button>
    </div>
  </div>
  <?php endif ?>

  <!-- Liste lignes -->
  <?php if (!empty($lignes)): ?>
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b flex items-center justify-between">
      <h2 class="font-semibold text-slate-700"><?= count($lignes) ?> exemplaires scannés</h2>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b">
        <tr>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">N° inventaire</th>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">Ouvrage</th>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">État constaté</th>
          <th class="text-left px-4 py-2 text-slate-600 font-medium">Scanné le</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($lignes as $l): ?>
        <?php $c = match($l['statut_constate'] ?? '') { 'present' => 'green', 'deteriore' => 'orange', 'perdu', 'manquant' => 'red', default => 'slate' }; ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-2 font-mono text-xs"><?= htmlspecialchars($l['numero_inventaire'] ?? '—') ?></td>
          <td class="px-4 py-2 text-slate-700"><?= htmlspecialchars($l['ouvrage_titre'] ?? '—') ?></td>
          <td class="px-4 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-<?= $c ?>-100 text-<?= $c ?>-700 capitalize"><?= $l['statut_constate'] ?? '—' ?></span></td>
          <td class="px-4 py-2 text-slate-400 text-xs"><?= !empty($l['scanned_at']) ? date('d/m H:i', strtotime($l['scanned_at'])) : '—' ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>

</div>
<script>
const sessionId = <?= (int)$session['id'] ?>;

document.getElementById('scanCode')?.addEventListener('keydown', e => { if (e.key === 'Enter') scanner(); });

function scanner() {
  const code = document.getElementById('scanCode').value.trim();
  if (!code) return;
  const data = new URLSearchParams({
    code, statut: document.getElementById('scanStatut').value, csrf_token: '',
  });
  fetch('/v2/bibliotheque/inventaire/' + sessionId + '/scan', {method:'POST', body:data})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        document.getElementById('scanSuccess').textContent = '✓ Scanné : ' + code;
        document.getElementById('scanSuccess').classList.remove('hidden');
        document.getElementById('scanError').classList.add('hidden');
        document.getElementById('scanCode').value = '';
        document.getElementById('scanCode').focus();
        setTimeout(() => location.reload(), 1500);
      } else {
        document.getElementById('scanError').textContent = d.error;
        document.getElementById('scanError').classList.remove('hidden');
      }
    });
}

function terminer() {
  if (!confirm('Clôturer la session ? Cette action est irréversible.')) return;
  fetch('/v2/bibliotheque/inventaire/' + sessionId + '/terminer', {method:'POST'})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.error); });
}
</script>
</body>
</html>
