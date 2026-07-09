<?php /** @var string $titre */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Nouvel emprunt') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-lg mx-auto py-8 px-4">

  <div class="mb-4">
    <a href="/v2/bibliotheque/emprunts" class="text-sm text-violet-600 hover:underline">← Emprunts</a>
  </div>

  <div class="bg-white rounded-xl shadow-sm p-6">
    <h1 class="text-xl font-bold text-slate-800 mb-6">Nouvel emprunt</h1>

    <div id="error" class="hidden bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-4"></div>

    <div class="space-y-4">
      <!-- Scan exemplaire -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Code-barres / N° inventaire <span class="text-red-500">*</span></label>
        <div class="flex gap-2">
          <input type="text" id="barcode" placeholder="Scanner ou saisir le code..."
                 class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-violet-400 focus:outline-none">
          <button onclick="lookupBarcode()" class="bg-slate-100 text-slate-700 text-sm px-3 py-2 rounded-lg hover:bg-slate-200">Chercher</button>
        </div>
        <input type="hidden" id="exemplaire_id">
        <div id="exemplaire_info" class="hidden mt-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800"></div>
      </div>

      <!-- Emprunteur -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">ID emprunteur <span class="text-red-500">*</span></label>
        <input type="number" id="user_id" placeholder="ID de l'élève ou du professeur"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
      </div>

      <!-- Date retour -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de retour prévue <span class="text-red-500">*</span></label>
        <input type="date" id="date_retour_prevue"
               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               value="<?= date('Y-m-d', strtotime('+14 days')) ?>"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
        <textarea id="notes" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none"></textarea>
      </div>

      <button onclick="creerEmprunt()" class="w-full bg-violet-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-violet-700">
        Créer l'emprunt
      </button>
    </div>
  </div>

</div>
<script>
function lookupBarcode() {
  const code = document.getElementById('barcode').value.trim();
  if (!code) return;
  fetch('/v2/bibliotheque/exemplaires/barcode?code=' + encodeURIComponent(code))
    .then(r => r.json())
    .then(d => {
      if (d && d.id) {
        document.getElementById('exemplaire_id').value = d.id;
        document.getElementById('exemplaire_info').textContent = '✓ ' + (d.ouvrage_titre || 'Exemplaire trouvé') + ' — ' + d.numero_inventaire;
        document.getElementById('exemplaire_info').classList.remove('hidden');
      } else {
        document.getElementById('exemplaire_info').textContent = '✗ Exemplaire non trouvé';
        document.getElementById('exemplaire_info').className = 'mt-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
        document.getElementById('exemplaire_info').classList.remove('hidden');
      }
    });
}

document.getElementById('barcode').addEventListener('keydown', e => { if (e.key === 'Enter') lookupBarcode(); });

function creerEmprunt() {
  const data = new URLSearchParams({
    exemplaire_id: document.getElementById('exemplaire_id').value,
    user_id: document.getElementById('user_id').value,
    date_retour_prevue: document.getElementById('date_retour_prevue').value,
    notes: document.getElementById('notes').value,
    csrf_token: '',
  });
  fetch('/v2/bibliotheque/emprunts', {method:'POST', body: data})
    .then(r => r.json())
    .then(d => {
      if (d.success) window.location.href = '/v2/bibliotheque/emprunts/' + d.id;
      else { document.getElementById('error').textContent = d.error || 'Erreur'; document.getElementById('error').classList.remove('hidden'); }
    });
}
</script>
</body>
</html>
