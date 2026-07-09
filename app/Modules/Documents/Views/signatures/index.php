<?php
/** @var array $document @var array $signataires @var bool $canRequest */
$titre = 'Signatures — ' . htmlspecialchars($document['titre']);
?>
<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <a href="/v2/documents/<?= $document['id'] ?>" class="text-sm text-violet-600 hover:text-violet-800 font-medium">← Retour</a>
      <h1 class="text-2xl font-bold text-slate-800 mt-1">Signatures</h1>
      <p class="text-sm text-slate-500"><?= htmlspecialchars($document['titre']) ?></p>
    </div>
    <?php if ($canRequest): ?>
    <button onclick="document.getElementById('modal-sign').classList.remove('hidden')"
            class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
      Demander une signature
    </button>
    <?php endif; ?>
  </div>

  <!-- Statut global -->
  <?php
  $statut = $document['signature_statut'] ?? 'non_requis';
  $colors = ['non_requis'=>'slate','en_attente'=>'amber','partiel'=>'blue','complet'=>'green'];
  $labels = ['non_requis'=>'Non requis','en_attente'=>'En attente','partiel'=>'Partiel','complet'=>'Complet'];
  $c = $colors[$statut] ?? 'slate';
  ?>
  <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium bg-<?= $c ?>-100 text-<?= $c ?>-700">
    <?= $labels[$statut] ?? $statut ?>
  </div>

  <!-- Liste signataires -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 text-sm font-semibold text-slate-700">
      Signataires (<?= count($signataires) ?>)
    </div>
    <?php if (empty($signataires)): ?>
    <p class="px-5 py-6 text-sm text-slate-400">Aucune signature demandée.</p>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="text-left px-4 py-2 font-medium text-slate-600">Signataire</th>
          <th class="text-left px-4 py-2 font-medium text-slate-600">Statut</th>
          <th class="text-left px-4 py-2 font-medium text-slate-600">Signé le</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($signataires as $sig): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800">
            <?= htmlspecialchars($sig['signataire_nom'] ?? $sig['signataire_email'] ?? '—') ?>
          </td>
          <td class="px-4 py-3">
            <?php
            $sc = ['en_attente'=>'amber','signe'=>'green','refuse'=>'red','expire'=>'slate'];
            $sl = ['en_attente'=>'En attente','signe'=>'Signé','refuse'=>'Refusé','expire'=>'Expiré'];
            $ss = $sig['statut'] ?? 'en_attente';
            ?>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $sc[$ss]??'slate' ?>-100 text-<?= $sc[$ss]??'slate' ?>-700">
              <?= $sl[$ss] ?? $ss ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-500">
            <?= $sig['signed_at'] ? date('d/m/Y H:i', strtotime($sig['signed_at'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<!-- Modal demande signature -->
<div id="modal-sign" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6">
    <h2 class="text-lg font-bold text-slate-800 mb-4">Demander une signature</h2>
    <form id="form-sign" class="space-y-3">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Email du signataire</label>
        <input type="email" name="signataire_email" required
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
        <input type="text" name="signataire_nom"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      </div>
      <div class="flex gap-3 justify-end pt-2">
        <button type="button" onclick="document.getElementById('modal-sign').classList.add('hidden')"
                class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-600">Annuler</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium">Envoyer</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('form-sign').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const signataires = [{ email: fd.get('signataire_email'), nom: fd.get('signataire_nom'), type: 'externe' }];
  const r = await fetch('/v2/signatures/<?= $document['id'] ?>/request', {
    method: 'POST',
    headers: {'Content-Type':'application/json','X-CSRF-Token': csrf},
    body: JSON.stringify({ signataires }),
  });
  const d = await r.json();
  if (d.success) { location.reload(); }
  else { alert(JSON.stringify(d.errors ?? d)); }
});
</script>
