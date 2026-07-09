<?php
/** @var array $presence, $refs, $old, $errors */
/** @var string $model */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function val(string $k, string $def = ''): string {
    global $old, $presence;
    return htmlspecialchars($old[$k] ?? $presence[$k] ?? $def, ENT_QUOTES, 'UTF-8');
}
$isValide = ($presence['statut_validation'] ?? '') === 'valide';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier le pointage — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8 max-w-3xl">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-2">
    <a href="/v2/rh/presences" class="hover:text-violet-600">Présences</a>
    <span>/</span>
    <a href="/v2/rh/presences/<?= (int)$presence['id'] ?>" class="hover:text-violet-600"><?= e($presence['employe_nom'] ?? 'Employé') ?></a>
    <span>/</span><span>Modifier</span>
  </div>
  <h1 class="text-2xl font-bold text-slate-900 mb-6">Modifier le pointage</h1>

  <?php if ($isValide): ?>
  <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm flex items-start gap-3">
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    <div>
      <div class="font-semibold mb-0.5">Pointage validé</div>
      Ce pointage a été validé. La modification va effacer la validation et repasser le statut en <strong>En attente</strong>.
      Pour une correction mineure, préférez la <a href="/v2/rh/presences/<?= (int)$presence['id'] ?>" class="underline">régularisation</a>.
    </div>
  </div>
  <?php endif; ?>

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

  <form method="POST" action="/v2/rh/presences/<?= (int)$presence['id'] ?>" class="space-y-6">
    <?= \Core\Csrf::field() ?>

    <!-- Employé (lecture seule) -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Employé</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Employé</label>
          <div class="w-full border border-slate-100 bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-600">
            <?= e($presence['employe_nom'] ?? '—') ?>
            <?php if (!empty($presence['employe_matricule'])): ?>
              <span class="text-slate-400">(<?= e($presence['employe_matricule']) ?>)</span>
            <?php endif; ?>
          </div>
          <!-- Champ caché pour la validation -->
          <input type="hidden" name="employe_id" value="<?= (int)$presence['employe_id'] ?>">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Affectation active</label>
          <select name="affectation_id"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <option value="">Aucune / Non lié</option>
            <?php foreach ($refs['affectations'] as $aff): ?>
              <option value="<?= (int)$aff['id'] ?>"
                      <?= (int)val('affectation_id') === (int)$aff['id'] ? 'selected' : '' ?>>
                <?= e($aff['poste_intitule'] ?? 'Poste NC') ?> — <?= e($aff['departement_nom'] ?? '') ?>
                (<?= e($aff['type']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Date & Références -->
    <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Date & Références</h2>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Date de présence *</label>
          <input type="date" name="date_presence" required value="<?= val('date_presence') ?>"
                 max="<?= date('Y-m-d') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Heure d'arrivée théorique</label>
          <input type="time" name="heure_reference_arrivee" value="<?= val('heure_reference_arrivee', '08:00') ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Durée journée (min)</label>
          <input type="number" name="duree_reference_minutes" value="<?= val('duree_reference_minutes', '480') ?>"
                 min="60" max="720"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
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
              <option value="<?= e($k) ?>" <?= val('statut') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Mode de pointage *</label>
          <select name="mode_pointage" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
            <?php foreach ($model::MODES as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= val('mode_pointage') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <!-- Métriques actuelles -->
      <?php if ($presence['duree_minutes']): ?>
      <div class="mt-4 p-3 bg-slate-50 rounded-lg text-xs text-slate-600 flex gap-6">
        <span>Durée actuelle : <strong><?= e($model::formatDuree((int)$presence['duree_minutes'])) ?></strong></span>
        <?php if ((int)$presence['retard_minutes'] > 0): ?>
          <span>Retard : <strong class="text-amber-600"><?= e($model::formatDuree((int)$presence['retard_minutes'])) ?></strong></span>
        <?php endif; ?>
        <?php if ((int)$presence['heures_supp_minutes'] > 0): ?>
          <span>Heures sup : <strong class="text-violet-600"><?= e($model::formatDuree((int)$presence['heures_supp_minutes'])) ?></strong></span>
        <?php endif; ?>
        <span class="text-slate-400">Les valeurs seront recalculées à la sauvegarde</span>
      </div>
      <?php endif; ?>
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
        Enregistrer les modifications
      </button>
      <a href="/v2/rh/presences/<?= (int)$presence['id'] ?>"
         class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50 transition-colors">
        Annuler
      </a>
    </div>
  </form>
</main>

<script>
function calcDuree() {
    const a = document.getElementById('h-arrivee').value;
    const d = document.getElementById('h-depart').value;
    if (a && d && d > a) {
        const [ah, am] = a.split(':').map(Number);
        const [dh, dm] = d.split(':').map(Number);
        const mins = (dh * 60 + dm) - (ah * 60 + am);
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        console.debug('Durée: ' + (h > 0 ? (m > 0 ? h+'h'+m : h+'h') : m+'min'));
    }
}
document.getElementById('h-arrivee').addEventListener('change', calcDuree);
document.getElementById('h-depart').addEventListener('change', calcDuree);
</script>
</body>
</html>
