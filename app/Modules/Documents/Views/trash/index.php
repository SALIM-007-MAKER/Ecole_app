<?php
/** @var array $corbeille @var bool $canRestore @var bool $canPurge */
$titre = 'Corbeille';
?>
<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Corbeille</h1>
      <p class="text-sm text-slate-500 mt-1"><?= count($corbeille) ?> document(s) dans la corbeille</p>
    </div>
    <?php if ($canPurge && !empty($corbeille)): ?>
    <button onclick="viderCorbeille()"
            class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
      Vider la corbeille
    </button>
    <?php endif; ?>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Document</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Module</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Mis en corbeille</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($corbeille as $doc): ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($doc['titre']) ?></td>
          <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($doc['module_source']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y H:i', strtotime($doc['updated_at'])) ?></td>
          <td class="px-4 py-3 text-right space-x-2">
            <?php if ($canRestore): ?>
            <button onclick="restaurer(<?= $doc['id'] ?>)"
                    class="text-xs text-green-600 hover:text-green-800 font-medium">Restaurer</button>
            <?php endif; ?>
            <?php if ($canPurge): ?>
            <button onclick="purger(<?= $doc['id'] ?>)"
                    class="text-xs text-red-600 hover:text-red-800 font-medium">Supprimer définitivement</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($corbeille)): ?>
        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">La corbeille est vide.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';

async function restaurer(id) {
  const r = await fetch(`/v2/trash/${id}/restore`, {method:'POST', headers:{'X-CSRF-Token': csrf}});
  const d = await r.json();
  if (d.success) location.reload();
}

async function purger(id) {
  if (!confirm('Supprimer définitivement ? Cette action est irréversible.')) return;
  const r = await fetch(`/v2/trash/${id}/purge`, {method:'POST', headers:{'X-CSRF-Token': csrf}});
  const d = await r.json();
  if (d.success) location.reload();
}

async function viderCorbeille() {
  if (!confirm('Vider toute la corbeille ? Cette action est irréversible.')) return;
  const r = await fetch('<?= BASE_URL ?>/v2/trash/purge-all', {method:'POST', headers:{'X-CSRF-Token': csrf}});
  const d = await r.json();
  if (d.success) location.reload();
}
</script>
