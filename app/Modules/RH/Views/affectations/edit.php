<?php
/** @var array $affectation, $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$old = $old !== [] ? $old : $affectation;
$err = $errors ?? [];
function val(string $k, $default = ''): string {
    global $old, $affectation;
    $v = $old[$k] ?? $affectation[$k] ?? $default;
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/affectations" class="hover:text-violet-600">Affectations</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>" class="hover:text-violet-600"><?= e($affectation['employe_nom']) ?></a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Modifier</span>
  </div>
  <div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
    <h1 class="text-2xl font-bold text-slate-900">Modifier l'affectation</h1>
    <p class="text-sm text-slate-500 mt-1">
      Employé : <strong><?= e($affectation['employe_nom']) ?></strong>
      — Statut : <span class="<?= $model::statutColor($affectation['statut']) ?> px-2 py-0.5 text-xs rounded-full"><?= e($model::statutLabel($affectation['statut'])) ?></span>
    </p>
    </div>
  </div>

  <?php if (!empty($err['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($err['global']) ?></div>
  <?php endif; ?>
  <?php if ($affectation['statut'] === 'terminee'): ?>
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
      Cette affectation est terminée. Les modifications sont bloquées.
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>"
        class="bg-white rounded-xl border border-slate-200 p-6 space-y-6">
    <?= \Core\Csrf::field() ?>
    <!-- employe_id en hidden : l'employé ne change pas via une simple modification -->
    <input type="hidden" name="employe_id" value="<?= (int)$affectation['employe_id'] ?>">

    <!-- Employé (lecture seule) -->
    <div>
      <label class="form-label">Employé</label>
      <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
        <?= e($affectation['employe_nom']) ?> — <?= e($affectation['employe_matricule'] ?? '') ?>
      </div>
    </div>

    <!-- Contrat -->
    <div>
      <label class="form-label">Contrat actif lié</label>
      <select name="contrat_id" class="form-select">
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
      <label class="form-label">Type d'affectation</label>
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
        <label class="form-label">Poste</label>
        <select name="poste_id" class="form-select">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['postes'] as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= val('poste_id') == $p['id'] ? 'selected' : '' ?>><?= e($p['intitule']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Département</label>
        <select name="departement_id" class="form-select">
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
        <label class="form-label">Service</label>
        <select name="service_id" class="form-select">
          <option value="">— Aucun —</option>
          <?php foreach ($refs['services'] as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= val('service_id') == $s['id'] ? 'selected' : '' ?>><?= e($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Responsable direct</label>
        <select name="responsable_id" class="form-select">
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
        <label class="form-label">Date de début *</label>
        <input type="date" name="date_debut" required value="<?= val('date_debut') ?>"
               class="form-input <?= isset($err['date_debut']) ? 'is-invalid' : '' ?>">
        <?php if (isset($err['date_debut'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="form-label">Date de fin</label>
        <input type="date" name="date_fin" value="<?= val('date_fin') ?>"
               class="form-input <?= isset($err['date_fin']) ? 'is-invalid' : '' ?>">
        <?php if (isset($err['date_fin'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_fin']) ?></p><?php endif; ?>
      </div>
    </div>

    <!-- Notes -->
    <div>
      <label class="form-label">Notes internes</label>
      <textarea name="notes" rows="2"
                class="form-textarea"><?= val('notes') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" <?= $affectation['statut'] === 'terminee' ? 'disabled' : '' ?> class="btn btn-primary">Enregistrer</button>
      <a href="<?= BASE_URL ?>/v2/rh/affectations/<?= (int)$affectation['id'] ?>" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
