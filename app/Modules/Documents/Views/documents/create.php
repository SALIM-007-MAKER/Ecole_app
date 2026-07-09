<?php
/** @var array $categories @var array $folders @var string $moduleSource */
$titre = 'Nouveau document';
?>
<div class="p-6 max-w-2xl mx-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Nouveau document</h1>
    <p class="text-sm text-slate-500 mt-1">Module : <?= htmlspecialchars($moduleSource) ?></p>
  </div>

  <form id="uploadForm" class="space-y-5 bg-white border border-slate-200 rounded-xl p-6">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <input type="hidden" name="module_source" value="<?= htmlspecialchars($moduleSource) ?>">

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-red-500">*</span></label>
      <input type="text" name="titre" required
             class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-violet-500 focus:border-violet-500">
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Fichier <span class="text-red-500">*</span></label>
      <input type="file" id="uploadVersion" name="fichier" required
             class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      <p class="text-xs text-slate-400 mt-1">Taille maximale : 20 Mo</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie</label>
        <select name="categorie_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <option value="">— Aucune —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Dossier</label>
        <select name="folder_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <option value="">— Racine —</option>
          <?php foreach ($folders as $f): ?>
          <option value="<?= $f['id'] ?>"><?= str_repeat('&nbsp;&nbsp;', ($f['depth'] ?? 0)) ?><?= htmlspecialchars($f['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Confidentialité</label>
        <select name="confidentialite" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <option value="public">Public</option>
          <option value="interne">Interne</option>
          <option value="confidentiel">Confidentiel</option>
          <option value="secret">Secret</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date d'expiration</label>
        <input type="date" name="date_expiration"
               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
      <textarea name="description" rows="3"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm resize-none focus:ring-violet-500 focus:border-violet-500"></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Référence externe</label>
      <input type="text" name="reference_externe"
             class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
    </div>

    <div id="errorsBox" class="hidden bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700"></div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="/v2/documents" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm hover:bg-slate-50">Annuler</a>
      <button type="submit" class="px-5 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        Téléverser
      </button>
    </div>
  </form>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('/v2/documents', {method:'POST', body: fd});
  const data = await res.json();
  if (data.success) {
    window.location = '/v2/documents/' + data.id;
  } else {
    const box = document.getElementById('errorsBox');
    box.classList.remove('hidden');
    box.textContent = Object.values(data.errors || {}).join(' · ');
  }
});
</script>
