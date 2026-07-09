<?php
/** @var array $presence, $regularisations */
/** @var string $model */
/** @var bool $canUpdate, $canValidate */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$retard   = (int)($presence['retard_minutes']    ?? 0);
$heuresSup= (int)($presence['heures_supp_minutes']?? 0);
$estValide= $presence['statut_validation'] === 'valide';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pointage — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">
<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>
<main class="ml-64 p-8">

  <!-- En-tête -->
  <div class="flex items-start justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="/v2/rh/presences" class="hover:text-violet-600">Présences</a>
        <span>/</span>
        <span><?= e($presence['employe_nom']) ?></span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
        <?= e($presence['employe_nom']) ?>
        <span class="px-2 py-0.5 text-sm rounded-full <?= $model::statutColor($presence['statut']) ?>">
          <?= e($model::statutLabel($presence['statut'])) ?>
        </span>
        <span class="px-2 py-0.5 text-sm rounded-full <?= $model::validationColor($presence['statut_validation']) ?>">
          <?= e($model::validationLabel($presence['statut_validation'])) ?>
        </span>
      </h1>
      <div class="text-slate-500 text-sm mt-1"><?= e($presence['employe_matricule'] ?? '') ?> · <?= e(date('l d F Y', strtotime($presence['date_presence']))) ?></div>
    </div>
    <div class="flex gap-3">
      <?php if ($canUpdate && !$estValide): ?>
      <a href="/v2/rh/presences/<?= (int)$presence['id'] ?>/edit"
         class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
        Modifier
      </a>
      <?php endif; ?>
      <?php if ($canValidate && $presence['statut_validation'] === 'en_attente'): ?>
      <button onclick="document.getElementById('modal-valider').classList.remove('hidden')"
              class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">
        Valider / Rejeter
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <!-- Grille principale -->
  <div class="grid grid-cols-3 gap-6 mb-6">

    <!-- Pointage -->
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 mb-4 uppercase tracking-wide">Pointage</h2>
      <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
          <dt class="text-slate-500">Arrivée</dt>
          <dd class="font-medium"><?= $presence['heure_arrivee'] ? e(substr($presence['heure_arrivee'], 0, 5)) : '—' ?></dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-slate-500">Départ</dt>
          <dd class="font-medium"><?= $presence['heure_depart'] ? e(substr($presence['heure_depart'], 0, 5)) : '—' ?></dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-slate-500">Durée effective</dt>
          <dd class="font-semibold text-slate-900"><?= e($model::formatDuree((int)($presence['duree_minutes'] ?? 0))) ?></dd>
        </div>
        <div class="flex justify-between pt-2 border-t border-slate-50">
          <dt class="text-slate-500">Mode</dt>
          <dd>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::modeColor($presence['mode_pointage']) ?>">
              <?= e($model::modeLabel($presence['mode_pointage'])) ?>
            </span>
          </dd>
        </div>
        <?php if (!empty($presence['source_id'])): ?>
        <div class="flex justify-between">
          <dt class="text-slate-500">Source ID</dt>
          <dd class="font-mono text-xs"><?= e($presence['source_id']) ?></dd>
        </div>
        <?php endif; ?>
      </dl>
    </div>

    <!-- Métriques -->
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 mb-4 uppercase tracking-wide">Métriques</h2>
      <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
          <dt class="text-slate-500">Heure référence</dt>
          <dd class="font-medium"><?= e(substr($presence['heure_reference_arrivee'] ?? '08:00', 0, 5)) ?></dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-slate-500">Durée référence</dt>
          <dd class="font-medium"><?= e($model::formatDuree((int)($presence['duree_reference_minutes'] ?? 480))) ?></dd>
        </div>
        <div class="flex justify-between pt-2 border-t border-slate-50">
          <dt class="text-slate-500">Retard</dt>
          <dd class="<?= $retard > 0 ? $model::alerteRetard($retard) : 'text-emerald-600' ?>">
            <?= $retard > 0 ? '+' . e($model::formatDuree($retard)) : 'Aucun' ?>
          </dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-slate-500">Heures supplémentaires</dt>
          <dd class="<?= $heuresSup > 0 ? 'text-violet-700 font-medium' : 'text-slate-400' ?>">
            <?= $heuresSup > 0 ? '+' . e($model::formatDuree($heuresSup)) : '—' ?>
          </dd>
        </div>
        <?php if (!empty($presence['affectation_type'])): ?>
        <div class="flex justify-between pt-2 border-t border-slate-50">
          <dt class="text-slate-500">Affectation</dt>
          <dd class="text-xs text-slate-600"><?= e($presence['poste_intitule'] ?? 'N/A') ?> · <?= e($presence['departement_nom'] ?? '') ?></dd>
        </div>
        <?php endif; ?>
      </dl>
    </div>

    <!-- Validation & Justification -->
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <h2 class="text-sm font-semibold text-slate-700 mb-4 uppercase tracking-wide">Validation</h2>
      <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
          <dt class="text-slate-500">Statut</dt>
          <dd>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::validationColor($presence['statut_validation']) ?>">
              <?= e($model::validationLabel($presence['statut_validation'])) ?>
            </span>
          </dd>
        </div>
        <?php if ($presence['valide_par']): ?>
        <div class="flex justify-between">
          <dt class="text-slate-500">Traitée le</dt>
          <dd><?= e(date('d/m/Y H:i', strtotime($presence['date_validation']))) ?></dd>
        </div>
        <?php endif; ?>
        <?php if (!empty($presence['motif_rejet'])): ?>
        <div class="pt-2 border-t border-slate-50">
          <dt class="text-slate-500 mb-1">Motif de rejet</dt>
          <dd class="text-red-700 text-xs"><?= e($presence['motif_rejet']) ?></dd>
        </div>
        <?php endif; ?>
      </dl>

      <?php if (!empty($presence['justification'])): ?>
      <div class="mt-4 pt-4 border-t border-slate-100">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Justification</div>
        <p class="text-sm text-slate-700 bg-slate-50 rounded-lg p-3"><?= e($presence['justification']) ?></p>
        <?php if ($presence['date_justification']): ?>
        <div class="text-xs text-slate-400 mt-1"><?= e(date('d/m/Y H:i', strtotime($presence['date_justification']))) ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Actions secondaires -->
      <div class="mt-4 pt-4 border-t border-slate-100 flex flex-col gap-2">
        <?php if ($canUpdate && !$estValide): ?>
        <button onclick="document.getElementById('modal-justifier').classList.remove('hidden')"
                class="w-full px-3 py-2 bg-sky-50 border border-sky-200 text-sky-700 rounded-lg text-xs hover:bg-sky-100">
          Ajouter une justification
        </button>
        <button onclick="document.getElementById('modal-regulariser').classList.remove('hidden')"
                class="w-full px-3 py-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg text-xs hover:bg-amber-100">
          Régulariser
        </button>
        <button onclick="document.getElementById('modal-archiver').classList.remove('hidden')"
                class="w-full px-3 py-2 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs hover:bg-red-100">
          Archiver
        </button>
        <?php endif; ?>
        <?php if (!empty($presence['motif'])): ?>
        <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-2">
          <span class="font-medium">Motif :</span> <?= e($presence['motif']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Régularisations -->
  <?php if (!empty($regularisations)): ?>
  <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm mb-6">
    <h2 class="text-sm font-semibold text-slate-700 mb-4 uppercase tracking-wide">
      Historique des régularisations (<?= count($regularisations) ?>)
    </h2>
    <div class="relative pl-6">
      <div class="absolute left-2 top-0 bottom-0 w-px bg-slate-200"></div>
      <?php foreach ($regularisations as $reg): ?>
      <div class="relative mb-5">
        <div class="absolute -left-4 top-1 w-3 h-3 rounded-full bg-white border-2 border-slate-300"></div>
        <div class="bg-slate-50 rounded-lg p-3">
          <div class="flex items-center justify-between mb-2">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::regTypeColor($reg['type_regularisation']) ?>">
              <?= e($model::regTypeLabel($reg['type_regularisation'])) ?>
            </span>
            <span class="text-xs text-slate-400"><?= e(date('d/m/Y H:i', strtotime($reg['created_at']))) ?></span>
          </div>
          <div class="text-sm text-slate-700 mb-1"><?= e($reg['motif']) ?></div>
          <div class="text-xs text-slate-500">par <span class="font-medium"><?= e($reg['regularise_par_nom']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</main>

<!-- Modal Valider/Rejeter -->
<div id="modal-valider" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Valider ou rejeter ce pointage</h3>
    <form method="POST" action="/v2/rh/presences/<?= (int)$presence['id'] ?>/valider">
      <?= \Core\Csrf::field() ?>
      <div class="flex gap-3 mb-4">
        <label class="flex-1 flex items-center gap-2 p-3 border-2 rounded-xl cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
          <input type="radio" name="decision" value="valide" required class="accent-emerald-600">
          <span class="text-sm font-medium text-emerald-700">Valider</span>
        </label>
        <label class="flex-1 flex items-center gap-2 p-3 border-2 rounded-xl cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
          <input type="radio" name="decision" value="rejete" class="accent-red-600">
          <span class="text-sm font-medium text-red-700">Rejeter</span>
        </label>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Motif de rejet (obligatoire si rejet)</label>
        <textarea name="motif_rejet" rows="2"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none"></textarea>
      </div>
      <div class="flex gap-3 mt-4">
        <button type="submit" class="flex-1 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Confirmer</button>
        <button type="button" onclick="document.getElementById('modal-valider').classList.add('hidden')"
                class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Justifier -->
<div id="modal-justifier" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Ajouter une justification</h3>
    <form method="POST" action="/v2/rh/presences/<?= (int)$presence['id'] ?>/justifier">
      <?= \Core\Csrf::field() ?>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Justification *</label>
        <textarea name="justification" rows="4" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none"
                  placeholder="Expliquer la situation (maladie, transport, etc.)"><?= e($presence['justification'] ?? '') ?></textarea>
      </div>
      <div class="flex gap-3 mt-4">
        <button type="submit" class="flex-1 px-4 py-2 bg-sky-600 text-white rounded-lg text-sm hover:bg-sky-700">Enregistrer</button>
        <button type="button" onclick="document.getElementById('modal-justifier').classList.add('hidden')"
                class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Régulariser -->
<div id="modal-regulariser" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
    <h3 class="text-lg font-semibold mb-4">Régularisation</h3>
    <form method="POST" action="/v2/rh/presences/<?= (int)$presence['id'] ?>/regulariser">
      <?= \Core\Csrf::field() ?>
      <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-1">Type de régularisation *</label>
        <select name="type_regularisation" id="reg-type" required
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none">
          <?php foreach ($model::REG_TYPES as $k => $v): ?>
            <?php if ($k !== 'annulation'): ?>
            <option value="<?= e($k) ?>"><?= e($v) ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="reg-heures" class="mb-4 grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nouvelle heure d'arrivée</label>
          <input type="time" name="heure_arrivee" value="<?= e(substr($presence['heure_arrivee'] ?? '', 0, 5)) ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nouvelle heure de départ</label>
          <input type="time" name="heure_depart" value="<?= e(substr($presence['heure_depart'] ?? '', 0, 5)) ?>"
                 class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
        </div>
      </div>
      <div id="reg-statut" class="mb-4 hidden">
        <label class="block text-sm font-medium text-slate-700 mb-1">Nouveau statut</label>
        <select name="nouveau_statut" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
          <?php foreach ($model::STATUTS as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $presence['statut'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Motif *</label>
        <textarea name="motif" rows="2" required
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-300 outline-none"
                  placeholder="Raison de la régularisation"></textarea>
      </div>
      <div class="flex gap-3 mt-4">
        <button type="submit" class="flex-1 px-4 py-2 bg-amber-600 text-white rounded-lg text-sm hover:bg-amber-700">Régulariser</button>
        <button type="button" onclick="document.getElementById('modal-regulariser').classList.add('hidden')"
                class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Archiver -->
<div id="modal-archiver" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
    <h3 class="text-lg font-semibold text-red-700 mb-4">Archiver ce pointage</h3>
    <p class="text-sm text-slate-600 mb-4">Cette action masquera le pointage. Une régularisation sera enregistrée.</p>
    <form method="POST" action="/v2/rh/presences/<?= (int)$presence['id'] ?>/archive">
      <?= \Core\Csrf::field() ?>
      <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-1">Motif</label>
        <input type="text" name="motif" value="Archivage manuel"
               class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
      </div>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">Archiver</button>
        <button type="button" onclick="document.getElementById('modal-archiver').classList.add('hidden')"
                class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
// Afficher/masquer les champs selon le type de régularisation
document.getElementById('reg-type')?.addEventListener('change', function() {
    const heures = document.getElementById('reg-heures');
    const statut = document.getElementById('reg-statut');
    if (this.value === 'correction_heure') {
        heures.classList.remove('hidden');
        statut.classList.add('hidden');
    } else if (this.value === 'correction_statut') {
        heures.classList.add('hidden');
        statut.classList.remove('hidden');
    } else {
        heures.classList.add('hidden');
        statut.classList.add('hidden');
    }
});
</script>
</body>
</html>
