<?php
/** @var array $conge, $historique */
/** @var string $model */
/** @var bool $canUpdate, $canApprove, $canReject, $canCancel */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$statut = $conge['statut'] ?? '';
?>

  <!-- Breadcrumb & en-tête -->
  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/rh/conges" class="hover:text-violet-600">Congés</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700"><?= e($conge['employe_nom_complet'] ?? '—') ?></span>
  </div>

  <div class="flex items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="calendar-off" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
      <div class="flex items-center gap-3 mb-1">
        <h1 class="text-2xl font-bold text-slate-900"><?= e($conge['type_libelle']) ?></h1>
        <span class="px-3 py-1 rounded-full text-sm font-medium <?= $model::statutColor($statut) ?>">
          <?= e($model::statutLabel($statut)) ?>
        </span>
        <?php if (!($conge['is_paye'] ?? true)): ?>
          <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">Non payé</span>
        <?php endif; ?>
      </div>
      <p class="text-slate-500"><?= e($conge['employe_nom_complet'] ?? '—') ?> · <?= e($conge['employe_matricule'] ?? '') ?></p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap justify-end flex-shrink-0">
      <?php if ($canUpdate && $statut === 'brouillon'): ?>
        <a href="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/edit"
           class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">Modifier</a>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/soumettre"
              onsubmit="return confirm('Soumettre cette demande pour approbation ?')">
          <?= \Core\Csrf::field() ?>
          <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
            Soumettre
          </button>
        </form>
      <?php endif; ?>
      <?php if ($canApprove && $statut === 'soumis'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/approuver"
              onsubmit="return confirm('Approuver ce congé ?')">
          <?= \Core\Csrf::field() ?>
          <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">Approuver</button>
        </form>
        <button onclick="document.getElementById('modal-rejeter').classList.remove('hidden')"
                class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700">Rejeter</button>
      <?php endif; ?>
      <?php if ($canUpdate && $statut === 'approuve'): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/demarrer"
              onsubmit="return confirm('Marquer ce congé comme en cours ?')">
          <?= \Core\Csrf::field() ?>
          <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Démarrer</button>
        </form>
      <?php endif; ?>
      <?php if ($canUpdate && $statut === 'en_cours'): ?>
        <button onclick="document.getElementById('modal-terminer').classList.remove('hidden')"
                class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">Terminer</button>
      <?php endif; ?>
      <?php if ($canCancel && in_array($statut, ['soumis','approuve','en_cours'])): ?>
        <button onclick="document.getElementById('modal-annuler').classList.remove('hidden')"
                class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm hover:bg-slate-300">Annuler</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <!-- Informations -->
  <div class="grid grid-cols-3 gap-5 mb-6">
    <!-- Période -->
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Période</h2>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-slate-500">Début</span><strong><?= e(date('d/m/Y', strtotime($conge['date_debut']))) ?></strong></div>
        <div class="flex justify-between"><span class="text-slate-500">Fin</span><strong><?= e(date('d/m/Y', strtotime($conge['date_fin']))) ?></strong></div>
        <div class="flex justify-between border-t border-slate-50 pt-2 mt-2">
          <span class="text-slate-500">Durée</span>
          <strong class="text-violet-700">
            <?= e($model::formatDuree((float)$conge['duree_jours'], $conge['duree_heures'] ? (float)$conge['duree_heures'] : null)) ?>
          </strong>
        </div>
        <?php if ($conge['date_retour_effectif']): ?>
        <div class="flex justify-between"><span class="text-slate-500">Retour effectif</span><strong><?= e(date('d/m/Y', strtotime($conge['date_retour_effectif']))) ?></strong></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Type & Motif -->
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Type & Motif</h2>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-slate-500">Type</span><strong><?= e($conge['type_libelle']) ?></strong></div>
        <div class="flex justify-between"><span class="text-slate-500">Payé</span><span class="<?= $conge['is_paye'] ? 'text-emerald-600' : 'text-red-600' ?> font-medium"><?= $conge['is_paye'] ? 'Oui' : 'Non' ?></span></div>
        <?php if ($conge['motif']): ?>
        <div class="border-t border-slate-50 pt-2">
          <div class="text-slate-500 mb-1">Motif</div>
          <div class="text-slate-700 italic">"<?= e($conge['motif']) ?>"</div>
        </div>
        <?php endif; ?>
        <?php if ($conge['motif_rejet']): ?>
        <div class="border-t border-slate-50 pt-2">
          <div class="text-red-500 mb-1">Motif de rejet</div>
          <div class="text-red-700"><?= e($conge['motif_rejet']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($conge['motif_annulation']): ?>
        <div class="border-t border-slate-50 pt-2">
          <div class="text-slate-500 mb-1">Motif d'annulation</div>
          <div class="text-slate-700"><?= e($conge['motif_annulation']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Approbation -->
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm">
      <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Approbation</h2>
      <div class="space-y-2 text-sm">
        <?php if ($conge['approuve_par_nom']): ?>
        <div class="flex justify-between"><span class="text-slate-500">Approuvé par</span><strong><?= e($conge['approuve_par_nom']) ?></strong></div>
        <div class="flex justify-between"><span class="text-slate-500">Date</span><span><?= e(date('d/m/Y H:i', strtotime($conge['date_approbation']))) ?></span></div>
        <?php else: ?>
        <p class="text-slate-400 text-xs">En attente d'approbation</p>
        <?php endif; ?>
        <?php if ($conge['annule_par_nom']): ?>
        <div class="border-t border-slate-50 pt-2">
          <div class="flex justify-between"><span class="text-slate-500">Annulé par</span><strong><?= e($conge['annule_par_nom']) ?></strong></div>
          <div class="flex justify-between"><span class="text-slate-500">Date</span><span><?= e(date('d/m/Y H:i', strtotime($conge['date_annulation'] ?? ''))) ?></span></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Historique -->
  <?php if (!empty($historique)): ?>
  <div class="bg-white border border-slate-100 rounded-xl p-6 shadow-sm">
    <h2 class="text-sm font-semibold text-slate-700 mb-5">Historique des transitions</h2>
    <div class="relative pl-6">
      <div class="absolute left-2 top-0 bottom-0 w-0.5 bg-slate-100"></div>
      <div class="space-y-4">
        <?php foreach ($historique as $h): ?>
        <div class="relative">
          <div class="absolute -left-4 w-2.5 h-2.5 rounded-full bg-violet-400 border-2 border-white top-1"></div>
          <div class="text-xs text-slate-400 mb-0.5"><?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?> · <?= e($h['effectue_par_nom']) ?></div>
          <div class="text-sm font-medium text-slate-800">
            <?= e(ucfirst($h['action'])) ?>
            <?php if ($h['statut_avant'] && $h['statut_apres'] && $h['statut_avant'] !== $h['statut_apres']): ?>
              <span class="text-slate-400 font-normal mx-1">→</span>
              <span class="px-1.5 py-0.5 rounded text-xs <?= $model::statutColor($h['statut_apres']) ?>">
                <?= e($model::statutLabel($h['statut_apres'])) ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if ($h['commentaire']): ?>
            <div class="text-xs text-slate-500 mt-0.5 italic"><?= e($h['commentaire']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

<!-- Modal : Rejeter -->
<div id="modal-rejeter" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full">
    <h3 class="text-lg font-bold text-slate-900 mb-4">Rejeter la demande</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/rejeter">
      <?= \Core\Csrf::field() ?>
      <label class="form-label">Motif de rejet *</label>
      <textarea name="motif_rejet" rows="4" required
                class="w-full border border-red-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 outline-none mb-4"
                placeholder="Expliquez la raison du rejet…"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Confirmer le rejet</button>
        <button type="button" onclick="document.getElementById('modal-rejeter').classList.add('hidden')"
                class="px-5 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal : Annuler -->
<div id="modal-annuler" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full">
    <h3 class="text-lg font-bold text-slate-900 mb-4">Annuler la demande</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/annuler">
      <?= \Core\Csrf::field() ?>
      <label class="form-label">Motif d'annulation *</label>
      <textarea name="motif_annulation" rows="3" required
                class="form-textarea mb-4"
                placeholder="Raison de l'annulation…"></textarea>
      <div class="flex gap-3">
        <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">Confirmer l'annulation</button>
        <button type="button" onclick="document.getElementById('modal-annuler').classList.add('hidden')"
                class="px-5 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Fermer</button>
      </div>
    </form>
  </div>
</div>
<!-- Modal : Terminer -->
<div id="modal-terminer" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full">
    <h3 class="text-lg font-bold text-slate-900 mb-4">Clôturer le congé</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$conge['id'] ?>/terminer">
      <?= \Core\Csrf::field() ?>
      <div class="mb-4">
        <label class="form-label">Date de retour effective</label>
        <input type="date" name="date_retour_effectif" value="<?= date('Y-m-d') ?>"
               class="form-input">
      </div>
      <div class="mb-4">
        <label class="form-label">Commentaire (optionnel)</label>
        <textarea name="commentaire_retour" rows="2"
                  class="form-textarea"
                  placeholder="Remarques sur le retour…"></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="px-5 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">Clôturer</button>
        <button type="button" onclick="document.getElementById('modal-terminer').classList.add('hidden')"
                class="px-5 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">Annuler</button>
      </div>
    </form>
  </div>
</div>
