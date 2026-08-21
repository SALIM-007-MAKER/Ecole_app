<?php
$e   = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$err = $errors ?? [];
$sel = fn($k, $v) => (($old[$k] ?? '') === $v) ? 'selected' : '';
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="flex items-center gap-3">
    <a href="<?= BASE_URL ?>/v2/rh/documents" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <h2 class="text-xl font-semibold text-slate-800">Enregistrer un document RH</h2>
  </div>

  <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800 flex gap-2">
    <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
    <span>Ce formulaire enregistre les <strong>métadonnées</strong> du document. Le stockage du fichier physique sera géré par le module Documents V2.</span>
  </div>

  <?php if ($err['global'] ?? ''): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/documents" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-5">
    <?= \Core\Csrf::field() ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Type <span class="form-required">*</span></label>
        <select name="type" required class="form-select <?= isset($err['type']) ? 'is-invalid' : '' ?>">
          <option value="">Sélectionner…</option>
          <?php foreach ($model::TYPES as $t): ?>
          <option value="<?= $e($t) ?>" <?= $sel('type', $t) ?>><?= $e($model::typeLabel($t)) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($err['type'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['type']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="form-label">Employé <span class="form-required">*</span></label>
        <select name="employe_id" required class="form-select <?= isset($err['employe_id']) ? 'is-invalid' : '' ?>">
          <option value="">Sélectionner…</option>
          <?php foreach ($employes as $emp): ?>
          <option value="<?= (int)$emp['id'] ?>" <?= (($old['employe_id'] ?? $preEmp) == $emp['id']) ? 'selected' : '' ?>>
            <?= $e($emp['nom_complet']) ?> (<?= $e($emp['matricule']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <?php if ($err['employe_id'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['employe_id']) ?></p><?php endif; ?>
      </div>
    </div>

    <div>
      <label class="form-label">Titre <span class="form-required">*</span></label>
      <input type="text" name="titre" value="<?= $e($old['titre'] ?? '') ?>"
             placeholder="ex: Contrat CDI — M. Diallo 2026" maxlength="255"
             class="form-select <?= isset($err['titre']) ? 'is-invalid' : '' ?>">
      <?php if ($err['titre'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['titre']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="form-label">Date d'émission <span class="form-required">*</span></label>
        <input type="date" name="date_emission" value="<?= $e($old['date_emission'] ?? date('Y-m-d')) ?>"
               class="form-input">
      </div>
      <div>
        <label class="form-label">Date d'expiration</label>
        <input type="date" name="date_expiration" value="<?= $e($old['date_expiration'] ?? '') ?>"
               class="form-input <?= isset($err['date_expiration']) ? 'is-invalid' : '' ?>">
        <?php if ($err['date_expiration'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['date_expiration']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="form-label">Alerte (jours avant)</label>
        <input type="number" name="alerte_jours" value="<?= $e($old['alerte_jours'] ?? 30) ?>"
               min="0" max="365"
               class="form-input">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Confidentialité</label>
        <select name="confidentialite" class="form-select">
          <?php foreach ($model::CONFIDENTIALITES as $c): ?>
          <option value="<?= $e($c) ?>" <?= $sel('confidentialite', $c) ?>>
            <?= $e($model::CONFIDENTIALITE_LABELS[$c]) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Émetteur / Autorité</label>
        <input type="text" name="emetteur" value="<?= $e($old['emetteur'] ?? '') ?>"
               placeholder="DRH, Ministère, Médecin…" maxlength="255"
               class="form-input">
      </div>
    </div>

    <div>
      <label class="form-label">Référence externe</label>
      <input type="text" name="reference_externe" value="<?= $e($old['reference_externe'] ?? '') ?>"
             placeholder="UUID du fichier dans DocumentService (optionnel)"
             class="form-input font-mono">
      <p class="text-xs text-slate-400 mt-1">Sera complété automatiquement lors de l'intégration Documents V2.</p>
    </div>

    <div>
      <label class="form-label">Notes</label>
      <textarea name="notes" rows="3"
                class="form-textarea resize-none"
                placeholder="Observations, conditions particulières…"><?= $e($old['notes'] ?? '') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn btn-primary">
        Enregistrer (v1)
      </button>
      <a href="<?= BASE_URL ?>/v2/rh/documents" class="btn btn-secondary">
        Annuler
      </a>
    </div>
  </form>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
