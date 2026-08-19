<?php
$e   = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$err = $errors ?? [];
$sel = fn($k, $v) => (($old[$k] ?? $doc[$k] ?? '') === $v) ? 'selected' : '';
$val = fn($k) => $e($old[$k] ?? $doc[$k] ?? '');
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="flex items-center gap-3">
    <a href="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$doc['id'] ?>" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Modifier le document</h2>
      <p class="text-sm text-slate-400">Crée une nouvelle version (v<?= (int)$doc['version_courante'] + 1 ?>). La version courante est conservée.</p>
    </div>
  </div>

  <?php if ($err['global'] ?? ''): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$doc['id'] ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-5">
    <?= \Core\Csrf::field() ?>

    <!-- Champs immuables -->
    <div class="bg-slate-50 rounded-lg p-3 text-sm">
      <div class="flex gap-6">
        <div><span class="text-slate-400">Type :</span> <span class="font-medium text-slate-700"><?= $e($model::typeLabel($doc['type'])) ?></span></div>
        <div><span class="text-slate-400">Employé :</span> <span class="font-medium text-slate-700"><?= $e($doc['employe_nom'] ?? '') ?></span></div>
        <div><span class="text-slate-400">Date émission :</span> <span class="font-medium text-slate-700"><?= $e(date('d/m/Y', strtotime($doc['date_emission']))) ?></span></div>
      </div>
      <p class="text-xs text-slate-400 mt-1">Ces champs sont immuables et ne peuvent pas être modifiés.</p>
    </div>

    <div>
      <label class="form-label">Titre <span class="form-required">*</span></label>
      <input type="text" name="titre" value="<?= $val('titre') ?>" maxlength="255" required
             class="form-input">
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="form-label">Date d'expiration</label>
        <input type="date" name="date_expiration" value="<?= $val('date_expiration') ?>"
               class="form-input">
      </div>
      <div>
        <label class="form-label">Alerte (jours avant)</label>
        <input type="number" name="alerte_jours" value="<?= $val('alerte_jours') ?>" min="0" max="365"
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
        <label class="form-label">Émetteur</label>
        <input type="text" name="emetteur" value="<?= $val('emetteur') ?>" maxlength="255"
               class="form-input">
      </div>
    </div>

    <div>
      <label class="form-label">Référence externe</label>
      <input type="text" name="reference_externe" value="<?= $val('reference_externe') ?>" maxlength="150"
             placeholder="UUID DocumentService V2"
             class="form-input font-mono">
    </div>

    <div>
      <label class="form-label">Notes</label>
      <textarea name="notes" rows="3"
                class="form-textarea resize-none"><?= $e($old['notes'] ?? $doc['notes'] ?? '') ?></textarea>
    </div>

    <div class="border-t border-slate-100 pt-4">
      <label class="form-label">Motif de la nouvelle version <span class="form-required">*</span></label>
      <input type="text" name="notes_version" value="<?= $val('notes_version') ?>" required maxlength="500"
             placeholder="ex: Mise à jour de la référence externe, correction date d'expiration…"
             class="form-input">
      <p class="text-xs text-slate-400 mt-1">Ce motif sera enregistré dans l'historique des versions.</p>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition">
        Enregistrer — v<?= (int)$doc['version_courante'] + 1 ?>
      </button>
      <a href="<?= BASE_URL ?>/v2/rh/documents/<?= (int)$doc['id'] ?>" class="border border-slate-200 text-slate-600 px-6 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
        Annuler
      </a>
    </div>
  </form>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
