<?php
$title = 'Détail retard';
$statutClass = match($retard['statut']) {
    'justifie'   => 'bg-green-100 text-green-700',
    'en_attente' => 'bg-amber-100 text-amber-700',
    'refuse'     => 'bg-red-100 text-red-700',
    default      => 'bg-slate-100 text-slate-600',
};
$statutLabel = match($retard['statut']) {
    'non_justifie' => 'Non justifié',
    'en_attente'   => 'En attente de validation',
    'justifie'     => 'Justifié',
    'refuse'       => 'Refusé',
    default        => $retard['statut'],
};
?>
<div class="max-w-4xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards" class="hover:text-violet-600">Retards</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Retard #<?= $retard['id'] ?></span>
  </div>


  <!-- Fiche retard -->
  <div class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-slate-800">
          <?= htmlspecialchars($retard['eleve_prenom'] . ' ' . $retard['eleve_nom']) ?>
        </h1>
        <p class="text-slate-500 text-sm mt-0.5">
          <?= htmlspecialchars($retard['classe_nom']) ?> — <?= htmlspecialchars($retard['annee_scolaire']) ?>
        </p>
      </div>
      <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium <?= $statutClass ?>">
        <?= $statutLabel ?>
      </span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-6">
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-1">Date</p>
        <p class="text-slate-700 font-medium"><?= htmlspecialchars($retard['date_retard']) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-1">Heure prévue</p>
        <p class="text-slate-700 font-medium"><?= $retard['heure_prevue'] ? htmlspecialchars($retard['heure_prevue']) : '—' ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-1">Heure arrivée</p>
        <p class="text-slate-700 font-medium"><?= htmlspecialchars($retard['heure_arrivee']) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-1">Durée du retard</p>
        <p class="text-slate-700 font-bold text-lg"><?= (int)$retard['duree_minutes'] ?> min</p>
      </div>
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-1">Saisie par</p>
        <p class="text-slate-700"><?= htmlspecialchars($retard['saisie_par_prenom'] . ' ' . $retard['saisie_par_nom']) ?></p>
      </div>
      <?php if ($retard['appel_id']): ?>
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-wide font-medium mb-1">Session d'appel</p>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/<?= $retard['appel_id'] ?>"
           class="text-violet-600 hover:underline text-sm">Appel #<?= $retard['appel_id'] ?></a>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($retard['observation']): ?>
    <div class="bg-slate-50 rounded-lg p-3 mb-4">
      <p class="text-xs text-slate-400 font-medium mb-1">Observation</p>
      <p class="text-slate-700 text-sm"><?= nl2br(htmlspecialchars($retard['observation'])) ?></p>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-100">
      <?php if ($canJustify && $retard['statut'] === 'non_justifie' && $justif === null): ?>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>/justifier"
         class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 text-sm">
        <i data-lucide="file-text" class="w-4 h-4"></i> Soumettre une justification
      </a>
      <?php endif; ?>
      <?php if ($canModify): ?>
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>/edit"
         class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 text-sm">
        <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
      </a>
      <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>/delete"
            onsubmit="return confirm('Archiver ce retard ?')">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 text-sm">
          <i data-lucide="archive" class="w-4 h-4"></i> Archiver
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Justification -->
  <?php if ($justif !== null): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="text-base font-semibold text-slate-700 mb-4 flex items-center gap-2">
      <i data-lucide="file-check" class="w-4 h-4 text-violet-500"></i>
      Justification soumise
    </h2>

    <?php
    $jStatutClass = match($justif['statut']) {
        'validee' => 'bg-green-100 text-green-700',
        'refusee' => 'bg-red-100 text-red-700',
        default   => 'bg-amber-100 text-amber-700',
    };
    $jStatutLabel = match($justif['statut']) {
        'validee'    => 'Validée',
        'refusee'    => 'Refusée',
        'en_attente' => 'En attente',
        default      => $justif['statut'],
    };
    ?>

    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-slate-500">Soumise le <?= htmlspecialchars($justif['soumis_le']) ?> par <?= htmlspecialchars($justif['soumis_par_prenom'] . ' ' . $justif['soumis_par_nom']) ?></span>
      <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $jStatutClass ?>"><?= $jStatutLabel ?></span>
    </div>

    <?php if ($justif['motif_description']): ?>
    <div class="bg-slate-50 rounded-lg p-3 mb-3 text-sm text-slate-700">
      <?= nl2br(htmlspecialchars($justif['motif_description'])) ?>
    </div>
    <?php endif; ?>

    <?php if ($justif['fichier_justificatif']): ?>
    <a href="<?= htmlspecialchars((new \App\Services\UploadService())->url($justif['fichier_justificatif'])) ?>" target="_blank"
       class="inline-flex items-center gap-2 text-violet-600 hover:underline text-sm mb-3">
      <i data-lucide="paperclip" class="w-4 h-4"></i> Voir le fichier joint
    </a>
    <?php endif; ?>

    <?php if ($justif['statut'] === 'refusee' && $justif['motif_refus']): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700 mt-2">
      <strong>Motif de refus :</strong> <?= nl2br(htmlspecialchars($justif['motif_refus'])) ?>
    </div>
    <?php endif; ?>

    <!-- Actions validation/refus -->
    <?php if ($canValidate && $justif['statut'] === 'en_attente'): ?>
    <div class="flex gap-2 pt-4 border-t border-slate-100 mt-4">
      <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>/valider">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
          <i data-lucide="check" class="w-4 h-4"></i> Valider
        </button>
      </form>

      <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>/refuser" class="flex gap-2">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
        <input type="text" name="motif_refus" required placeholder="Motif de refus..."
               class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 flex-1 min-w-[200px]">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
          <i data-lucide="x" class="w-4 h-4"></i> Refuser
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
