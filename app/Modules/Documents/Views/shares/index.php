<?php
/** @var array $document @var array $partages @var bool $canShare */
$titre = 'Partages — ' . htmlspecialchars($document['titre']);
?>
<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Partages</h1>
      <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($document['titre']) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/v2/documents/<?= $document['id'] ?>" class="text-sm text-slate-500 hover:text-slate-700">← Retour</a>
  </div>

  <?php if ($canShare): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="text-sm font-semibold text-slate-700 mb-3">Nouveau partage</h2>
    <form id="shareForm" class="flex flex-wrap gap-3">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
      <select name="destinataire_type" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <option value="user">Utilisateur</option>
        <option value="role">Rôle</option>
        <option value="externe">Lien externe</option>
      </select>
      <input type="number" name="destinataire_id" placeholder="ID utilisateur/rôle (vide pour externe)"
             class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-48">
      <select name="permission" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <option value="lecture">Lecture</option>
        <option value="telechargement">Téléchargement</option>
      </select>
      <input type="date" name="date_expiration" placeholder="Expiration (optionnel)"
             class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
      <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium">Partager</button>
    </form>
    <div id="shareResult" class="mt-2 text-sm hidden"></div>
  </div>
  <?php endif; ?>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Destinataire</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Permission</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Expiration</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($partages as $p): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 text-slate-700">
            <?= htmlspecialchars($p['destinataire_type']) ?>
            <?= $p['destinataire_id'] ? ' #' . $p['destinataire_id'] : ' (externe)' ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= ucfirst($p['permission']) ?></td>
          <td class="px-4 py-3 text-slate-500">
            <?= !empty($p['date_expiration']) ? date('d/m/Y', strtotime($p['date_expiration'])) : '—' ?>
          </td>
          <td class="px-4 py-3">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $p['revoked_at'] ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
              <?= $p['revoked_at'] ? 'Révoqué' : 'Actif' ?>
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <?php if (!$p['revoked_at']): ?>
            <button onclick="revoquer(<?= $p['id'] ?>)"
                    class="text-xs text-red-600 hover:text-red-800 font-medium">Révoquer</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($partages)): ?>
        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucun partage configuré.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const docId = <?= $document['id'] ?>;

document.getElementById('shareForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch(`/v2/documents/${docId}/shares`, {method:'POST', body: fd});
  const data = await res.json();
  const box = document.getElementById('shareResult');
  box.classList.remove('hidden');
  if (data.success) {
    box.className = 'mt-2 text-sm text-green-700';
    box.textContent = data.partage?.token ? 'Lien : /v2/share/' + data.partage.token : 'Partage créé.';
    setTimeout(() => location.reload(), 1500);
  } else {
    box.className = 'mt-2 text-sm text-red-700';
    box.textContent = Object.values(data.errors || {}).join(' · ');
  }
});

async function revoquer(id) {
  if (!confirm('Révoquer ce partage ?')) return;
  const r = await fetch(`/v2/shares/${id}/revoke`, {method:'POST', headers:{'X-CSRF-Token': csrf}});
  const d = await r.json();
  if (d.success) location.reload();
}
</script>
