<?php
$e   = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$err = $errors ?? [];
$sel = fn($k, $v) => (($old[$k] ?? '') === $v) ? 'selected' : '';
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="flex items-start gap-4">
    <a href="<?= BASE_URL ?>/v2/rh/formations"
       class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
      <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="calendar-plus" class="w-5 h-5 text-violet-600"></i>
    </div>
    <h2 class="text-xl font-semibold text-slate-800 pt-2">Créer une session de formation</h2>
  </div>

  <?php if ($err['global'] ?? ''): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/formations/sessions" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-5">
    <?= \Core\Csrf::field() ?>

    <div>
      <label class="form-label">Formation <span class="form-required">*</span></label>
      <select name="formation_id" required class="form-select <?= isset($err['formation_id']) ? 'is-invalid' : '' ?>">
        <option value="">Sélectionner une formation…</option>
        <?php foreach ($formations as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= (($old['formation_id'] ?? $preFormation) == $f['id']) ? 'selected' : '' ?>>
          [<?= $e($f['code']) ?>] <?= $e($f['titre']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($err['formation_id'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['formation_id']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Code session <span class="form-required">*</span></label>
        <input type="text" name="code_session" value="<?= $e($old['code_session'] ?? '') ?>"
               placeholder="ex: SESS-2026-001" maxlength="50"
               class="form-select <?= isset($err['code_session']) ? 'is-invalid' : '' ?>">
        <?php if ($err['code_session'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['code_session']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="form-label">Lieu</label>
        <input type="text" name="lieu" value="<?= $e($old['lieu'] ?? '') ?>"
               placeholder="Salle, adresse, lien…" maxlength="255"
               class="form-input">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Date début <span class="form-required">*</span></label>
        <input type="date" name="date_debut" value="<?= $e($old['date_debut'] ?? '') ?>"
               class="form-input <?= isset($err['date_debut']) ? 'is-invalid' : '' ?>">
        <?php if ($err['date_debut'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="form-label">Date fin</label>
        <input type="date" name="date_fin" value="<?= $e($old['date_fin'] ?? '') ?>"
               class="form-input">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Heure début</label>
        <input type="time" name="heure_debut" value="<?= $e($old['heure_debut'] ?? '08:00') ?>"
               class="form-input">
      </div>
      <div>
        <label class="form-label">Heure fin</label>
        <input type="time" name="heure_fin" value="<?= $e($old['heure_fin'] ?? '17:00') ?>"
               class="form-input">
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="form-label">Max participants</label>
        <input type="number" name="max_participants" value="<?= $e($old['max_participants'] ?? 20) ?>"
               min="1" max="500"
               class="form-input">
      </div>
      <div>
        <label class="form-label">Coût total (FCFA)</label>
        <input type="number" name="cout_total" value="<?= $e($old['cout_total'] ?? '') ?>"
               min="0" step="0.01"
               class="form-input">
      </div>
      <div>
        <label class="form-label">Financeur</label>
        <select name="financeur" class="form-select">
          <?php foreach ($model::FINANCEURS as $f): ?>
          <option value="<?= $e($f) ?>" <?= $sel('financeur', $f) ?>><?= $e(ucfirst(str_replace('_',' ',$f))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="form-label">Formateur</label>
      <input type="text" name="formateur_nom" value="<?= $e($old['formateur_nom'] ?? '') ?>"
             placeholder="Nom du formateur pour cette session"
             class="form-input">
    </div>

    <div>
      <label class="form-label">Commentaire</label>
      <textarea name="commentaire" rows="2"
                class="form-textarea resize-none"><?= $e($old['commentaire'] ?? '') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition">
        Créer la session
      </button>
      <a href="<?= BASE_URL ?>/v2/rh/formations" class="border border-slate-200 text-slate-600 px-6 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
        Annuler
      </a>
    </div>
  </form>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
