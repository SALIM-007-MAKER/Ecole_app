<?php
/** @var array $presences */
/** @var string $model */
/** @var bool $canValidate */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

  <div class="flex items-center justify-between mb-8">
    <div>
      <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
        <a href="<?= BASE_URL ?>/v2/rh/presences" class="hover:text-violet-600">Présences</a>
        <span>/</span><span>File de validation</span>
      </div>
      <h1 class="text-2xl font-bold text-slate-900">Pointages en attente de validation</h1>
      <p class="text-sm text-slate-500 mt-1"><?= count($presences) ?> pointage<?= count($presences) > 1 ? 's' : '' ?> en attente</p>
    </div>
    <a href="<?= BASE_URL ?>/v2/rh/presences" class="btn btn-secondary">
      ← Retour à la liste
    </a>
  </div>

  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><?= e($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($presences)): ?>
  <div class="bg-white border border-slate-100 rounded-xl p-16 text-center shadow-sm">
    <svg class="w-16 h-16 mx-auto mb-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <h2 class="text-xl font-semibold text-slate-700 mb-2">File vide</h2>
    <p class="text-slate-400">Aucun pointage en attente de validation.</p>
  </div>
  <?php else: ?>

  <!-- Tableau de validation -->
  <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-100">
        <tr>
          <th class="text-left px-5 py-3 font-medium text-slate-600">Employé</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Arrivée / Départ</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Durée</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Retard / Hrs sup</th>
          <th class="text-left px-4 py-3 font-medium text-slate-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($presences as $p): ?>
        <tr class="hover:bg-slate-50 transition-colors" id="row-<?= (int)$p['id'] ?>">
          <td class="px-5 py-4">
            <div class="font-medium text-slate-900"><?= e($p['employe_nom']) ?></div>
            <div class="text-xs text-slate-400"><?= e($p['employe_matricule'] ?? '') ?>
              <?php if (!empty($p['departement_nom'])): ?>
                · <?= e($p['departement_nom']) ?>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-4 py-4 text-slate-700 whitespace-nowrap">
            <?= e(date('d/m/Y', strtotime($p['date_presence']))) ?>
          </td>
          <td class="px-4 py-4 text-slate-700 whitespace-nowrap">
            <span class="font-medium"><?= e($p['heure_arrivee'] ? substr($p['heure_arrivee'], 0, 5) : '—') ?></span>
            <span class="text-slate-300 mx-1">→</span>
            <span><?= e($p['heure_depart'] ? substr($p['heure_depart'], 0, 5) : '—') ?></span>
          </td>
          <td class="px-4 py-4 text-slate-700">
            <?= e($model::formatDuree((int)($p['duree_minutes'] ?? 0))) ?>
          </td>
          <td class="px-4 py-4">
            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $model::statutColor($p['statut']) ?>">
              <?= e($model::statutLabel($p['statut'])) ?>
            </span>
          </td>
          <td class="px-4 py-4">
            <?php $retard = (int)($p['retard_minutes'] ?? 0); ?>
            <?php $hSup   = (int)($p['heures_supp_minutes'] ?? 0); ?>
            <div class="flex flex-col gap-1">
              <?php if ($retard > 0): ?>
                <span class="text-xs <?= $model::alerteRetard($retard) ?>">
                  +<?= e($model::formatDuree($retard)) ?> retard
                </span>
              <?php endif; ?>
              <?php if ($hSup > 0): ?>
                <span class="text-xs text-violet-600 font-medium">
                  +<?= e($model::formatDuree($hSup)) ?> H.sup
                </span>
              <?php endif; ?>
              <?php if ($retard === 0 && $hSup === 0): ?>
                <span class="text-slate-300 text-xs">—</span>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-4 py-4">
            <?php if ($canValidate): ?>
            <div class="flex gap-2 items-center">
              <!-- Valider rapide -->
              <form method="POST" action="<?= BASE_URL ?>/v2/rh/presences/<?= (int)$p['id'] ?>/valider"
                    onsubmit="return confirm('Valider ce pointage ?')"
                    class="inline">
                <?= \Core\Csrf::field() ?>
                <input type="hidden" name="decision" value="valide">
                <button type="submit" class="btn btn-outline-success btn-sm">
                  Valider
                </button>
              </form>
              <!-- Rejeter (ouvre mini-form) -->
              <button onclick="toggleRejet(<?= (int)$p['id'] ?>)" class="btn btn-outline-danger btn-sm">
                Rejeter
              </button>
              <!-- Détail -->
              <a href="<?= BASE_URL ?>/v2/rh/presences/<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">
                Détail
              </a>
            </div>
            <!-- Formulaire rejet inline (masqué) -->
            <div id="form-rejet-<?= (int)$p['id'] ?>" class="hidden mt-3">
              <form method="POST" action="<?= BASE_URL ?>/v2/rh/presences/<?= (int)$p['id'] ?>/valider">
                <?= \Core\Csrf::field() ?>
                <input type="hidden" name="decision" value="rejete">
                <textarea name="motif_rejet" rows="2" required
                          placeholder="Motif de rejet obligatoire…"
                          class="form-textarea mb-2"></textarea>
                <div class="flex gap-2">
                  <button type="submit" class="btn btn-danger btn-sm">
                    Confirmer le rejet
                  </button>
                  <button type="button" onclick="toggleRejet(<?= (int)$p['id'] ?>)" class="btn btn-secondary btn-sm">
                    Annuler
                  </button>
                </div>
              </form>
            </div>
            <?php else: ?>
              <span class="text-xs text-slate-400">Permission requise</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Actions groupées (optionnel) -->
  <div class="mt-4 text-xs text-slate-400 text-center">
    Utilisez les boutons individuels pour valider ou rejeter chaque pointage.
    Pour une validation en lot, ouvrez le détail et utilisez la régularisation.
  </div>

  <?php endif; ?>
<script>
function toggleRejet(id) {
    const el = document.getElementById('form-rejet-' + id);
    el.classList.toggle('hidden');
    if (!el.classList.contains('hidden')) {
        el.querySelector('textarea').focus();
    }
}
</script>
