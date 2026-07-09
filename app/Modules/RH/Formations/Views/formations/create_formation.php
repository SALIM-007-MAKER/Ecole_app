<?php
$e   = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$old = $old ?? [];
$err = $errors ?? [];
$sel = fn($k, $v) => (($old[$k] ?? '') === $v) ? 'selected' : '';
?>
<div class="max-w-2xl mx-auto space-y-6">
  <div class="flex items-center gap-3">
    <a href="/v2/rh/formations/catalogue" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <h2 class="text-xl font-semibold text-slate-800">Ajouter une formation au catalogue</h2>
  </div>

  <?php if ($err['global'] ?? ''): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"><?= $e($err['global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/formations/catalogue" class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 space-y-5">
    <?= \Core\Csrf::field() ?>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Code <span class="text-red-500">*</span></label>
        <input type="text" name="code" value="<?= $e($old['code'] ?? '') ?>"
               placeholder="ex: FORM-001" maxlength="30"
               class="w-full border <?= isset($err['code']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
        <?php if ($err['code'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['code']) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Type <span class="text-red-500">*</span></label>
        <select name="type" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <?php foreach ($model::TYPES_FORMATION as $t): ?>
          <option value="<?= $e($t) ?>" <?= $sel('type', $t) ?>><?= $e(ucfirst($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-red-500">*</span></label>
      <input type="text" name="titre" value="<?= $e($old['titre'] ?? '') ?>"
             placeholder="Intitulé de la formation" maxlength="255"
             class="w-full border <?= isset($err['titre']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      <?php if ($err['titre'] ?? ''): ?><p class="text-xs text-red-500 mt-1"><?= $e($err['titre']) ?></p><?php endif; ?>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
      <textarea name="description" rows="3"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none"
                placeholder="Objectifs pédagogiques, contenu…"><?= $e($old['description'] ?? '') ?></textarea>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Durée (heures)</label>
        <input type="number" name="duree_heures" value="<?= $e($old['duree_heures'] ?? '') ?>"
               min="0.5" max="9999" step="0.5"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Niveau</label>
        <select name="niveau" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <?php foreach ($model::NIVEAUX as $n): ?>
          <option value="<?= $e($n) ?>" <?= $sel('niveau', $n) ?>><?= $e(ucfirst($n)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Modalité</label>
        <select name="modalite" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <?php foreach ($model::MODALITES as $m): ?>
          <option value="<?= $e($m) ?>" <?= $sel('modalite', $m) ?>><?= $e(ucfirst($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Organisme</label>
        <select name="organisme_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
          <option value="">Interne</option>
          <?php foreach ($organismes as $org): ?>
          <option value="<?= (int)$org['id'] ?>" <?= ($old['organisme_id'] ?? '') == $org['id'] ? 'selected' : '' ?>>
            <?= $e($org['nom']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Formateur principal</label>
        <input type="text" name="formateur_principal" value="<?= $e($old['formateur_principal'] ?? '') ?>"
               placeholder="Nom du formateur"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Coût unitaire (FCFA)</label>
        <input type="number" name="cout_unitaire" value="<?= $e($old['cout_unitaire'] ?? '') ?>"
               min="0" step="0.01"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Max participants / session</label>
        <input type="number" name="max_participants" value="<?= $e($old['max_participants'] ?? 20) ?>"
               min="1" max="500"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">
      </div>
    </div>

    <!-- Compétences acquises -->
    <?php if (!empty($competences)): ?>
    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Compétences développées</label>
      <?php
        $cats = [];
        foreach ($competences as $c) {
            $cats[$c['categorie']][] = $c;
        }
        $selIds = isset($old['competence_ids']) ? (array)$old['competence_ids'] : [];
      ?>
      <div class="border border-slate-200 rounded-lg p-3 space-y-3 max-h-52 overflow-y-auto">
        <?php foreach ($cats as $cat => $comps): ?>
        <div>
          <div class="text-xs font-semibold text-slate-500 uppercase mb-1"><?= $e(str_replace('_',' ',$cat)) ?></div>
          <div class="grid grid-cols-2 gap-1">
            <?php foreach ($comps as $c): ?>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" name="competence_ids[]" value="<?= (int)$c['id'] ?>"
                     <?= in_array((string)$c['id'], $selIds) ? 'checked' : '' ?>
                     class="rounded border-slate-300 text-violet-600 focus:ring-violet-400">
              <span class="text-slate-700"><?= $e($c['nom']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Prérequis</label>
      <textarea name="prerequis" rows="2"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none"><?= $e($old['prerequis'] ?? '') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Objectifs pédagogiques</label>
      <textarea name="objectifs" rows="2"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none"><?= $e($old['objectifs'] ?? '') ?></textarea>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition">
        Enregistrer
      </button>
      <a href="/v2/rh/formations/catalogue" class="border border-slate-200 text-slate-600 px-6 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
        Annuler
      </a>
    </div>
  </form>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
