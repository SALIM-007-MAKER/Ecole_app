<?php
/** @var array $contrat, $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$old = $old !== [] ? $old : $contrat;
$err = $errors ?? [];
function val(string $k, $default = ''): string {
    global $old, $contrat;
    $v = $old[$k] ?? $contrat[$k] ?? $default;
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/contrats" class="hover:text-violet-600">Contrats</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>" class="font-mono hover:text-violet-600"><?= e($contrat['numero_contrat']) ?></a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Modifier</span>
  </div>
  <div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="pencil" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Modifier le contrat</h1>
      <p class="text-sm text-slate-500 mt-0.5">
        Employé : <strong><?= e($contrat['employe_nom']) ?></strong>
        — Statut actuel : <span class="font-medium"><?= e($model::statutLabel($contrat['statut'])) ?></span>
      </p>
    </div>
  </div>

  <?php if (!empty($err['global'])): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($err['global']) ?></div>
  <?php endif; ?>
  <?php if (!in_array($contrat['statut'], ['brouillon','actif'], true)): ?>
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
      Ce contrat ne peut pas être modifié directement dans l'état <strong><?= e($model::statutLabel($contrat['statut'])) ?></strong>. Utilisez un avenant ou renouvellement.
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
    <?= \Core\Csrf::field() ?>
    <input type="hidden" name="employe_id" value="<?= (int)$contrat['employe_id'] ?>">

    <!-- Employé (lecture seule) -->
    <div>
      <label class="form-label">Employé</label>
      <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
        <?= e($contrat['employe_nom']) ?> — <?= e($contrat['employe_matricule'] ?? '') ?>
      </div>
    </div>

    <!-- Type + Statut -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Type de contrat *</label>
        <select name="type" required class="form-select">
          <?php foreach ($model::TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= val('type') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Statut</label>
        <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
          <?= e($model::statutLabel($contrat['statut'])) ?>
        </div>
        <input type="hidden" name="statut" value="<?= e($contrat['statut']) ?>">
      </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Date de début *</label>
        <input type="date" name="date_debut" value="<?= val('date_debut') ?>" required
               class="form-input <?= isset($err['date_debut']) ? 'is-invalid' : '' ?>">
        <?php if (isset($err['date_debut'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="form-label">Date de fin <span class="text-slate-400 text-xs">(vide = CDI)</span></label>
        <input type="date" name="date_fin" value="<?= val('date_fin') ?>"
               class="form-input <?= isset($err['date_fin']) ? 'is-invalid' : '' ?>">
        <?php if (isset($err['date_fin'])): ?><p class="text-red-500 text-xs mt-1"><?= e($err['date_fin']) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Date de signature</label>
        <input type="date" name="date_signature" value="<?= val('date_signature') ?>"
               class="form-input">
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

    <!-- Salaire -->
    <div class="grid grid-cols-3 gap-4">
      <div class="col-span-2">
        <label class="form-label">Salaire brut mensuel</label>
        <input type="number" name="salaire_brut" min="0" step="0.01" value="<?= val('salaire_brut') ?>"
               class="form-input">
      </div>
      <div>
        <label class="form-label">Devise</label>
        <input type="text" name="devise" maxlength="3" value="<?= val('devise', 'DZD') ?>"
               class="form-input uppercase">
      </div>
    </div>

    <!-- Motif / Notes -->
    <div>
      <label class="form-label">Motif de création</label>
      <input type="text" name="motif_creation" maxlength="500" value="<?= val('motif_creation') ?>"
             class="form-input">
    </div>
    <div>
      <label class="form-label">Notes internes</label>
      <textarea name="notes" rows="3"
                class="form-textarea"><?= val('notes') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn btn-primary">Enregistrer</button>
      <a href="<?= BASE_URL ?>/v2/rh/contrats/<?= (int)$contrat['id'] ?>" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
