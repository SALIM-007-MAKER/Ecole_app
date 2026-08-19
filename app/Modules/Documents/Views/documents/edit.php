<?php
/** @var array $document @var array $categories @var array $folders */
$titre = 'Modifier — ' . htmlspecialchars($document['titre']);
?>
<div class="p-6 max-w-2xl mx-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Modifier le document</h1>
    <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($document['titre']) ?></p>
  </div>

  <form id="editForm" class="space-y-5 bg-white border border-slate-200 rounded-xl p-6">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
    <input type="hidden" name="module_source" value="<?= htmlspecialchars($document['module_source']) ?>">

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-red-500">*</span></label>
      <input type="text" name="titre" required
             value="<?= htmlspecialchars($document['titre']) ?>"
             class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-violet-500 focus:border-violet-500">
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie</label>
        <select name="categorie_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <option value="">— Aucune —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $document['categorie_id'] == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['libelle']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Dossier</label>
        <select name="folder_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <option value="">— Racine —</option>
          <?php foreach ($folders as $f): ?>
          <option value="<?= $f['id'] ?>" <?= $document['folder_id'] == $f['id'] ? 'selected' : '' ?>>
            <?= str_repeat('&nbsp;&nbsp;', ($f['depth'] ?? 0)) ?><?= htmlspecialchars($f['nom']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Confidentialité</label>
        <select name="confidentialite" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <?php foreach (['public','interne','confidentiel','secret'] as $c): ?>
          <option value="<?= $c ?>" <?= ($document['confidentialite'] ?? '') === $c ? 'selected' : '' ?>>
            <?= ucfirst($c) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date d'expiration</label>
        <input type="date" name="date_expiration"
               value="<?= htmlspecialchars($document['date_expiration'] ?? '') ?>"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
      <textarea name="description" rows="3"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm resize-none"><?= htmlspecialchars($document['description'] ?? '') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Référence externe</label>
      <input type="text" name="reference_externe"
             value="<?= htmlspecialchars($document['reference_externe'] ?? '') ?>"
             class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
    </div>

    <div id="errorsBox" class="hidden bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700"></div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="<?= BASE_URL ?>/v2/documents/<?= $document['id'] ?>" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm">Annuler</a>
      <button type="submit" class="px-5 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        Enregistrer
      </button>
    </div>
  </form>
</div>

<script>
document.getElementById('editForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('<?= BASE_URL ?>/v2/documents/<?= $document['id'] ?>', {method:'POST', body: fd,
    headers:{'X-HTTP-Method-Override':'PUT'}});
  const data = await res.json();
  if (data.success) {
    window.location = '<?= BASE_URL ?>/v2/documents/<?= $document['id'] ?>';
  } else {
    const box = document.getElementById('errorsBox');
    box.classList.remove('hidden');
    box.textContent = Object.values(data.errors || {}).join(' · ');
  }
});
</script>
