<?php $title = 'EDT — ' . ($edt['classe_nom'] ?? ''); ?>

<?php
$joursLabels = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$typeCours   = [
    'cours'        => 'bg-violet-100 text-violet-800 border-l-4 border-violet-500',
    'td'           => 'bg-blue-100 text-blue-800 border-l-4 border-blue-500',
    'tp'           => 'bg-green-100 text-green-800 border-l-4 border-green-500',
    'examen'       => 'bg-red-100 text-red-800 border-l-4 border-red-500',
    'sortie'       => 'bg-orange-100 text-orange-800 border-l-4 border-orange-500',
    'permanence'   => 'bg-slate-100 text-slate-600 border-l-4 border-slate-400',
];
$statutEdt = match($edt['statut']) {
    'publie'  => ['label' => 'Publié',   'cls' => 'bg-green-100 text-green-700'],
    'archive' => ['label' => 'Archivé',  'cls' => 'bg-slate-100 text-slate-500'],
    default   => ['label' => 'Brouillon','cls' => 'bg-yellow-100 text-yellow-700'],
};
?>

<div class="p-6 space-y-6">

  <!-- En-tête -->
  <div class="flex items-start justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps"
         class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">
          EDT — <?= htmlspecialchars($edt['classe_nom']) ?>
        </h1>
        <p class="text-slate-500 text-sm mt-1">
          <?= htmlspecialchars($edt['annee_scolaire']) ?> · <?= htmlspecialchars($edt['semaine_type']) ?>
          · <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statutEdt['cls'] ?>">
              <?= $statutEdt['label'] ?>
            </span>
          · v<?= $edt['version'] ?>
        </p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap">
      <?php if ($policy->canModifyEdt($user, $edt)): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/<?= $edt['id'] ?>/creneaux/ajouter"
           class="inline-flex items-center gap-1 bg-violet-600 hover:bg-violet-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
          <i data-lucide="plus" class="w-4 h-4"></i> Ajouter créneau
        </a>
      <?php endif; ?>
      <?php if ($policy->canPublishEdt($user, $edt)): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/<?= $edt['id'] ?>/publier"
              onsubmit="return confirm('Publier cet emploi du temps ?')">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
          <button type="submit" class="inline-flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition">
            <i data-lucide="send" class="w-4 h-4"></i> Publier
          </button>
        </form>
      <?php endif; ?>
      <?php if (in_array('timetable.export', $user['permissions'] ?? [])): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/export?classe_id=<?= $edt['classe_id'] ?>&annee=<?= $edt['annee_scolaire'] ?>"
           class="inline-flex items-center gap-1 border border-slate-300 text-slate-600 hover:bg-slate-50 px-3 py-1.5 rounded-lg text-sm font-medium transition">
          <i data-lucide="download" class="w-4 h-4"></i> Export CSV
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Flash -->

  <!-- Grille visuelle -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
    <table class="w-full text-sm min-w-[700px]">
      <thead>
        <tr class="border-b border-slate-200">
          <th class="px-3 py-3 text-left text-xs text-slate-500 font-medium w-28">Plage</th>
          <?php for ($j = 1; $j <= 6; $j++): ?>
            <th class="px-3 py-3 text-center text-xs font-semibold text-slate-700 bg-slate-50">
              <?= $joursLabels[$j] ?>
            </th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($plages as $plage): ?>
          <tr>
            <td class="px-3 py-3 text-xs text-slate-500 whitespace-nowrap">
              <div class="font-medium text-slate-700"><?= htmlspecialchars($plage['libelle']) ?></div>
              <div><?= htmlspecialchars($plage['heure_debut']) ?> – <?= htmlspecialchars($plage['heure_fin']) ?></div>
            </td>
            <?php for ($j = 1; $j <= 6; $j++): ?>
              <td class="px-2 py-2 align-top">
                <?php $cr = $grille[$j][$plage['id']] ?? null; ?>
                <?php if ($cr): ?>
                  <?php $cls = $typeCours[$cr['type_cours']] ?? 'bg-violet-100 text-violet-800 border-l-4 border-violet-500'; ?>
                  <div class="<?= $cls ?> rounded-lg px-2 py-1.5 space-y-0.5 group relative">
                    <div class="font-semibold text-xs leading-tight"><?= htmlspecialchars($cr['matiere_nom']) ?></div>
                    <div class="text-xs opacity-75"><?= htmlspecialchars($cr['enseignant_nom'] . ' ' . $cr['enseignant_prenom']) ?></div>
                    <?php if ($cr['salle_nom']): ?>
                      <div class="text-xs opacity-60"><?= htmlspecialchars($cr['salle_nom']) ?></div>
                    <?php endif; ?>
                    <?php if ($policy->canModifyEdt($user, $edt)): ?>
                      <div class="hidden group-hover:flex gap-1 mt-1">
                        <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/creneaux/<?= $cr['id'] ?>/supprimer"
                              onsubmit="return confirm('Supprimer ce créneau ?')" class="inline">
                          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                          <button type="submit" class="text-red-500 hover:text-red-700 p-0.5 rounded" title="Supprimer">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                          </button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="h-12 rounded-lg bg-slate-50 border border-dashed border-slate-200"></div>
                <?php endif; ?>
              </td>
            <?php endfor; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Légende types de cours -->
  <div class="flex flex-wrap gap-3 text-xs">
    <?php foreach (['cours' => 'Cours', 'td' => 'TD', 'tp' => 'TP', 'examen' => 'Examen', 'sortie' => 'Sortie', 'permanence' => 'Permanence'] as $type => $lib): ?>
      <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded <?= $typeCours[$type] ?>">
        <span class="w-2 h-2 rounded-full bg-current opacity-60"></span> <?= $lib ?>
      </span>
    <?php endforeach; ?>
  </div>

  <!-- Historique des versions -->
  <?php if (!empty($versions)): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="font-semibold text-slate-800 mb-3 flex items-center gap-2">
      <i data-lucide="history" class="w-4 h-4 text-violet-500"></i>
      Historique des versions
    </h2>
    <div class="space-y-2">
      <?php foreach ($versions as $v): ?>
        <div class="flex items-center justify-between text-sm py-2 border-b border-slate-100 last:border-0">
          <div class="flex items-center gap-3">
            <span class="font-semibold text-violet-700">v<?= $v['version'] ?></span>
            <span class="text-slate-500"><?= htmlspecialchars($v['motif'] ?? '—') ?></span>
          </div>
          <div class="text-xs text-slate-400">
            <?= htmlspecialchars($v['modifie_par_prenom'] . ' ' . $v['modifie_par_nom']) ?>
            · <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

