<?php
$e   = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$err = $errors ?? [];
$sel = fn($k, $v) => (($old[$k] ?? '') === $v) ? 'selected' : '';
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="flex items-center gap-3">
    <a href="/v2/rh/formations" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <h2 class="text-xl font-semibold text-slate-800">Créer une session de formation</h2>
  </div>

  <?php if ($err['global'] ?? ''): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/formations/sessions" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-5">
    <?= \Core\Csrf::field() ?>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Formation <span class="text-red-500">*</span></label>
      <select name="formation_id" required class="w-full border <?= isset($err['formation_id']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
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
        <label class="block text-sm font-medium text-slate-700 mb-1">Code session <span class="text-red-500">*</span></label>
        <input type="text" name="code_session" value="<?= $e($old['code_session'] ?? '') ?>"
               placeholder="ex: SESS-2026-001" maxlength="50"
               class="w-full border <?= isset($err['code_session']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
        <?php if ($err['code_session'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['code_session']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Lieu</label>
        <input type="text" name="lieu" value="<?= $e($old['lieu'] ?? '') ?>"
               placeholder="Salle, adresse, lien…" maxlength="255"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date début <span class="text-red-500">*</span></label>
        <input type="date" name="date_debut" value="<?= $e($old['date_debut'] ?? '') ?>"
               class="w-full border <?= isset($err['date_debut']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
        <?php if ($err['date_debut'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['date_debut']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date fin</label>
        <input type="date" name="date_fin" value="<?= $e($old['date_fin'] ?? '') ?>"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure début</label>
        <input type="time" name="heure_debut" value="<?= $e($old['heure_debut'] ?? '08:00') ?>"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure fin</label>
        <input type="time" name="heure_fin" value="<?= $e($old['heure_fin'] ?? '17:00') ?>"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Max participants</label>
        <input type="number" name="max_participants" value="<?= $e($old['max_participants'] ?? 20) ?>"
               min="1" max="500"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Coût total (FCFA)</label>
        <input type="number" name="cout_total" value="<?= $e($old['cout_total'] ?? '') ?>"
               min="0" step="0.01"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Financeur</label>
        <select name="financeur" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <?php foreach ($model::FINANCEURS as $f): ?>
          <option value="<?= $e($f) ?>" <?= $sel('financeur', $f) ?>><?= $e(ucfirst(str_replace('_',' ',$f))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Formateur</label>
      <input type="text" name="formateur_nom" value="<?= $e($old['formateur_nom'] ?? '') ?>"
             placeholder="Nom du formateur pour cette session"
             class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Commentaire</label>
      <textarea name="commentaire" rows="2"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none"><?= $e($old['commentaire'] ?? '') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition">
        Créer la session
      </button>
      <a href="/v2/rh/formations" class="border border-slate-200 text-slate-600 px-6 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
        Annuler
      </a>
    </div>
  </form>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
