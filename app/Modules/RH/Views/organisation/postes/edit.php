<?php
/** @var array $poste, $departements, $services, $categories, $niveaux, $old, $errors */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, array $data, array $old, mixed $default = ''): string {
    return e((string)($old[$k] ?? $data[$k] ?? $default));
}
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/organisation/postes" class="hover:text-violet-600">Postes</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Modifier</span>
  </div>

  <div class="flex items-start gap-4 mb-8">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 pt-2">Modifier le poste</h1>
  </div>

  <?php if (!empty($errors['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/organisation/postes/<?= (int)($poste['id'] ?? 0) ?>"
        class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
    <?php \Core\Csrf::field(); ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Intitulé *</label>
        <input type="text" name="intitule" value="<?= val('intitule', $poste, $old) ?>" required maxlength="150"
               class="form-input <?= isset($errors['intitule']) ? 'is-invalid' : '' ?>">
        <?php if (isset($errors['intitule'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['intitule']) ?></p>
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
        <input type="text" name="code" value="<?= val('code', $poste, $old) ?>" required
               pattern="[A-Z0-9_]{2,30}" maxlength="30"
               class="form-input <?= isset($errors['code']) ? 'is-invalid' : '' ?> font-mono uppercase"
               oninput="this.value=this.value.toUpperCase()">
        <?php if (isset($errors['code'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['code']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie *</label>
        <?php $curCat = $old['categorie'] ?? $poste['categorie'] ?? ''; ?>
        <select name="categorie" required
                class="form-select">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $curCat === $cat ? 'selected' : '' ?>>
              <?= e(ucfirst($cat)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Niveau hiérarchique</label>
        <?php $curNiv = (int)($old['niveau'] ?? $poste['niveau'] ?? 1); ?>
        <select name="niveau"
                class="form-select">
          <?php foreach ($niveaux as $n => $label): ?>
            <option value="<?= $n ?>" <?= $curNiv === $n ? 'selected' : '' ?>>
              <?= $n ?> — <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
        <?php $curDept = (int)($old['departement_id'] ?? $poste['departement_id'] ?? 0); ?>
        <select name="departement_id" id="sel-dept" onchange="filterServices()"
                class="form-select">
          <option value="0">Aucun</option>
          <?php foreach ($departements as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= $curDept === (int)$d['id'] ? 'selected' : '' ?>>
              <?= e($d['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Service</label>
        <?php $curSvc = (int)($old['service_id'] ?? $poste['service_id'] ?? 0); ?>
        <select name="service_id" id="sel-service"
                class="form-select">
          <option value="0">Aucun</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int)$s['id'] ?>"
                    data-dept="<?= (int)$s['departement_id'] ?>"
                    <?= $curSvc === (int)$s['id'] ? 'selected' : '' ?>>
              <?= e($s['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Nb occupants max</label>
        <input type="number" name="nb_occupants_max"
               value="<?= val('nb_occupants_max', $poste, $old, '1') ?>"
               min="1" max="50"
               class="form-input">
      </div>
      <div class="flex items-end pb-2">
        <?php $isActif = (bool)(int)($old['actif'] ?? $poste['actif'] ?? 1); ?>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="actif" value="1" <?= $isActif ? 'checked' : '' ?> class="rounded text-violet-600">
          <span class="text-sm text-slate-700">Poste actif</span>
        </label>
      </div>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
      <textarea name="description" rows="3"
                class="form-textarea resize-none"><?= val('description', $poste, $old) ?></textarea>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="<?= BASE_URL ?>/v2/rh/organisation/postes" class="px-5 py-2.5 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Annuler</a>
      <button type="submit" class="px-5 py-2.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 font-medium">
        Enregistrer
      </button>
    </div>
  </form>

<script>
function filterServices() {
    const deptId = parseInt(document.getElementById('sel-dept').value);
    document.querySelectorAll('#sel-service option').forEach(opt => {
        const d = parseInt(opt.dataset.dept || 0);
        opt.style.display = (opt.value === '0' || deptId === 0 || d === deptId) ? '' : 'none';
    });
}
filterServices();
</script>
