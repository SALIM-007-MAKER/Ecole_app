<?php $title = htmlspecialchars($activity['titre']); ?>

<?php
$statutCls = match($activity['statut']) {
    'publie'   => 'bg-green-100 text-green-700',
    'en_cours' => 'bg-blue-100 text-blue-700',
    'termine'  => 'bg-slate-100 text-slate-500',
    'annule'   => 'bg-red-100 text-red-700',
    default    => 'bg-yellow-100 text-yellow-700',
};
$statutLib = match($activity['statut']) {
    'publie'  => 'Publié', 'en_cours' => 'En cours',
    'termine' => 'Terminé', 'annule' => 'Annulé', default => 'Brouillon',
};
$pct = $activity['capacite_max'] > 0
    ? min(100, round($activity['nb_inscrits'] / $activity['capacite_max'] * 100))
    : 0;
$inscrits       = array_filter($inscriptions, fn($i) => $i['statut'] === 'inscrit');
$listeAttente   = array_filter($inscriptions, fn($i) => $i['statut'] === 'liste_attente');
$marquageOuvert = in_array($activity['statut'], ['publie', 'en_cours'], true);
?>

<div class="p-6 space-y-6">

  <!-- En-tête -->
  <div class="flex items-start justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites"
         class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
      </a>
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                style="background-color:<?= htmlspecialchars($activity['categorie_couleur']) ?>22;color:<?= htmlspecialchars($activity['categorie_couleur']) ?>">
            <?= htmlspecialchars($activity['categorie_nom']) ?>
          </span>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statutCls ?>">
            <?= $statutLib ?>
          </span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($activity['titre']) ?></h1>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap">
      <?php if ($policy->canModifyActivity($user, $activity)): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>/edit"
           class="inline-flex items-center gap-1 border border-slate-300 text-slate-600 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-sm transition">
          <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
        </a>
      <?php endif; ?>
      <?php if ($policy->canPublishActivity($user, $activity)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>/publier"
              onsubmit="return confirm('Publier cette activité ?')">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
          <button type="submit" class="inline-flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
            <i data-lucide="send" class="w-4 h-4"></i> Publier
          </button>
        </form>
      <?php endif; ?>
      <?php if ($policy->canRegisterStudent($user) && in_array($activity['statut'], ['publie','en_cours'])): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>/inscrire"
           class="inline-flex items-center gap-1 bg-violet-600 hover:bg-violet-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
          <i data-lucide="user-plus" class="w-4 h-4"></i> Inscrire
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Flash -->

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Colonne principale -->
    <div class="lg:col-span-2 space-y-5">

      <!-- Infos -->
      <div class="bg-white border border-slate-200 rounded-xl p-5 grid grid-cols-2 gap-4 text-sm">
        <div>
          <p class="text-xs text-slate-400 mb-0.5">Date</p>
          <p class="font-medium text-slate-800"><?= date('d/m/Y', strtotime($activity['date_activite'])) ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-400 mb-0.5">Horaires</p>
          <p class="font-medium text-slate-800"><?= htmlspecialchars($activity['heure_debut']) ?> – <?= htmlspecialchars($activity['heure_fin']) ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-400 mb-0.5">Lieu</p>
          <p class="font-medium text-slate-800"><?= htmlspecialchars($activity['lieu'] ?? '—') ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-400 mb-0.5">Organisateur</p>
          <p class="font-medium text-slate-800">
            <?= $activity['organisateur_nom']
              ? htmlspecialchars($activity['organisateur_prenom'] . ' ' . $activity['organisateur_nom'])
              : '—' ?>
          </p>
        </div>
        <?php if (!empty($activity['classes'])): ?>
        <div class="col-span-2">
          <p class="text-xs text-slate-400 mb-1">Classes concernées</p>
          <div class="flex flex-wrap gap-1">
            <?php foreach ($activity['classes'] as $cl): ?>
              <span class="bg-violet-100 text-violet-700 text-xs px-2 py-0.5 rounded-full">
                <?= htmlspecialchars($cl['nom']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($activity['responsables'])): ?>
        <div class="col-span-2">
          <p class="text-xs text-slate-400 mb-1">Responsables</p>
          <div class="flex flex-wrap gap-1">
            <?php foreach ($activity['responsables'] as $r): ?>
              <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full">
                <?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($activity['description']): ?>
        <div class="col-span-2">
          <p class="text-xs text-slate-400 mb-1">Description</p>
          <p class="text-slate-600 text-sm leading-relaxed"><?= nl2br(htmlspecialchars($activity['description'])) ?></p>
        </div>
        <?php endif; ?>
      </div>

      <!-- Jauge -->
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <div class="flex justify-between text-sm mb-2">
          <span class="font-medium text-slate-700">
            <?= count($inscrits) ?> inscrits · <?= count($listeAttente) ?> en attente
          </span>
          <span class="text-slate-500">Capacité : <?= $activity['capacite_max'] ?></span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-3">
          <div class="h-3 rounded-full transition-all <?= $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-orange-400' : 'bg-violet-500') ?>"
               style="width:<?= $pct ?>%"></div>
        </div>
        <p class="text-xs text-slate-400 mt-1"><?= $pct ?>% rempli</p>
      </div>

      <!-- Présences (si activité terminée ou en cours) -->
      <?php if ($marquageOuvert && $policy->canUpdate($user) && !empty($inscrits)): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-5">
        <h2 class="font-semibold text-slate-800 mb-3">Marquer les présences</h2>
        <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>/presences">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
          <div class="space-y-2">
            <?php foreach ($inscrits as $ins): ?>
              <div class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0">
                <span class="text-sm text-slate-700">
                  <?= htmlspecialchars($ins['eleve_prenom'] . ' ' . $ins['eleve_nom']) ?>
                </span>
                <div class="flex gap-3">
                  <label class="flex items-center gap-1.5 text-sm text-green-700 cursor-pointer">
                    <input type="radio" name="presences[<?= $ins['id'] ?>]" value="present"
                           <?= ($ins['statut'] === 'present') ? 'checked' : '' ?>> Présent
                  </label>
                  <label class="flex items-center gap-1.5 text-sm text-red-700 cursor-pointer">
                    <input type="radio" name="presences[<?= $ins['id'] ?>]" value="absent"
                           <?= ($ins['statut'] === 'absent') ? 'checked' : '' ?>> Absent
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="mt-4 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            Enregistrer les présences
          </button>
        </form>
      </div>
      <?php endif; ?>

      <!-- Liste inscrits -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Participants inscrits</h2>
          <span class="text-xs text-slate-400"><?= count($inscrits) ?> / <?= $activity['capacite_max'] ?></span>
        </div>
        <?php if (empty($inscrits)): ?>
          <div class="text-center py-6 text-slate-400 text-sm">Aucun inscrit.</div>
        <?php else: ?>
          <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($inscrits as $ins): ?>
                <tr class="hover:bg-slate-50">
                  <td class="px-4 py-2.5 font-medium text-slate-800">
                    <?= htmlspecialchars($ins['eleve_prenom'] . ' ' . $ins['eleve_nom']) ?>
                    <span class="text-xs text-slate-400 ml-1"><?= htmlspecialchars($ins['eleve_matricule'] ?? '') ?></span>
                  </td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs"><?= htmlspecialchars($ins['classe_nom'] ?? '—') ?></td>
                  <td class="px-4 py-2.5 text-right">
                    <?php if ($ins['statut'] === 'present'): ?>
                      <span class="text-green-600 text-xs font-medium">Présent</span>
                    <?php elseif ($ins['statut'] === 'absent'): ?>
                      <span class="text-red-600 text-xs font-medium">Absent</span>
                    <?php endif; ?>
                  </td>
                  <?php if ($policy->canRegisterStudent($user) && $ins['statut'] === 'inscrit'): ?>
                  <td class="px-4 py-2.5 text-right">
                    <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/activites/inscriptions/<?= $ins['id'] ?>/annuler"
                          onsubmit="return confirm('Annuler cette inscription ?')">
                      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                      <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Annuler</button>
                    </form>
                  </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Liste d'attente -->
      <?php if (!empty($listeAttente)): ?>
      <div class="bg-white border border-amber-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-amber-200 bg-amber-50">
          <h2 class="font-semibold text-amber-800">Liste d'attente (<?= count($listeAttente) ?>)</h2>
        </div>
        <table class="w-full text-sm">
          <tbody class="divide-y divide-slate-100">
            <?php $pos = 1; foreach ($listeAttente as $ins): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-2.5 text-amber-600 font-semibold text-xs w-8">#<?= $pos++ ?></td>
                <td class="px-4 py-2.5 font-medium text-slate-800">
                  <?= htmlspecialchars($ins['eleve_prenom'] . ' ' . $ins['eleve_nom']) ?>
                </td>
                <td class="px-4 py-2.5 text-slate-400 text-xs">
                  <?= date('d/m/Y H:i', strtotime($ins['date_inscription'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div>

    <!-- Colonne latérale -->
    <div class="space-y-4">

      <!-- Annulation -->
      <?php if ($policy->canCancelActivity($user, $activity)): ?>
      <div class="bg-white border border-red-200 rounded-xl p-4">
        <h3 class="font-semibold text-red-700 mb-3 text-sm">Annuler l'activité</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/activites/<?= $activity['id'] ?>/annuler"
              onsubmit="return confirm('Annuler définitivement cette activité ?')">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
          <textarea name="motif_annulation" rows="3" required minlength="10"
                    placeholder="Motif (minimum 10 caractères)…"
                    class="w-full border border-red-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-300 mb-2"></textarea>
          <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            Annuler l'activité
          </button>
        </form>
      </div>
      <?php endif; ?>

      <!-- Historique -->
      <?php if (!empty($historique)): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-4">
        <h3 class="font-semibold text-slate-800 mb-3 text-sm flex items-center gap-2">
          <i data-lucide="history" class="w-4 h-4 text-violet-500"></i> Historique
        </h3>
        <div class="space-y-2.5 max-h-64 overflow-y-auto">
          <?php foreach ($historique as $h): ?>
            <div class="border-l-2 border-violet-200 pl-3">
              <p class="text-xs font-medium text-slate-700"><?= htmlspecialchars($h['description'] ?? $h['action']) ?></p>
              <p class="text-xs text-slate-400">
                <?= htmlspecialchars($h['user_prenom'] . ' ' . $h['user_nom']) ?>
                · <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

