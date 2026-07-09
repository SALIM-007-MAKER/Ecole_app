<?php
/** @var array $departements, $services, $categories, $niveaux, $old, $errors */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function old(string $k, array $old, mixed $default = ''): string { return e((string)($old[$k] ?? $default)); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau poste — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 3) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-2xl">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
    <a href="/v2/rh/organisation/postes" class="hover:text-violet-600">Postes</a>
    <span>/</span>
    <span>Nouveau</span>
  </div>

  <h1 class="text-2xl font-bold text-slate-900 mb-8">Nouveau poste</h1>

  <?php if (!empty($errors['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/organisation/postes" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
    <?php \Core\Csrf::field(); ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Intitulé *</label>
        <input type="text" name="intitule" value="<?= old('intitule', $old) ?>" required maxlength="150"
               placeholder="Ex : Professeur certifié"
               class="w-full px-3 py-2 text-sm border <?= isset($errors['intitule']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <?php if (isset($errors['intitule'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['intitule']) ?></p>
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Code *</label>
        <input type="text" name="code" value="<?= old('code', $old) ?>" required
               pattern="[A-Z0-9_]{2,30}" maxlength="30"
               class="w-full px-3 py-2 text-sm font-mono uppercase border <?= isset($errors['code']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300"
               oninput="this.value=this.value.toUpperCase()">
        <?php if (isset($errors['code'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['code']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie *</label>
        <select name="categorie" required
                class="w-full px-3 py-2 text-sm border <?= isset($errors['categorie']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
          <option value="">Choisir...</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= ($old['categorie'] ?? '') === $cat ? 'selected' : '' ?>>
              <?= e(ucfirst($cat)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['categorie'])): ?>
          <p class="mt-1 text-xs text-red-600"><?= e($errors['categorie']) ?></p>
        <?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Niveau hiérarchique *</label>
        <select name="niveau"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
          <?php foreach ($niveaux as $n => $label): ?>
            <option value="<?= $n ?>" <?= (($old['niveau'] ?? 1) == $n) ? 'selected' : '' ?>>
              <?= $n ?> — <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
        <select name="departement_id" id="sel-dept"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300"
                onchange="filterServices()">
          <option value="0">Aucun</option>
          <?php foreach ($departements as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (($old['departement_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
              <?= e($d['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Service (optionnel)</label>
        <select name="service_id" id="sel-service"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
          <option value="0">Aucun</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int)$s['id'] ?>"
                    data-dept="<?= (int)$s['departement_id'] ?>"
                    <?= (($old['service_id'] ?? 0) == $s['id']) ? 'selected' : '' ?>>
              <?= e($s['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Nb occupants max</label>
        <input type="number" name="nb_occupants_max" value="<?= old('nb_occupants_max', $old, '1') ?>"
               min="1" max="50"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300">
        <p class="mt-1 text-xs text-slate-400">Nombre de titulaires simultanés autorisés</p>
      </div>
      <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="actif" value="1"
                 <?= ($old['actif'] ?? '1') ? 'checked' : '' ?> class="rounded text-violet-600">
          <span class="text-sm text-slate-700">Poste actif</span>
        </label>
      </div>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Description</label>
      <textarea name="description" rows="3" placeholder="Missions, responsabilités, prérequis..."
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-300 resize-none"><?= old('description', $old) ?></textarea>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="/v2/rh/organisation/postes" class="px-5 py-2.5 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200">Annuler</a>
      <button type="submit" class="px-5 py-2.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 font-medium">
        Créer le poste
      </button>
    </div>
  </form>

</main>

<script>
function filterServices() {
    const deptId = parseInt(document.getElementById('sel-dept').value);
    const serviceSelect = document.getElementById('sel-service');
    const options = serviceSelect.querySelectorAll('option');
    options.forEach(opt => {
        const optDept = parseInt(opt.dataset.dept || 0);
        opt.style.display = (opt.value === '0' || deptId === 0 || optDept === deptId) ? '' : 'none';
    });
    // Reset selection if hidden
    const current = serviceSelect.value;
    const currentOpt = serviceSelect.querySelector(`option[value="${current}"]`);
    if (currentOpt && currentOpt.style.display === 'none') {
        serviceSelect.value = '0';
    }
}
filterServices();
</script>
</body>
</html>
