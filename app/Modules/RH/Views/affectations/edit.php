<?php
/** @var array $affectation, $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$old = $old !== [] ? $old : $affectation;
$err = $errors ?? [];
function val(string $k, $default = '') use ($old, $affectation): string {
    $v = $old[$k] ?? $affectation[$k] ?? $default;
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier affectation — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="mb-6">
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
      <a href="/v2/rh/affectations" class="hover:text-violet-600">Affectations</a>
      <span>/</span>
      <a href="/v2/rh/affectations/<?= (int)$affectation['id'] ?>" class="hover:text-violet-600"><?= e($affectation['employe_nom']) ?></a>
      <span>/</span><span>Modifier</span>
    </div>
    <h1 class="text-2xl font-bold text-slate-900">Modifier l'affectation</h1>
    <p class="text-sm text-slate-500 mt-1">
      Employé : <strong><?= e($affectation['employe_nom']) ?></strong>
      — Statut : <span class="<?= $model::statutColor($affectation['statut']) ?> px-2 py-0.5 text-xs rounded-full"><?= e($model::statutLabel($affectation['statut'])) ?></span>
    </p>
  </div>

  <?php if (!empty($err['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($err['global']) ?></div>
  <?php endif; ?>
  <?php if ($affectation['statut'] === 'terminee'): ?>
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
      Cette affectation est terminée. Les modifications sont bloquées.
    </div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/affectations/<?= (int)$affectation['id'] ?>"
        class="bg-white rounded-xl border border-slate-200 p-6 space-y-6">
    <?= \Core\Csrf::field() ?>
    <!-- employe_id en hidden : l'employé ne change pas via une simple modification -->
    <input type="hidden" name="employe_id" value="<?= (int)$affectation['employe_id'] ?>">

    <!-- Employé (lecture seule) -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Employé</label>
      <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
        <?= e($affectation['employe_nom']) ?> — <?= e($affectation['employe_matricule'] ?? '') ?>
      </div>
    </div>

    <!-- Contrat -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Contrat actif lié</label>
      <select name="contrat_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">— Aucun —</option>
        <?php foreach ($refs['contrats'] as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= val('contrat_id') == $c['id'] ? 'selected' : '' ?>>
            <?= e($c['numero_contrat']) ?> (<?= e(strtoupper($c['type'])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Type -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Type d'affectation</label>
      <div class="flex gap-3">
        <?php foreach ($model::TYPES as $k => $label): ?>
          <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer <?= val('type', 'principale') === $k ? 'border-violet-400 bg-violet-50' : 'border-slate-200 hover:bg-slate-50' ?>">
            <input type="radio" name="type" value="<?= e($k) ?>" <?= val('type', 'principale') === $k ? 'checked' : '' ?> class="text-violet-600">
            <span class="text-sm"><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Poste / Département -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Poste</label>
        <select name="poste_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['postes'] as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= val('poste_id') == $p['id'] ? 'selected' : '' ?>><?= e($p['intitule']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Département</label>
        <select name="departement_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
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
        <select name="service_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['services'] as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= val('service_id') == $s['id'] ? 'selected' : '' ?>><?= e($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Responsable direct</label>
        <select name="responsable_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
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
        <input type="date" name="date_debut" required value="<?= val('date_debut') ?>"
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_debut']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_debut'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de fin</label>
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
      <button type="submit" <?= $affectation['statut'] === 'terminee' ? 'disabled' : '' ?>
              class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700 disabled:opacity-50">Enregistrer</button>
      <a href="/v2/rh/affectations/<?= (int)$affectation['id'] ?>"
         class="px-6 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Annuler</a>
    </div>
  </form>

</main>
</body>
</html>
