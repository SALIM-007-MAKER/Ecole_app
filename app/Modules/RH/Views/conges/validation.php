<?php
/** @var array $conges */
/** @var string $model */
/** @var bool $canApprove, $canReject */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="<?= BASE_URL ?>/v2/rh/conges" class="hover:text-violet-600">Congés</a>
        <span>/</span><span>File d'approbation</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Demandes en attente d'approbation</h1>
      <p class="text-sm text-slate-500 mt-1"><?= count($conges) ?> demande<?= count($conges) > 1 ? 's' : '' ?> en attente</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/conges" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">← Retour</a>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($conges)): ?>
  <div class="bg-white border border-slate-100 rounded-xl p-16 text-center shadow-sm">
    <svg class="w-16 h-16 mx-auto mb-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <h2 class="text-xl font-semibold text-slate-700 mb-2">File vide</h2>
    <p class="text-slate-400">Aucune demande en attente d'approbation.</p>
  </div>
  <?php else: ?>

  <div class="space-y-4">
    <?php foreach ($conges as $c): ?>
    <div class="bg-white border border-slate-100 rounded-xl p-5 shadow-sm" id="conge-<?= (int)$c['id'] ?>">
      <div class="flex items-start justify-between gap-4">
        <!-- Infos -->
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-2">
            <div class="font-semibold text-slate-900"><?= e($c['employe_nom_complet']) ?></div>
            <span class="text-xs text-slate-400"><?= e($c['employe_matricule'] ?? '') ?></span>
            <?php if ($c['departement_nom']): ?>
              <span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= e($c['departement_nom']) ?></span>
            <?php endif; ?>
          </div>
          <div class="grid grid-cols-4 gap-4 text-sm">
            <div>
              <div class="text-xs text-slate-400 mb-0.5">Type</div>
              <div class="font-medium"><?= e($c['type_libelle']) ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-400 mb-0.5">Début</div>
              <div class="font-medium"><?= e(date('d/m/Y', strtotime($c['date_debut']))) ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-400 mb-0.5">Fin</div>
              <div class="font-medium"><?= e(date('d/m/Y', strtotime($c['date_fin']))) ?></div>
            </div>
            <div>
              <div class="text-xs text-slate-400 mb-0.5">Durée</div>
              <div class="font-bold text-violet-700"><?= e($model::formatDuree((float)$c['duree_jours'])) ?></div>
            </div>
          </div>
          <?php if ($c['motif']): ?>
          <div class="mt-2 text-sm text-slate-500 italic">"<?= e($c['motif']) ?>"</div>
          <?php endif; ?>
          <div class="mt-2 text-xs text-slate-400">Demande soumise le <?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></div>
        </div>

        <!-- Actions -->
        <div class="flex-shrink-0 flex flex-col gap-2 min-w-[160px]">
          <?php if ($canApprove): ?>
          <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$c['id'] ?>/approuver"
                onsubmit="return confirm('Approuver ce congé de <?= e($c['employe_nom_complet']) ?> ?')">
            <?= \Core\Csrf::field() ?>
            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
              Approuver
            </button>
          </form>
          <?php endif; ?>
          <?php if ($canReject): ?>
          <button onclick="toggleRejet(<?= (int)$c['id'] ?>)"
                  class="w-full px-4 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200">
            Rejeter
          </button>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$c['id'] ?>"
             class="w-full px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm text-center hover:bg-slate-200">
            Détail
          </a>
        </div>
      </div>

      <!-- Formulaire de rejet inline -->
      <div id="form-rejet-<?= (int)$c['id'] ?>" class="hidden mt-4 border-t border-slate-100 pt-4">
        <form method="POST" action="<?= BASE_URL ?>/v2/rh/conges/<?= (int)$c['id'] ?>/rejeter">
          <?= \Core\Csrf::field() ?>
          <label class="form-label">Motif de rejet *</label>
          <textarea name="motif_rejet" rows="2" required
                    class="w-full border border-red-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 outline-none mb-3"
                    placeholder="Motif obligatoire…"></textarea>
          <div class="flex gap-2">
            <button type="submit" class="px-4 py-1.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
              Confirmer le rejet
            </button>
            <button type="button" onclick="toggleRejet(<?= (int)$c['id'] ?>)"
                    class="px-4 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200">
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
<script>
function toggleRejet(id) {
    const el = document.getElementById('form-rejet-' + id);
    el.classList.toggle('hidden');
    if (!el.classList.contains('hidden')) el.querySelector('textarea').focus();
}
</script>
