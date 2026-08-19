<?php
/** @var array $arbre @var string|null $moduleSource @var bool $canCreate @var bool $canDelete */
$titre = 'Dossiers';

function renderFolderNode(array $node, bool $canCreate, bool $canDelete): void {
    $hasChildren = !empty($node['children']); ?>
    <div class="pl-4 border-l border-slate-200 mt-1">
      <div class="flex items-center justify-between group py-1">
        <a href="<?= BASE_URL ?>/v2/documents?folder_id=<?= $node['id'] ?>"
           class="flex items-center gap-2 text-sm text-slate-700 hover:text-violet-700 font-medium">
          <svg class="w-4 h-4 text-<?= htmlspecialchars($node['couleur'] ?? 'slate') ?>-500 shrink-0"
               fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
          </svg>
          <?= htmlspecialchars($node['nom']) ?>
        </a>
        <?php if ($canDelete): ?>
        <button onclick="supprimerDossier(<?= $node['id'] ?>)"
                class="hidden group-hover:block text-xs text-red-500 hover:text-red-700 px-2 py-0.5 rounded">✕</button>
        <?php endif; ?>
      </div>
      <?php if ($hasChildren): ?>
        <?php foreach ($node['children'] as $child): renderFolderNode($child, $canCreate, $canDelete); endforeach; ?>
      <?php endif; ?>
    </div>
<?php }
?>
<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-800">Dossiers</h1>
    <?php if ($canCreate): ?>
    <button onclick="document.getElementById('modal-folder').classList.remove('hidden')"
            class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
      + Nouveau dossier
    </button>
    <?php endif; ?>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <?php if (empty($arbre)): ?>
    <p class="text-sm text-slate-400">Aucun dossier.<?= $canCreate ? ' Créez-en un ci-dessus.' : '' ?></p>
    <?php else: ?>
      <?php foreach ($arbre as $root): renderFolderNode($root, $canCreate, $canDelete); endforeach; ?>
    <?php endif; ?>
  </div>

  <a href="<?= BASE_URL ?>/v2/documents<?= $moduleSource ? '?module_source=' . urlencode($moduleSource) : '' ?>"
     class="text-sm text-violet-600 hover:text-violet-800 font-medium">← Documents</a>
</div>

<!-- Modal création dossier -->
<div id="modal-folder" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
    <h2 class="text-lg font-bold text-slate-800 mb-4">Nouveau dossier</h2>
    <form id="form-folder" class="space-y-3">
      <input type="hidden" name="module_source" value="<?= htmlspecialchars($moduleSource ?? 'general') ?>">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nom *</label>
        <input type="text" name="nom" required
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
        <input type="text" name="description"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      </div>
      <div class="flex gap-3 justify-end pt-2">
        <button type="button" onclick="document.getElementById('modal-folder').classList.add('hidden')"
                class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-600">Annuler</button>
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium">Créer</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('form-folder').addEventListener('submit', async function(e) {
  e.preventDefault();
  const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const r = await fetch('<?= BASE_URL ?>/v2/folders', {
    method: 'POST',
    headers: {'X-CSRF-Token': csrf},
    body: new FormData(this),
  });
  const d = await r.json();
  if (d.success) { location.reload(); }
  else { alert(JSON.stringify(d.errors ?? d)); }
});

async function supprimerDossier(id) {
  if (!confirm('Supprimer ce dossier ?')) return;
  const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const r = await fetch(`/v2/folders/${id}`, { method: 'DELETE', headers: {'X-CSRF-Token': csrf} });
  const d = await r.json();
  if (d.success) { location.reload(); }
  else { alert(d.message ?? 'Erreur'); }
}
</script>
