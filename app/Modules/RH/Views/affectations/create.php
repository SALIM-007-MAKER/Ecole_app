<?php
/** @var array $refs, $old, $errors */
/** @var string $model */
/** @var int|null $preEmployeId */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$old = $old ?? [];
$err = $errors ?? [];
function val(string $k, $default = '') use ($old): string {
    return htmlspecialchars((string)($old[$k] ?? $default), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouvelle affectation — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="mb-6">
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
      <a href="/v2/rh/affectations" class="hover:text-violet-600">Affectations</a>
      <span>/</span><span>Nouvelle</span>
    </div>
    <h1 class="text-2xl font-bold text-slate-900">Nouvelle affectation</h1>
  </div>

  <?php if (!empty($err['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/affectations" class="bg-white rounded-xl border border-slate-200 p-6 space-y-6" id="form-affectation">
    <?= \Core\Csrf::field() ?>

    <!-- Employé -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Employé *</label>
      <select name="employe_id" required id="sel-employe"
              onchange="chargerContrats(this.value)"
              class="w-full px-3 py-2 text-sm border <?= isset($err['employe_id']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">— Sélectionner un employé —</option>
        <?php foreach ($refs['employes'] as $emp): ?>
          <option value="<?= (int)$emp['id'] ?>" <?= val('employe_id', $preEmployeId ?? '') == $emp['id'] ? 'selected' : '' ?>><?= e($emp['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($err['employe_id'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['employe_id']) ?></p><?php endif; ?>
    </div>

    <!-- Contrat lié -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Contrat actif lié <span class="text-slate-400">(optionnel)</span></label>
      <select name="contrat_id" id="sel-contrat"
              class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">— Aucun contrat —</option>
        <?php foreach ($refs['contrats'] as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= val('contrat_id') == $c['id'] ? 'selected' : '' ?>>
            <?= e($c['numero_contrat']) ?> (<?= e(strtoupper($c['type'])) ?>) — depuis <?= e(date('d/m/Y', strtotime($c['date_debut']))) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Type -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Type d'affectation *</label>
      <div class="flex gap-3">
        <?php foreach ($model::TYPES as $k => $label): ?>
          <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer <?= val('type', 'principale') === $k ? 'border-violet-400 bg-violet-50' : 'border-slate-200 hover:bg-slate-50' ?>">
            <input type="radio" name="type" value="<?= e($k) ?>" <?= val('type', 'principale') === $k ? 'checked' : '' ?> class="text-violet-600">
            <span class="text-sm"><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="text-xs text-slate-400 mt-1">Principale : une seule active à la fois par employé.</p>
    </div>

    <!-- Poste / Département -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Poste</label>
        <select name="poste_id" id="sel-poste" onchange="filterServices()"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['postes'] as $p): ?>
            <option value="<?= (int)$p['id'] ?>" data-dept="<?= (int)($p['departement_id'] ?? 0) ?>"
                    <?= val('poste_id') == $p['id'] ? 'selected' : '' ?>>
              <?= e($p['intitule']) ?> <span class="text-slate-400">(<?= e($p['categorie']) ?>)</span>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Département</label>
        <select name="departement_id" id="sel-dept" onchange="filterServices()"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['departements'] as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= val('departement_id') == $d['id'] ? 'selected' : '' ?>><?= e($d['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Service / Responsable -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Service</label>
        <select name="service_id" id="sel-service"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['services'] as $s): ?>
            <option value="<?= (int)$s['id'] ?>" data-dept="<?= (int)($s['departement_id'] ?? 0) ?>"
                    <?= val('service_id') == $s['id'] ? 'selected' : '' ?>>
              <?= e($s['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Responsable direct</label>
        <select name="responsable_id"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['employes'] as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>" <?= val('responsable_id') == $emp['id'] ? 'selected' : '' ?>><?= e($emp['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de début *</label>
        <input type="date" name="date_debut" required value="<?= val('date_debut', date('Y-m-d')) ?>"
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_debut']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_debut'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de fin <span class="text-slate-400 text-xs">(vide = indéterminée)</span></label>
        <input type="date" name="date_fin" value="<?= val('date_fin') ?>"
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_fin']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_fin'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_fin']) ?></p><?php endif; ?>
      </div>
    </div>

    <!-- Notes -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Notes internes</label>
      <textarea name="notes" rows="2"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300"><?= val('notes') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Créer l'affectation</button>
      <a href="/v2/rh/affectations" class="px-6 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Annuler</a>
    </div>
  </form>

</main>
<script>
function filterServices() {
    const deptId  = document.getElementById('sel-dept').value;
    const options = document.querySelectorAll('#sel-service option[data-dept]');
    options.forEach(opt => {
        opt.hidden = deptId && opt.dataset.dept !== deptId;
    });
}

function chargerContrats(employeId) {
    if (!employeId) return;
    // En production, ceci ferait un appel AJAX /v2/rh/employes/{id}/contrats-actifs
    // Pour V2 statique, l'utilisateur re-sélectionne le contrat après avoir choisi l'employé
}

filterServices();
</script>
</body>
</html>
