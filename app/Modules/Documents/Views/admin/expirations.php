<?php
/** @var array $expiring @var array $expired */
$titre = 'Administration — Expirations';
?>
<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-800">Gestion des expirations</h1>
    <button onclick="lancerVerification()"
            class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700">
      Vérifier maintenant
    </button>
  </div>

  <!-- Bientôt expirés -->
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="text-base font-semibold text-slate-700 mb-3">
      Expiration dans les 30 jours (<?= count($expiring) ?>)
    </h2>
    <?php if (empty($expiring)): ?>
    <p class="text-sm text-slate-400">Aucun document en cours d'expiration.</p>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="text-left px-3 py-2 font-medium text-slate-600">Titre</th>
        <th class="text-left px-3 py-2 font-medium text-slate-600">Module</th>
        <th class="text-left px-3 py-2 font-medium text-slate-600">Expiration</th>
        <th class="px-3 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($expiring as $d): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-3 py-2 font-medium text-slate-800"><?= htmlspecialchars($d['titre']) ?></td>
          <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($d['module_source']) ?></td>
          <td class="px-3 py-2 text-amber-700 font-medium"><?= date('d/m/Y', strtotime($d['date_expiration'])) ?></td>
          <td class="px-3 py-2 text-right">
            <a href="<?= BASE_URL ?>/v2/documents/<?= $d['id'] ?>" class="text-xs text-violet-600 hover:text-violet-800">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Déjà expirés -->
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="text-base font-semibold text-slate-700 mb-3">
      Documents expirés (<?= count($expired) ?>)
    </h2>
    <?php if (empty($expired)): ?>
    <p class="text-sm text-slate-400">Aucun document expiré.</p>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="text-left px-3 py-2 font-medium text-slate-600">Titre</th>
        <th class="text-left px-3 py-2 font-medium text-slate-600">Module</th>
        <th class="text-left px-3 py-2 font-medium text-slate-600">Expiré le</th>
        <th class="px-3 py-2"></th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($expired as $d): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-3 py-2 font-medium text-slate-800"><?= htmlspecialchars($d['titre']) ?></td>
          <td class="px-3 py-2 text-slate-600"><?= htmlspecialchars($d['module_source']) ?></td>
          <td class="px-3 py-2 text-red-700"><?= date('d/m/Y', strtotime($d['date_expiration'])) ?></td>
          <td class="px-3 py-2 text-right">
            <a href="<?= BASE_URL ?>/v2/documents/<?= $d['id'] ?>" class="text-xs text-violet-600 hover:text-violet-800">Voir</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>
async function lancerVerification() {
  const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const r = await fetch('<?= BASE_URL ?>/v2/documents/admin/expire-check', {method:'POST', headers:{'X-CSRF-Token': csrf}});
  const d = await r.json();
  if (d.success) { alert(d.processed + ' document(s) traité(s).'); location.reload(); }
}
</script>
