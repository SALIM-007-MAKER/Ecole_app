<?php
/** @var array $refs, $old, $errors */
/** @var string $model, $today */
/** @var int|null $preEmployeId */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, string $def = ''): string {
    global $old;
    return htmlspecialchars($old[$k] ?? $def, ENT_QUOTES, 'UTF-8');
}
?>

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/conges" class="hover:text-violet-600">Congés</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Nouvelle demande</span>
  </div>
  <div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="calendar-off" class="w-5 h-5 text-violet-600"></i>
    </div>
    <h1 class="text-2xl font-bold text-slate-900 pt-2">Nouvelle demande de congé</h1>
  </div>

  <?php if (!empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>
  <?php if (!empty($errors) && empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
    <?php foreach ($errors as $k => $err): ?>
      <?php if ($k !== 'global'): ?>
      <div><?= e(is_array($err) ? implode(', ', $err) : $err) ?></div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Employé -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Employé</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Employé *</label>
          <select name="employe_id" required id="sel-employe"
                  class="form-select">
            <option value="">Sélectionner un employé…</option>
            <?php foreach ($refs['employes'] as $emp): ?>
              <option value="<?= (int)$emp['id'] ?>"
                      <?= (int)val('employe_id', (string)($preEmployeId ?? 0)) === (int)$emp['id'] ? 'selected' : '' ?>>
                <?= e($emp['nom_complet']) ?> (<?= e($emp['matricule'] ?? '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Affectation liée</label>
          <select name="affectation_id" id="sel-affectation"
                  class="form-select">
            <option value="">Non lié</option>
            <?php foreach ($refs['affectations'] as $aff): ?>
              <option value="<?= (int)$aff['id'] ?>" <?= (int)val('affectation_id') === (int)$aff['id'] ? 'selected' : '' ?>>
                <?= e($aff['poste_intitule'] ?? '') ?> — <?= e($aff['departement_nom'] ?? '') ?> (<?= e($aff['type']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Type & Période -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Type & Période</h2>
      <div class="grid grid-cols-3 gap-4 mb-4">
        <div class="col-span-3 md:col-span-1">
          <label class="form-label">Type de congé *</label>
          <select name="type_conge_id" required id="sel-type"
                  class="form-select">
            <option value="">Choisir un type…</option>
            <?php foreach ($refs['typesConges'] as $t): ?>
              <option value="<?= (int)$t['id'] ?>"
                      data-max="<?= (int)($t['duree_max_jours'] ?? 0) ?>"
                      data-paye="<?= (int)$t['is_paye'] ?>"
                      <?= (int)val('type_conge_id') === (int)$t['id'] ? 'selected' : '' ?>>
                <?= e($t['libelle']) ?><?= $t['duree_max_jours'] ? ' (max '.$t['duree_max_jours'].'j)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Date de début *</label>
          <input type="date" name="date_debut" required value="<?= val('date_debut', $today) ?>" id="d-debut"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Date de fin *</label>
          <input type="date" name="date_fin" required value="<?= val('date_fin', $today) ?>" id="d-fin"
                 class="form-input">
        </div>
      </div>
      <!-- Info type sélectionné -->
      <div id="type-info" class="p-3 bg-slate-50 rounded-lg text-xs text-slate-600 hidden">
        <span id="type-info-text"></span>
      </div>
      <!-- Durée prévisionnelle -->
      <div id="preview-duree" class="mt-3 p-3 bg-violet-50 rounded-lg text-sm text-violet-700 hidden">
        Durée prévisionnelle : <strong id="preview-val"></strong> jours ouvrables
      </div>
    </div>

    <!-- Durée en heures (pour permissions) -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Options</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Durée en heures (permissions seulement)</label>
          <input type="number" name="duree_heures" value="<?= val('duree_heures') ?>"
                 min="0.5" max="24" step="0.5"
                 placeholder="Ex : 4 pour une demi-journée"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Contrat lié</label>
          <select name="contrat_id"
                  class="form-select">
            <option value="">Aucun / Automatique</option>
            <?php foreach ($refs['contrats'] as $ct): ?>
              <option value="<?= (int)$ct['id'] ?>" <?= (int)val('contrat_id') === (int)$ct['id'] ? 'selected' : '' ?>>
                <?= e($ct['type_contrat']) ?> — depuis <?= e(date('d/m/Y', strtotime($ct['date_debut']))) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Motif -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Motif</h2>
      <textarea name="motif" rows="3"
                class="form-textarea"
                placeholder="Précisez le motif si nécessaire (deuil, mariage, formation…)"><?= val('motif') ?></textarea>
    </div>

    <!-- Actions -->
    <div class="flex gap-3">
      <button type="submit"
              class="px-6 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
        Créer en brouillon
      </button>
      <a href="<?= BASE_URL ?>/v2/rh/conges"
         class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50 transition-colors">
        Annuler
      </a>
    </div>
  </form>
<script>
// Calcul prévisuel durée (jours ouvrables simple)
function calcDuree() {
    const debut = document.getElementById('d-debut').value;
    const fin   = document.getElementById('d-fin').value;
    const prev  = document.getElementById('preview-duree');
    if (debut && fin && fin >= debut) {
        let d = new Date(debut), f = new Date(fin), jours = 0;
        while (d <= f) {
            const dow = d.getDay();
            if (dow > 0 && dow < 6) jours++;
            d.setDate(d.getDate() + 1);
        }
        document.getElementById('preview-val').textContent = jours;
        prev.classList.remove('hidden');
    } else {
        prev.classList.add('hidden');
    }
}
document.getElementById('d-debut').addEventListener('change', calcDuree);
document.getElementById('d-fin').addEventListener('change', calcDuree);
// Info type sélectionné
document.getElementById('sel-type').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const info = document.getElementById('type-info');
    if (!this.value) { info.classList.add('hidden'); return; }
    const max  = parseInt(opt.dataset.max || 0);
    const paye = opt.dataset.paye === '1';
    let txt = paye ? '✓ Payé' : '✗ Non payé';
    if (max > 0) txt += ' · Maximum ' + max + ' jours';
    document.getElementById('type-info-text').textContent = txt;
    info.classList.remove('hidden');
});
// Filtre affectations par employé
document.getElementById('sel-employe').addEventListener('change', function() {
    window.location.href = '<?= BASE_URL ?>/v2/rh/conges/create?employe_id=' + this.value;
});
</script>
