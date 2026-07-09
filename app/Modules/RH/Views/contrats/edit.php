<?php
/** @var array $contrat, $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$old = $old !== [] ? $old : $contrat;
$err = $errors ?? [];
function val(string $k, $default = '') use ($old, $contrat): string {
    $v = $old[$k] ?? $contrat[$k] ?? $default;
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier <?= e($contrat['numero_contrat']) ?> — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="mb-6">
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
      <a href="/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
      <span>/</span>
      <a href="/v2/rh/contrats/<?= (int)$contrat['id'] ?>" class="font-mono hover:text-violet-600"><?= e($contrat['numero_contrat']) ?></a>
      <span>/</span><span>Modifier</span>
    </div>
    <h1 class="text-2xl font-bold text-slate-900">Modifier le contrat</h1>
    <p class="text-sm text-slate-500 mt-1">
      Employé : <strong><?= e($contrat['employe_nom']) ?></strong>
      — Statut actuel : <span class="font-medium"><?= e($model::statutLabel($contrat['statut'])) ?></span>
    </p>
  </div>

  <?php if (!empty($err['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($err['global']) ?></div>
  <?php endif; ?>
  <?php if (!in_array($contrat['statut'], ['brouillon','actif'], true)): ?>
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
      Ce contrat ne peut pas être modifié directement dans l'état <strong><?= e($model::statutLabel($contrat['statut'])) ?></strong>. Utilisez un avenant ou renouvellement.
    </div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/contrats/<?= (int)$contrat['id'] ?>" class="bg-white rounded-xl border border-slate-200 p-6 space-y-6">
    <?= \Core\Csrf::field() ?>
    <input type="hidden" name="employe_id" value="<?= (int)$contrat['employe_id'] ?>">

    <!-- Employé (lecture seule) -->
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Employé</label>
      <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
        <?= e($contrat['employe_nom']) ?> — <?= e($contrat['employe_matricule'] ?? '') ?>
      </div>
    </div>

    <!-- Type + Statut -->
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
        <label class="block text-sm font-medium text-slate-700 mb-1">Statut</label>
        <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
          <?= e($model::statutLabel($contrat['statut'])) ?>
        </div>
        <input type="hidden" name="statut" value="<?= e($contrat['statut']) ?>">
      </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de début *</label>
        <input type="date" name="date_debut" value="<?= val('date_debut') ?>" required
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_debut']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_debut'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date de fin <span class="text-slate-400 text-xs">(vide = CDI)</span></label>
        <input type="date" name="date_fin" value="<?= val('date_fin') ?>"
               class="w-full px-3 py-2 text-sm border <?= isset($err['date_fin']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:ring-2 focus:ring-violet-300">
        <?php if (isset($err['date_fin'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_fin']) ?></p><?php endif; ?>
      </div>
    </div>

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

    <!-- Salaire -->
    <div class="grid grid-cols-3 gap-4">
      <div class="col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Salaire brut mensuel</label>
        <input type="number" name="salaire_brut" min="0" step="0.01" value="<?= val('salaire_brut') ?>"
               class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-violet-300">
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
      <button type="submit" class="px-6 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Enregistrer</button>
      <a href="/v2/rh/contrats/<?= (int)$contrat['id'] ?>" class="px-6 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Annuler</a>
    </div>
  </form>

</main>
</body>
</html>
