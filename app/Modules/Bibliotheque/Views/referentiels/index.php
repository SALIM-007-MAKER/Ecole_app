<?php /** @var array $auteurs @var array $editeurs @var array $categories @var array $tags @var string $titre */ ?>
<div class="max-w-5xl mx-auto py-8 px-4">

  <h1 class="text-2xl font-bold text-slate-800 mb-6">Référentiels</h1>

  <!-- Tabs -->
  <div class="flex gap-1 mb-6 bg-white rounded-xl p-1 shadow-sm w-fit">
    <?php foreach (['auteurs' => 'Auteurs', 'editeurs' => 'Éditeurs', 'categories' => 'Catégories', 'tags' => 'Tags'] as $tab => $label): ?>
    <button onclick="showTab('<?= $tab ?>')" id="btn-<?= $tab ?>"
            class="tab-btn px-4 py-2 text-sm rounded-lg font-medium transition">
      <?= $label ?>
    </button>
    <?php endforeach ?>
  </div>

  <!-- Auteurs -->
  <div id="tab-auteurs" class="tab-panel">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-semibold text-slate-700">Auteurs (<?= count($auteurs) ?>)</h2>
      <button onclick="showForm('auteur')" class="text-sm bg-violet-600 text-white px-3 py-1.5 rounded-lg hover:bg-violet-700">+ Ajouter</button>
    </div>
    <div id="form-auteur" class="hidden bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="grid grid-cols-2 gap-3">
        <input type="text" id="a_nom" placeholder="Nom *" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        <input type="text" id="a_prenom" placeholder="Prénom" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        <div class="col-span-2 flex gap-2">
          <button onclick="saveAuteur()" class="bg-violet-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-violet-700">Sauvegarder</button>
          <button onclick="document.getElementById('form-auteur').classList.add('hidden')" class="text-sm px-4 py-1.5 rounded-lg border hover:border-slate-400">Annuler</button>
        </div>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($auteurs as $a): ?>
          <tr class="hover:bg-slate-50 px-4 py-2">
            <td class="px-4 py-2"><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td>
            <td class="px-4 py-2 text-right">
              <button onclick="archiveRef('auteur', <?= $a['id'] ?>)" class="text-xs text-red-500 hover:underline">Archiver</button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Éditeurs -->
  <div id="tab-editeurs" class="tab-panel hidden">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-semibold text-slate-700">Éditeurs (<?= count($editeurs) ?>)</h2>
      <button onclick="showForm('editeur')" class="text-sm bg-violet-600 text-white px-3 py-1.5 rounded-lg hover:bg-violet-700">+ Ajouter</button>
    </div>
    <div id="form-editeur" class="hidden bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex gap-3">
        <input type="text" id="e_nom" placeholder="Nom éditeur *" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        <button onclick="saveEditeur()" class="bg-violet-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-violet-700">Ajouter</button>
        <button onclick="document.getElementById('form-editeur').classList.add('hidden')" class="text-sm px-4 py-1.5 rounded-lg border hover:border-slate-400">✕</button>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($editeurs as $e): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2"><?= htmlspecialchars($e['nom']) ?></td>
            <td class="px-4 py-2 text-right">
              <button onclick="archiveRef('editeur', <?= $e['id'] ?>)" class="text-xs text-red-500 hover:underline">Archiver</button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Catégories -->
  <div id="tab-categories" class="tab-panel hidden">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-semibold text-slate-700">Catégories (<?= count($categories) ?>)</h2>
      <button onclick="showForm('categorie')" class="text-sm bg-violet-600 text-white px-3 py-1.5 rounded-lg hover:bg-violet-700">+ Ajouter</button>
    </div>
    <div id="form-categorie" class="hidden bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="flex gap-3">
        <input type="text" id="c_nom" placeholder="Nom catégorie *" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        <button onclick="saveCategorie()" class="bg-violet-600 text-white text-sm px-4 py-1.5 rounded-lg hover:bg-violet-700">Ajouter</button>
        <button onclick="document.getElementById('form-categorie').classList.add('hidden')" class="text-sm px-4 py-1.5 rounded-lg border hover:border-slate-400">✕</button>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($categories as $cat): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2"><?= htmlspecialchars($cat['nom']) ?></td>
            <td class="px-4 py-2 text-right">
              <button onclick="archiveRef('categorie', <?= $cat['id'] ?>)" class="text-xs text-red-500 hover:underline">Archiver</button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tags -->
  <div id="tab-tags" class="tab-panel hidden">
    <div class="flex justify-between items-center mb-3">
      <h2 class="font-semibold text-slate-700">Tags (<?= count($tags) ?>)</h2>
      <div class="flex gap-2">
        <input type="text" id="tag_nom" placeholder="Nouveau tag..." class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
        <button onclick="saveTag()" class="bg-violet-600 text-white text-sm px-3 py-1.5 rounded-lg hover:bg-violet-700">Ajouter</button>
      </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-2">
      <?php foreach ($tags as $tag): ?>
      <span class="bg-slate-100 text-slate-700 text-sm px-3 py-1 rounded-full">
        <?= htmlspecialchars($tag['nom']) ?>
        <?php if (!empty($tag['nb_ouvrages'])): ?><span class="text-slate-400 text-xs ml-1">(<?= $tag['nb_ouvrages'] ?>)</span><?php endif ?>
      </span>
      <?php endforeach ?>
    </div>
  </div>

</div>
<script>
function showTab(tab) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('bg-violet-600','text-white'));
  document.getElementById('tab-' + tab).classList.remove('hidden');
  document.getElementById('btn-' + tab).classList.add('bg-violet-600','text-white');
}
showTab('auteurs');

function showForm(type) { document.getElementById('form-' + type).classList.remove('hidden'); }

function saveAuteur() {
  const data = new URLSearchParams({nom: document.getElementById('a_nom').value, prenom: document.getElementById('a_prenom').value, csrf_token: ''});
  fetch('<?= BASE_URL ?>/v2/bibliotheque/referentiels/auteurs', {method:'POST', body:data}).then(()=>location.reload());
}
function saveEditeur() {
  const data = new URLSearchParams({nom: document.getElementById('e_nom').value, csrf_token: ''});
  fetch('<?= BASE_URL ?>/v2/bibliotheque/referentiels/editeurs', {method:'POST', body:data}).then(()=>location.reload());
}
function saveCategorie() {
  const data = new URLSearchParams({nom: document.getElementById('c_nom').value, csrf_token: ''});
  fetch('<?= BASE_URL ?>/v2/bibliotheque/referentiels/categories', {method:'POST', body:data}).then(()=>location.reload());
}
function saveTag() {
  const data = new URLSearchParams({nom: document.getElementById('tag_nom').value, csrf_token: ''});
  fetch('<?= BASE_URL ?>/v2/bibliotheque/referentiels/tags', {method:'POST', body:data}).then(()=>location.reload());
}
function archiveRef(type, id) {
  if (!confirm('Archiver ?')) return;
  fetch('<?= BASE_URL ?>/v2/bibliotheque/referentiels/' + type + 's/' + id + '/archive', {method:'POST'}).then(()=>location.reload());
}
</script>
