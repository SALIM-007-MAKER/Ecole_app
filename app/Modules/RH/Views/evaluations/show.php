<?php
/** @var array $eval, $criteres, $historique, $plans */
/** @var string $model */
/** @var \App\Modules\RH\Evaluations\Policies\EvaluationPolicy $policy */
/** @var array $user */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$statut = $eval['statut'];
?>

  <!-- En-tête -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/evaluations" class="hover:text-violet-600">Évaluations</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Détail</span>
  </div>
  <div class="flex items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="star" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
      <h1 class="text-2xl font-bold text-slate-900"><?= e($eval['employe_nom_complet']) ?></h1>
      <div class="flex items-center gap-3 mt-2">
        <span class="px-3 py-1 rounded-full text-sm font-medium <?= $model::statutColor($statut) ?>">
          <?= $model::statutLabel($statut) ?>
        </span>
        <?php if ($eval['mention']): ?>
          <span class="px-2 py-1 rounded text-sm font-medium <?= $model::mentionColor($eval['mention']) ?>">
            <?= e($eval['mention']) ?>
          </span>
        <?php endif; ?>
        <span class="text-sm text-slate-500"><?= e($eval['campagne_libelle']) ?> (<?= (int)$eval['campagne_annee'] ?>)</span>
      </div>
      </div>
    </div>
    <!-- Boutons d'action -->
    <div class="flex flex-col gap-2 min-w-[160px]">
      <?php if ($statut === 'brouillon' && $policy->canUpdate($user)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/demarrer-auto-eval">
          <?= \Core\Csrf::field() ?>
          <button type="submit" class="w-full px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
            Démarrer auto-éval
          </button>
        </form>
      <?php endif; ?>
      <?php if ($statut === 'en_evaluation' && $policy->canUpdate($user)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/soumettre">
          <?= \Core\Csrf::field() ?>
          <button type="submit" class="w-full px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
            Soumettre pour validation
          </button>
        </form>
      <?php endif; ?>
      <?php if ($statut === 'soumise' && $policy->canValidate($user)): ?>
        <button onclick="document.getElementById('modal-valider').classList.remove('hidden')"
                class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
          Valider
        </button>
      <?php endif; ?>
      <?php if ($statut === 'validee' && $policy->canPublish($user)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/publier"
              onsubmit="return confirm('Publier cette évaluation à l\'employé ?')">
          <?= \Core\Csrf::field() ?>
          <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
            Publier
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-3 gap-6">
    <!-- Colonne principale -->
    <div class="col-span-2 space-y-6">

      <!-- Scores synthèse -->
      <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Scores</h2>
        <div class="grid grid-cols-3 gap-4 text-center">
          <?php foreach ([
            ['Auto-évaluation', $eval['score_auto_eval'], 'text-blue-600'],
            ['Évaluateur', $eval['score_evaluateur'], 'text-amber-600'],
            ['Score final', $eval['score_final'], 'text-emerald-600'],
          ] as [$label, $score, $color]): ?>
          <div class="p-3 bg-slate-50 rounded-lg">
            <div class="text-xs text-slate-500 mb-1"><?= $label ?></div>
            <div class="text-2xl font-bold <?= $score !== null ? $color : 'text-slate-300' ?>">
              <?= $score !== null ? number_format((float)$score, 2) : '—' ?>
            </div>
            <?php if ($score !== null): ?><div class="text-xs text-slate-400">/ 5</div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Critères -->
      <?php if (!empty($criteres)): ?>
      <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide mb-4">Critères d'évaluation</h2>

        <!-- Auto-évaluation -->
        <?php if (in_array($statut, ['en_auto_evaluation'], true) && ($policy->canSelfEvaluate($user, $eval) || $policy->canUpdate($user))): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/auto-eval" class="mb-6">
          <?= \Core\Csrf::field() ?>
          <h3 class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-3">Saisir l'auto-évaluation</h3>
          <?php foreach ($criteres as $cr): ?>
          <div class="grid grid-cols-5 gap-2 items-center mb-3 text-sm">
            <div class="col-span-2 text-slate-700"><?= e($cr['libelle']) ?></div>
            <div class="col-span-2">
              <input type="range" name="notes[<?= (int)$cr['critere_id'] ?>][note]"
                     min="<?= (int)$cr['note_min'] ?>" max="<?= (int)$cr['note_max'] ?>" step="0.5"
                     value="<?= number_format((float)($cr['note_auto_eval'] ?? ($cr['note_min'] ?? 0)), 1) ?>"
                     class="w-full accent-blue-600"
                     oninput="document.getElementById('ae-val-<?= (int)$cr['critere_id'] ?>').textContent=this.value">
            </div>
            <div class="text-center font-bold text-blue-700">
              <span id="ae-val-<?= (int)$cr['critere_id'] ?>">
                <?= number_format((float)($cr['note_auto_eval'] ?? 0), 1) ?>
              </span>
              / <?= (int)$cr['note_max'] ?>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="mt-4">
            <label class="form-label">Commentaire général</label>
            <textarea name="commentaire_auto_eval" rows="2"
                      class="form-textarea"><?= e($eval['commentaire_auto_eval'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="mt-3 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
            Soumettre l'auto-évaluation
          </button>
        </form>
        <?php endif; ?>

        <!-- Évaluation responsable -->
        <?php if ($statut === 'en_evaluation' && $policy->canEvaluateAsResponsable($user, $eval)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/evaluer">
          <?= \Core\Csrf::field() ?>
          <h3 class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-3">Évaluation du responsable</h3>
          <?php foreach ($criteres as $cr): ?>
          <div class="grid grid-cols-5 gap-2 items-center mb-3 text-sm">
            <div class="col-span-2">
              <div class="text-slate-700"><?= e($cr['libelle']) ?></div>
              <?php if ($cr['note_auto_eval'] !== null): ?>
              <div class="text-xs text-blue-500">Auto-éval : <?= number_format((float)$cr['note_auto_eval'], 1) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-span-2">
              <input type="range" name="notes[<?= (int)$cr['critere_id'] ?>][note]"
                     min="<?= (int)$cr['note_min'] ?>" max="<?= (int)$cr['note_max'] ?>" step="0.5"
                     value="<?= number_format((float)($cr['note_evaluateur'] ?? ($cr['note_auto_eval'] ?? 0)), 1) ?>"
                     class="w-full accent-amber-500"
                     oninput="document.getElementById('ev-val-<?= (int)$cr['critere_id'] ?>').textContent=this.value">
            </div>
            <div class="text-center font-bold text-amber-600">
              <span id="ev-val-<?= (int)$cr['critere_id'] ?>">
                <?= number_format((float)($cr['note_evaluateur'] ?? 0), 1) ?>
              </span>
              / <?= (int)$cr['note_max'] ?>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="mt-4">
            <label class="form-label">Commentaire responsable</label>
            <textarea name="commentaire_evaluateur" rows="2"
                      class="form-textarea"><?= e($eval['commentaire_evaluateur'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="mt-3 px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700">
            Enregistrer les notes
          </button>
        </form>
        <?php elseif (!empty($criteres) && $eval['score_evaluateur'] !== null): ?>
        <!-- Lecture seule -->
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Notes par critère</h3>
        <div class="space-y-2">
          <?php foreach ($criteres as $cr): ?>
          <div class="grid grid-cols-5 items-center gap-2 text-sm">
            <div class="col-span-2 text-slate-700"><?= e($cr['libelle']) ?></div>
            <div class="col-span-1 text-xs text-blue-500 text-right">Auto: <?= $cr['note_auto_eval'] !== null ? number_format((float)$cr['note_auto_eval'],1) : '—' ?></div>
            <div class="col-span-1 text-xs text-amber-600 text-right">Éval: <?= $cr['note_evaluateur'] !== null ? number_format((float)$cr['note_evaluateur'],1) : '—' ?></div>
            <div class="col-span-1 text-xs text-slate-400 text-right">poids <?= number_format((float)$cr['poids_effectif'],1) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Plans de développement -->
      <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm" id="plans">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Plans de développement</h2>
          <?php if (in_array($statut, ['validee','publiee'], true) && $policy->canUpdate($user)): ?>
          <button onclick="document.getElementById('modal-plan').classList.remove('hidden')"
                  class="px-3 py-1 bg-violet-600 text-white rounded text-xs font-medium hover:bg-violet-700">
            + Ajouter
          </button>
          <?php endif; ?>
        </div>
        <?php if (empty($plans)): ?>
          <p class="text-sm text-slate-400">Aucun plan de développement.</p>
        <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($plans as $plan): ?>
          <div class="border border-slate-100 rounded-lg p-3">
            <div class="flex items-start justify-between gap-2">
              <div class="text-sm font-medium text-slate-800"><?= e($plan['objectif']) ?></div>
              <span class="flex-shrink-0 px-2 py-0.5 rounded text-xs <?= $plan['statut'] === 'realise' ? 'bg-emerald-100 text-emerald-700' : ($plan['statut'] === 'abandonne' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                <?= e($plan['statut']) ?>
              </span>
            </div>
            <?php if ($plan['actions']): ?>
              <p class="text-xs text-slate-500 mt-1"><?= e($plan['actions']) ?></p>
            <?php endif; ?>
            <div class="flex gap-4 mt-2 text-xs text-slate-400">
              <?php if ($plan['echeance']): ?><span>Échéance : <?= e(date('d/m/Y', strtotime($plan['echeance']))) ?></span><?php endif; ?>
              <span>Progression : <?= (int)$plan['progression'] ?>%</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Colonne droite -->
    <div class="space-y-6">
      <!-- Infos -->
      <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm text-sm">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Informations</h2>
        <dl class="space-y-2">
          <div class="flex justify-between"><dt class="text-slate-500">Matricule</dt><dd><?= e($eval['employe_matricule'] ?? '—') ?></dd></dl>
          <div class="flex justify-between"><dt class="text-slate-500">Département</dt><dd><?= e($eval['departement_nom'] ?? '—') ?></dd></dl>
          <div class="flex justify-between"><dt class="text-slate-500">Campagne</dt><dd><?= e($eval['campagne_code']) ?></dd></dl>
          <div class="flex justify-between"><dt class="text-slate-500">Période</dt><dd><?= (int)$eval['campagne_annee'] ?></dd></dl>
          <?php if ($eval['date_validation']): ?>
          <div class="flex justify-between"><dt class="text-slate-500">Validée le</dt><dd><?= e(date('d/m/Y', strtotime($eval['date_validation']))) ?></dd></dl>
          <div class="flex justify-between"><dt class="text-slate-500">Par</dt><dd><?= e($eval['valide_par_nom'] ?? '—') ?></dd></dl>
          <?php endif; ?>
          <?php if ($eval['date_publication']): ?>
          <div class="flex justify-between"><dt class="text-slate-500">Publiée le</dt><dd><?= e(date('d/m/Y', strtotime($eval['date_publication']))) ?></dd></dl>
          <?php endif; ?>
        </dl>
        <?php if ($eval['commentaire_validation']): ?>
        <div class="mt-3 p-2 bg-slate-50 rounded text-xs text-slate-600 italic">
          "<?= e($eval['commentaire_validation']) ?>"
        </div>
        <?php endif; ?>
      </div>

      <!-- Historique -->
      <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Historique</h2>
        <div class="relative">
          <div class="absolute left-2.5 top-0 bottom-0 w-0.5 bg-slate-100"></div>
          <div class="space-y-3">
            <?php foreach ($historique as $h): ?>
            <div class="relative pl-7">
              <div class="absolute left-0 top-1 w-5 h-5 rounded-full bg-violet-100 border-2 border-violet-300 flex items-center justify-center">
                <div class="w-1.5 h-1.5 rounded-full bg-violet-500"></div>
              </div>
              <div class="text-xs text-slate-700 font-medium"><?= e($h['action']) ?></div>
              <div class="text-xs text-slate-400"><?= e($h['effectue_par_nom']) ?> · <?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?></div>
              <?php if ($h['commentaire']): ?>
              <div class="text-xs text-slate-500 italic mt-0.5"><?= e($h['commentaire']) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- Modal Valider -->
<div id="modal-valider" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Valider l'évaluation</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/valider">
      <?= \Core\Csrf::field() ?>
      <label class="form-label">Commentaire de validation</label>
      <textarea name="commentaire_validation" rows="3"
                class="form-textarea mb-4"
                placeholder="Commentaire optionnel…"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
          Confirmer la validation
        </button>
        <button type="button" onclick="document.getElementById('modal-valider').classList.add('hidden')"
                class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>
<!-- Modal Plan développement -->
<div id="modal-plan" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-xl">
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Nouveau plan de développement</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/evaluations/<?= (int)$eval['id'] ?>/plan" class="space-y-4">
      <?= \Core\Csrf::field() ?>
      <div>
        <label class="form-label">Objectif *</label>
        <textarea name="objectif" rows="2" required
                  class="form-textarea"></textarea>
      </div>
      <div>
        <label class="form-label">Actions à mettre en œuvre</label>
        <textarea name="actions" rows="2"
                  class="form-textarea"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">Ressources nécessaires</label>
          <input type="text" name="ressources"
                 class="form-input">
        </div>
        <div>
          <label class="form-label">Échéance</label>
          <input type="date" name="echeance"
                 class="form-input">
        </div>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
          Créer le plan
        </button>
        <button type="button" onclick="document.getElementById('modal-plan').classList.add('hidden')"
                class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>
