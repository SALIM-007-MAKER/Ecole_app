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
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau pointage — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
    <a href="/v2/rh/presences" class="hover:text-violet-600">Présences</a>
    <span>/</span><span>Nouveau pointage</span>
  </div>
  <h1 class="text-2xl font-bold text-slate-900 mb-6">Enregistrer un pointage</h1>

  <?php if (!empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($errors['global']) ?></div>
  <?php endif; ?>
  <?php if (!empty($errors) && empty($errors['global'])): ?>
  <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
    <?php foreach ($errors as $err): ?>
      <div><?= e(is_array($err) ? implode(', ', $err) : $err) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="/v2/rh/presences" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Qui -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Employé</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Employé *</label>
          <select name="employe_id" required id="sel-employe"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <option value="">Sélectionner un employé…</option>
            <?php foreach ($refs['employes'] as $emp): ?>
              <option value="<?= (int)$emp['id'] ?>"
                      data-matricule="<?= e($emp['matricule'] ?? '') ?>"
                      <?= (int)($old['employe_id'] ?? $preEmployeId ?? 0) === (int)$emp['id'] ? 'selected' : '' ?>>
                <?= e($emp['nom_complet']) ?> (<?= e($emp['matricule'] ?? '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Affectation active</label>
          <select name="affectation_id" id="sel-affectation"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <option value="">Aucune / Non lié</option>
            <?php foreach ($refs['affectations'] as $aff): ?>
              <option value="<?= (int)$aff['id'] ?>"
                      data-employe="<?= (int)($aff['employe_id'] ?? 0) ?>"
                      <?= (int)($old['affectation_id'] ?? 0) === (int)$aff['id'] ? 'selected' : '' ?>>
                <?= e($aff['poste_intitule'] ?? 'Poste NC') ?> — <?= e($aff['departement_nom'] ?? '') ?>
                (<?= e($aff['type']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Quand -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Date & Références</h2>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Date de présence *</label>
          <input type="date" name="date_presence" required value="<?= val('date_presence', $today) ?>"
                 max="<?= e($today) ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Heure d'arrivée théorique</label>
          <input type="time" name="heure_reference_arrivee" value="<?= val('heure_reference_arrivee', '08:00') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
          <p class="text-xs text-slate-400 mt-1">Seuil de détection du retard</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Durée journée (min)</label>
          <input type="number" name="duree_reference_minutes" value="<?= val('duree_reference_minutes', '480') ?>"
                 min="60" max="720"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
          <p class="text-xs text-slate-400 mt-1">Seuil heures supplémentaires (480 = 8h)</p>
        </div>
      </div>
    </div>

    <!-- Pointage -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Pointage</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Heure d'arrivée</label>
          <input type="time" name="heure_arrivee" value="<?= val('heure_arrivee') ?>" id="h-arrivee"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Heure de départ</label>
          <input type="time" name="heure_depart" value="<?= val('heure_depart') ?>" id="h-depart"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Statut *</label>
          <select name="statut" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <?php foreach ($model::STATUTS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= val('statut', 'present') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Mode de pointage *</label>
          <select name="mode_pointage" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <?php foreach ($model::MODES as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= val('mode_pointage', 'manuel') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <!-- Prévisualisation durée -->
      <div id="preview-duree" class="mt-4 p-3 bg-slate-50 rounded-lg text-sm text-slate-600 hidden">
        Durée calculée : <span id="preview-val" class="font-semibold text-slate-900"></span>
      </div>
    </div>

    <!-- Notes -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Notes & Motif</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motif</label>
          <input type="text" name="motif" value="<?= val('motif') ?>"
                 placeholder="Mission, formation, déplacement…"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
          <input type="text" name="notes" value="<?= val('notes') ?>"
                 placeholder="Remarques libres"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-3">
      <button type="submit"
              class="px-6 py-2.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
        Enregistrer le pointage
      </button>
      <a href="/v2/rh/presences"
         class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50 transition-colors">
        Annuler
      </a>
    </div>
  </form>
</main>

<script>
// Calcul prévisualisation durée
function calcDuree() {
    const a = document.getElementById('h-arrivee').value;
    const d = document.getElementById('h-depart').value;
    const prev = document.getElementById('preview-duree');
    if (a && d && d > a) {
        const [ah, am] = a.split(':').map(Number);
        const [dh, dm] = d.split(':').map(Number);
        const mins = (dh * 60 + dm) - (ah * 60 + am);
        const h    = Math.floor(mins / 60);
        const m    = mins % 60;
        document.getElementById('preview-val').textContent = h > 0 ? (m > 0 ? h+'h'+m : h+'h') : m+'min';
        prev.classList.remove('hidden');
    } else {
        prev.classList.add('hidden');
    }
}
document.getElementById('h-arrivee').addEventListener('change', calcDuree);
document.getElementById('h-depart').addEventListener('change', calcDuree);

// Filtre des affectations par employé
document.getElementById('sel-employe').addEventListener('change', function() {
    const eid = parseInt(this.value);
    const sel = document.getElementById('sel-affectation');
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        const optEid = parseInt(opt.dataset.employe || 0);
        opt.style.display = (optEid === eid || !eid) ? '' : 'none';
    });
    sel.value = '';
});
</script>
</body>
</html>
