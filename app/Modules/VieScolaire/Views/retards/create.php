<?php
$title = 'Saisir un retard';
$errors   = $_SESSION['errors']    ?? [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['errors'], $_SESSION['old_input']);
?>
<div class="max-w-3xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards" class="hover:text-violet-600">Retards</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Saisie manuelle</span>
  </div>

  <div class="flex items-start gap-4 mb-6">
    <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
      <i data-lucide="clock" class="w-5 h-5 text-violet-600"></i>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Saisir un retard</h1>
      <p class="text-slate-500 text-sm mt-0.5">Enregistrez le retard d'un élève et suivez sa justification.</p>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm flex items-start gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
    <ul class="list-disc list-inside space-y-0.5">
      <?php foreach ($errors as $field => $msgs): foreach ($msgs as $m): ?>
      <li><?= htmlspecialchars($m) ?></li>
      <?php endforeach; endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/retards"
        class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 space-y-6">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

    <div class="space-y-4">
      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
        <i data-lucide="user" class="w-3.5 h-3.5"></i>
        Élève concerné
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Classe <span class="form-required">*</span></label>
          <select name="classe_id" id="classe_id" required class="form-select">
            <option value="">Sélectionner</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c->id ?>" <?= ($oldInput['classe_id'] ?? '') == $c->id ? 'selected' : '' ?>>
              <?= htmlspecialchars($c->nom) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label">Élève <span class="form-required">*</span></label>
          <select name="eleve_id" id="eleve_id" required class="form-select">
            <option value="">Sélectionner une classe d'abord</option>
            <?php foreach ($eleves as $e): ?>
            <option value="<?= $e->id ?>" data-classe="<?= $e->classe_id ?>"
                    <?= ($oldInput['eleve_id'] ?? '') == $e->id ? 'selected' : '' ?>>
              <?= htmlspecialchars($e->nom . ' ' . $e->prenom) ?><?= $e->matricule ? ' — ' . htmlspecialchars($e->matricule) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="space-y-4 pt-2 border-t border-slate-100">
      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide pt-4">
        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
        Détails du retard
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Date <span class="form-required">*</span></label>
          <input type="date" name="date_retard" required
                 value="<?= htmlspecialchars($oldInput['date_retard'] ?? date('Y-m-d')) ?>"
                 class="form-input">
        </div>

        <div>
          <label class="form-label">Année scolaire <span class="form-required">*</span></label>
          <input type="text" name="annee_scolaire" required placeholder="ex: 2024-2025"
                 value="<?= htmlspecialchars($oldInput['annee_scolaire'] ?? (date('Y') . '-' . (date('Y') + 1))) ?>"
                 class="form-input">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Heure prévue</label>
          <input type="time" name="heure_prevue"
                 value="<?= htmlspecialchars($oldInput['heure_prevue'] ?? '') ?>"
                 class="form-input">
          <p class="form-hint">Heure normale d'entrée en classe (optionnel).</p>
        </div>

        <div>
          <label class="form-label">Heure d'arrivée <span class="form-required">*</span></label>
          <input type="time" name="heure_arrivee" required
                 value="<?= htmlspecialchars($oldInput['heure_arrivee'] ?? '') ?>"
                 class="form-input">
        </div>
      </div>
    </div>

    <div class="space-y-4 pt-2 border-t border-slate-100">
      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide pt-4">
        <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
        Observation
      </div>

      <div>
        <label class="form-label">Note (optionnel)</label>
        <textarea name="observation" rows="3" placeholder="Observation optionnelle..."
                  class="form-textarea"><?= htmlspecialchars($oldInput['observation'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards"
         class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Annuler</a>
      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        <i data-lucide="check" class="w-4 h-4"></i>
        Enregistrer le retard
      </button>
    </div>
  </form>
</div>

<script>
(function () {
  const classeSelect = document.getElementById('classe_id');
  const eleveSelect  = document.getElementById('eleve_id');
  const eleveOptions = Array.from(eleveSelect.options).slice(1);

  function filterEleves() {
    const classeId  = classeSelect.value;
    const selected  = eleveSelect.value;
    eleveSelect.innerHTML = '';
    eleveSelect.appendChild(new Option(classeId ? 'Sélectionner' : 'Sélectionner une classe d\'abord', ''));
    eleveOptions.forEach(function (opt) {
      if (classeId && opt.dataset.classe === classeId) {
        eleveSelect.appendChild(opt.cloneNode(true));
      }
    });
    if ([...eleveSelect.options].some(function (o) { return o.value === selected; })) {
      eleveSelect.value = selected;
    }
  }

  classeSelect.addEventListener('change', filterEleves);
  filterEleves();
})();
</script>
