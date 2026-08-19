<?php
/** @var array $fonctions, $canCreate, $canUpdate, $canArchive */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$perimetreLabels = ['etablissement' => 'Établissement', 'departement' => 'Département', 'service' => 'Service', 'transversal' => 'Transversal'];
$niveauLabels    = [1 => 'Opérationnel', 2 => 'Encadrement', 3 => 'Direction'];
?>

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="<?= BASE_URL ?>/v2/rh/organisation" class="hover:text-violet-600">Organisation</a>
        <span>/</span>
        <a href="<?= BASE_URL ?>/v2/rh/organisation/postes" class="hover:text-violet-600">Postes</a>
        <span>/</span>
        <span>Fonctions</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Fonctions transversales</h1>
      <p class="text-slate-500 text-sm mt-1">Fonctions institutionnelles indépendantes du poste</p>
    </div>
    <?php if ($canCreate): ?>
      <button onclick="document.getElementById('modal-new').classList.remove('hidden')"
              class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nouvelle fonction
      </button>
    <?php endif; ?>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Nom</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Code</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Niveau</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Périmètre</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Affectations</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Statut</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($fonctions)): ?>
          <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune fonction définie.</td></tr>
        <?php endif; ?>
        <?php foreach ($fonctions as $f): ?>
          <?php $archived = $f['deleted_at'] !== null; ?>
          <tr class="hover:bg-slate-50 <?= $archived ? 'opacity-60' : '' ?>">
            <td class="px-4 py-3 font-medium text-slate-800"><?= e($f['nom']) ?></td>
            <td class="px-4 py-3"><span class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded"><?= e($f['code']) ?></span></td>
            <td class="px-4 py-3 text-slate-600 text-xs"><?= e($niveauLabels[$f['niveau']] ?? 'N' . $f['niveau']) ?></td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs rounded-full bg-blue-50 text-blue-700">
                <?= e($perimetreLabels[$f['perimetre']] ?? $f['perimetre']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-center text-slate-700"><?= (int)$f['nb_affectations'] ?></td>
            <td class="px-4 py-3 text-center">
              <?php if ($archived): ?>
                <span class="px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 rounded-full">Archivé</span>
              <?php elseif ($f['actif']): ?>
                <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-full">Actif</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <?php if ($canUpdate && !$archived): ?>
                  <button onclick="openEditModal(<?= (int)$f['id'] ?>, '<?= addslashes(e($f['nom'])) ?>', '<?= e($f['code']) ?>', <?= (int)$f['niveau'] ?>, '<?= e($f['perimetre']) ?>', '<?= addslashes(e((string)$f['description'])) ?>')"
                          class="px-2 py-1 text-xs text-violet-600 bg-violet-50 rounded hover:bg-violet-100">Éditer</button>
                <?php endif; ?>
                <?php if ($canArchive && !$archived): ?>
                  <form method="POST" action="<?= BASE_URL ?>/v2/rh/organisation/fonctions/<?= (int)$f['id'] ?>/archive"
                        onsubmit="return confirm('Archiver cette fonction ?')">
                    <?php \Core\Csrf::field(); ?>
                    <button type="submit" class="px-2 py-1 text-xs text-amber-600 bg-amber-50 rounded hover:bg-amber-100">Archiver</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<!-- Modal nouvelle fonction -->
<?php if ($canCreate): ?>
<div id="modal-new" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
    <h2 class="font-semibold text-slate-800 mb-4">Nouvelle fonction</h2>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/organisation/fonctions" class="space-y-4">
      <?php \Core\Csrf::field(); ?>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Nom *</label>
          <input type="text" name="nom" required placeholder="Ex : Tuteur"
                 class="form-input">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
          <input type="text" name="code" required placeholder="TUTEUR" pattern="[A-Z0-9_]{2,30}"
                 class="form-input font-mono"
                 oninput="this.value=this.value.toUpperCase()">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Niveau</label>
          <select name="niveau" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <option value="1">1 — Opérationnel</option>
            <option value="2">2 — Encadrement</option>
            <option value="3">3 — Direction</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Périmètre</label>
          <select name="perimetre" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <option value="transversal">Transversal</option>
            <option value="etablissement">Établissement</option>
            <option value="departement">Département</option>
            <option value="service">Service</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
        <input type="text" name="description" placeholder="Description optionnelle"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
      </div>
      <div class="flex gap-2 justify-end pt-2">
        <button type="button" onclick="document.getElementById('modal-new').classList.add('hidden')"
                class="px-4 py-2 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Annuler</button>
        <button type="submit" class="px-4 py-2 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700">Créer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<!-- Modal édition fonction -->
<?php if ($canUpdate): ?>
<div id="modal-edit" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
    <h2 class="font-semibold text-slate-800 mb-4">Modifier la fonction</h2>
    <form id="form-edit-fonction" method="POST" class="space-y-4">
      <?php \Core\Csrf::field(); ?>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Nom *</label>
          <input type="text" id="edit-nom" name="nom" required
                 class="form-input">
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
          <input type="text" id="edit-code" name="code" required pattern="[A-Z0-9_]{2,30}"
                 class="form-input font-mono"
                 oninput="this.value=this.value.toUpperCase()">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Niveau</label>
          <select id="edit-niveau" name="niveau" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <option value="1">1 — Opérationnel</option>
            <option value="2">2 — Encadrement</option>
            <option value="3">3 — Direction</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Périmètre</label>
          <select id="edit-perimetre" name="perimetre" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <option value="transversal">Transversal</option>
            <option value="etablissement">Établissement</option>
            <option value="departement">Département</option>
            <option value="service">Service</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
        <input type="text" id="edit-description" name="description"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
      </div>
      <input type="hidden" name="actif" value="1">
      <div class="flex gap-2 justify-end pt-2">
        <button type="button" onclick="document.getElementById('modal-edit').classList.add('hidden')"
                class="px-4 py-2 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Annuler</button>
        <button type="submit" class="px-4 py-2 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
function openEditModal(id, nom, code, niveau, perimetre, description) {
    document.getElementById('form-edit-fonction').action = '/v2/rh/organisation/fonctions/' + id;
    document.getElementById('edit-nom').value = nom;
    document.getElementById('edit-code').value = code;
    document.getElementById('edit-niveau').value = niveau;
    document.getElementById('edit-perimetre').value = perimetre;
    document.getElementById('edit-description').value = description;
    document.getElementById('modal-edit').classList.remove('hidden');
}
</script>
<?php endif; ?>
