<?php
/** @var array $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$old = $old ?? [];
$err = $errors ?? [];
function val(string $k, $default = '') use ($old): string {
    $v = $old[$k] ?? $default;
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau contrat — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="mb-6">
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
      <a href="/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
      <span>/</span><span>Nouveau</span>
    </div>
    <h1 class="text-2xl font-bold text-slate-900">Nouveau contrat</h1>
  </div>

  <?php if (!empty($err['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/contrats" class="bg-white rounded-xl border border-slate-200 p-6 space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Employé -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Employé *</label>
      <select name="employe_id" required
              class="w-full px-3 py-2 text-sm border <?= isset($err['employe_id']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <option value="">— Sélectionner un employé —</option>
        <?php foreach ($refs['employes'] as $emp): ?>
          <option value="<?= (int)$emp['id'] ?>" <?= val('employe_id') == $emp['id'] ? 'selected' : '' ?>><?= e($emp['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($err['employe_id'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['employe_id']) ?></p><?php endif; ?>
    </div>

    <!-- Type + Statut initial -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Type de contrat *</label>
        <select name="type" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <?php foreach ($model::TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= val('type') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Statut initial</label>
        <select name="statut" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="brouillon" <?= val('statut','brouillon') === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
          <option value="actif" <?= val('statut') === 'actif' ? 'selected' : '' ?>>Actif</option>
        </select>
      </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de début *</label>
        <input type="date" name="date_debut" value="<?= val('date_debut', date('Y-m-d')) ?>" required
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_debut']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_debut'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de fin <span class="text-slate-400 text-xs">(vide = durée indéterminée)</span></label>
        <input type="date" name="date_fin" value="<?= val('date_fin') ?>"
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_fin']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_fin'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_fin']) ?></p><?php endif; ?>
      </div>
    </div>

    <!-- Date de signature -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de signature</label>
        <input type="date" name="date_signature" value="<?= val('date_signature') ?>"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
      </div>
    </div>

    <!-- Poste / Département -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Poste</label>
        <select name="poste_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['postes'] as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= val('poste_id') == $p['id'] ? 'selected' : '' ?>>
              <?= e($p['intitule']) ?> <span class="text-slate-400">(<?= e($p['categorie']) ?>)</span>
            </option>
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

    <!-- Salaire -->
    <div class="grid grid-cols-3 gap-4">
      <div class="col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Salaire brut mensuel</label>
        <input type="number" name="salaire_brut" min="0" step="0.01" value="<?= val('salaire_brut') ?>"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300" placeholder="0.00">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Devise</label>
        <input type="text" name="devise" maxlength="3" value="<?= val('devise', 'DZD') ?>"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300 uppercase">
      </div>
    </div>

    <!-- Motif / Notes -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Motif de création</label>
      <input type="text" name="motif_creation" maxlength="500" value="<?= val('motif_creation') ?>"
             class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Notes internes</label>
      <textarea name="notes" rows="3"
                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300"><?= val('notes') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Créer le contrat</button>
      <a href="/v2/rh/contrats" class="px-6 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Annuler</a>
    </div>
  </form>

</main>
</body>
</html>
