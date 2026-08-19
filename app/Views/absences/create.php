<?php
$classes  = $classes  ?? [];
$creneaux = $creneaux ?? [];
$types    = $types    ?? [];
$old      = $old      ?? [];
$oldCreneauIds = array_map('intval', (array)($old['creneau_ids'] ?? []));
$oldModeCreneaux = !empty($oldCreneauIds);
$csrfToken = \Core\Session::getCsrfToken();
?>
<div class="max-w-3xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/absences/liste" class="hover:text-violet-600">Absences</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Saisie manuelle</span>
  </div>

  <div class="flex items-center justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="calendar-x" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Ajouter une absence</h1>
        <p class="text-slate-500 text-sm mt-0.5">Saisie manuelle d'une absence ou d'un retard pour un élève.</p>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/absences/liste"
       class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
    </a>
  </div>

  <form method="POST" action="<?= BASE_URL ?>/absences/store" id="formCreate"
        class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 space-y-6">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

    <div class="space-y-4">
      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">
        <i data-lucide="user" class="w-3.5 h-3.5"></i>
        Élève concerné
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Classe <span class="form-required">*</span></label>
          <select name="classe_id" id="selClasse" required
                  class="form-select">
            <option value="">Sélectionner</option>
            <?php foreach ($classes as $cl): ?>
            <option value="<?= $cl->id ?>" <?= ($old['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
              <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="form-label">Élève <span class="form-required">*</span></label>
          <select name="eleve_id" id="selEleve" required
                  class="form-select">
            <option value="">Sélectionnez une classe d'abord</option>
          </select>
        </div>
      </div>
    </div>

    <div class="space-y-4 pt-2 border-t border-slate-100">
      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide pt-4">
        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
        Détails de l'absence
      </div>

      <div>
        <label class="form-label">Date <span class="form-required">*</span></label>
        <input type="date" name="date_absence" required
               value="<?= htmlspecialchars($old['date_absence'] ?? date('Y-m-d'), ENT_QUOTES) ?>"
               max="<?= date('Y-m-d') ?>"
               class="form-input max-w-xs">
      </div>

      <div>
        <label class="form-label">Session <span class="form-required">*</span></label>
        <div class="flex items-center gap-4 mb-2">
          <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer">
            <input type="radio" name="session_mode" value="journee" id="modeJournee"
                   class="accent-violet-600" <?= !$oldModeCreneaux ? 'checked' : '' ?>>
            Journée complète
          </label>
          <?php if (!empty($creneaux)): ?>
          <label class="flex items-center gap-1.5 text-sm text-slate-600 cursor-pointer">
            <input type="radio" name="session_mode" value="creneaux" id="modeCreneaux"
                   class="accent-violet-600" <?= $oldModeCreneaux ? 'checked' : '' ?>>
            Créneau(x) précis
          </label>
          <?php endif; ?>
        </div>
        <?php if (!empty($creneaux)): ?>
        <div id="creneauxGroup" class="grid grid-cols-2 sm:grid-cols-3 gap-2"
             style="display:<?= $oldModeCreneaux ? 'grid' : 'none' ?>">
          <?php foreach ($creneaux as $c): ?>
          <label class="flex items-center gap-2 text-xs text-slate-600 border border-slate-200 rounded-lg px-2.5 py-2 hover:bg-slate-50 cursor-pointer">
            <input type="checkbox" name="creneau_ids[]" value="<?= $c->id ?>" class="accent-violet-600"
                   <?= in_array((int)$c->id, $oldCreneauIds, true) ? 'checked' : '' ?>>
            <span>
              <?= htmlspecialchars($c->nom, ENT_QUOTES) ?><br>
              <span class="text-slate-400"><?= substr($c->heure_debut, 0, 5) ?>–<?= substr($c->heure_fin, 0, 5) ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <p class="text-xs text-slate-400 mt-1.5">
          Cochez un ou plusieurs créneaux si l'élève n'a manqué qu'une partie de la session (ex : Cours 1 et 2, mais présent au Cours 3).
        </p>
        <?php endif; ?>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label">Type</label>
          <select name="type" id="selType"
                  class="form-select">
            <?php foreach ($types as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($old['type'] ?? 'absence') === $k ? 'selected' : '' ?>>
              <?= htmlspecialchars($v, ENT_QUOTES) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="dureeGroup" style="display:<?= ($old['type'] ?? 'absence') === 'retard' ? 'block' : 'none' ?>">
          <label class="form-label">Durée du retard (minutes)</label>
          <input type="number" name="duree_retard"
                 value="<?= (int)($old['duree_retard'] ?? 15) ?>" min="1" max="240"
                 class="form-input">
        </div>
      </div>
    </div>

    <div class="space-y-4 pt-2 border-t border-slate-100">
      <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide pt-4">
        <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
        Motif
      </div>

      <div>
        <label class="form-label">Motif (optionnel)</label>
        <input type="text" name="motif"
               value="<?= htmlspecialchars($old['motif'] ?? '', ENT_QUOTES) ?>"
               placeholder="Raison de l'absence ou du retard"
               class="form-input">
      </div>
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
      <a href="<?= BASE_URL ?>/absences/liste"
         class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Annuler</a>
      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
        <i data-lucide="check" class="w-4 h-4"></i>
        Enregistrer l'absence
      </button>
    </div>
  </form>
</div>

<script>
(function () {
    var selClasse  = document.getElementById('selClasse');
    var selEleve   = document.getElementById('selEleve');
    var selType    = document.getElementById('selType');
    var dureeGrp   = document.getElementById('dureeGroup');
    var oldEleveId = <?= json_encode($old['eleve_id'] ?? 0) ?>;
    var modeJournee  = document.getElementById('modeJournee');
    var modeCreneaux = document.getElementById('modeCreneaux');
    var creneauxGrp  = document.getElementById('creneauxGroup');
    var formCreate   = document.getElementById('formCreate');

    selType.addEventListener('change', function() {
        dureeGrp.style.display = this.value === 'retard' ? 'block' : 'none';
    });

    if (modeCreneaux && creneauxGrp) {
        [modeJournee, modeCreneaux].forEach(function(r) {
            r.addEventListener('change', function() {
                creneauxGrp.style.display = modeCreneaux.checked ? 'grid' : 'none';
            });
        });
        formCreate.addEventListener('submit', function(e) {
            if (modeCreneaux.checked) {
                var checked = creneauxGrp.querySelectorAll('input[type=checkbox]:checked');
                if (checked.length === 0) {
                    e.preventDefault();
                    alert('Cochez au moins un créneau, ou choisissez "Journée complète".');
                }
            }
        });
    }

    selClasse.addEventListener('change', function() {
        var classeId = this.value;
        selEleve.innerHTML = '<option value="">Chargement…</option>';
        if (!classeId) {
            selEleve.innerHTML = '<option value="">Sélectionnez d\'abord une classe</option>';
            return;
        }
        fetch('<?= BASE_URL ?>/api/eleves?classe_id=' + classeId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                selEleve.innerHTML = '<option value="">Sélectionner un élève</option>';
                (data.data || data).forEach(function(e) {
                    var opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.prenom + ' ' + e.nom + (e.matricule ? ' (' + e.matricule + ')' : '');
                    if (e.id == oldEleveId) opt.selected = true;
                    selEleve.appendChild(opt);
                });
            })
            .catch(function() {
                selEleve.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    });

    if (selClasse.value) selClasse.dispatchEvent(new Event('change'));
})();
</script>
