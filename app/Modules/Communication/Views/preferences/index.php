<?php
/** @var array $preferences @var array $grouped */
$types = [
  'note_saisie'       => 'Nouvelle note saisie',
  'bulletin_disponible' => 'Bulletin disponible',
  'facture_emise'     => 'Facture émise',
  'paiement_recu'     => 'Paiement reçu',
  'paiement_retard'   => 'Rappel paiement en retard',
  'absence_signal'    => 'Absence signalée',
  'retard_signal'     => 'Retard signalé',
  'sanction_prononcee'=> 'Sanction prononcée',
  'conge_approuve'    => 'Congé approuvé',
  'contrat_expire'    => 'Contrat expirant',
  'document_partage'  => 'Document partagé',
  'message_recu'      => 'Message reçu',
];
$canaux = ['internal', 'email', 'push'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($titre ?? 'Préférences') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-3xl mx-auto py-8 px-4">
  <h1 class="text-2xl font-bold text-slate-800 mb-2">Préférences de notification</h1>
  <p class="text-sm text-slate-500 mb-6">Choisissez les notifications que vous souhaitez recevoir et sur quels canaux.</p>

  <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="text-left px-4 py-3 text-xs text-slate-500 uppercase">Type de notification</th>
          <?php foreach ($canaux as $c): ?>
          <th class="px-4 py-3 text-xs text-slate-500 uppercase text-center"><?= strtoupper($c) ?></th>
          <?php endforeach ?>
          <th class="px-4 py-3 text-xs text-slate-500 uppercase text-center">Activé</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($types as $type => $label): ?>
        <?php
          $pref = $grouped[$type] ?? null;
          $activeCanaux = $pref ? json_decode($pref['canaux'] ?? '["internal"]', true) : ['internal'];
          $enabled = $pref ? (bool)$pref['actif'] : true;
        ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($label) ?></td>
          <?php foreach ($canaux as $c): ?>
          <td class="px-4 py-3 text-center">
            <input type="checkbox" class="canal-check rounded accent-violet-600"
                   data-type="<?= $type ?>" data-canal="<?= $c ?>"
                   <?= in_array($c, $activeCanaux, true) && $enabled ? 'checked' : '' ?>
                   <?= !$enabled ? 'disabled' : '' ?>>
          </td>
          <?php endforeach ?>
          <td class="px-4 py-3 text-center">
            <input type="checkbox" class="enable-check accent-violet-600"
                   data-type="<?= $type ?>" <?= $enabled ? 'checked' : '' ?>>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <button id="save-btn" class="mt-6 bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">
    Sauvegarder mes préférences
  </button>
  <span id="saved-msg" class="hidden ml-3 text-green-600 text-sm">Préférences enregistrées ✓</span>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

document.querySelectorAll('.enable-check').forEach(cb => {
  cb.addEventListener('change', () => {
    const type = cb.dataset.type;
    document.querySelectorAll(`.canal-check[data-type="${type}"]`).forEach(c => {
      c.disabled = !cb.checked;
      if (!cb.checked) c.checked = false;
    });
  });
});

document.getElementById('save-btn').addEventListener('click', async () => {
  const prefs = {};
  document.querySelectorAll('.enable-check').forEach(cb => {
    const type = cb.dataset.type;
    const canaux = [];
    if (cb.checked) {
      document.querySelectorAll(`.canal-check[data-type="${type}"]:checked`).forEach(c => canaux.push(c.dataset.canal));
    }
    prefs[type] = { actif: cb.checked ? 1 : 0, canaux };
  });

  const r = await fetch('/v2/notifications/preferences', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' },
    body: JSON.stringify({ preferences: prefs })
  });
  if ((await r.json()).success) {
    document.getElementById('saved-msg').classList.remove('hidden');
    setTimeout(() => document.getElementById('saved-msg').classList.add('hidden'), 3000);
  }
});
</script>
</body>
</html>
