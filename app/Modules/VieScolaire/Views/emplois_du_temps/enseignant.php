<?php $title = 'EDT Enseignant — ' . ($enseignant['nom'] ?? ''); ?>

<?php $joursLabels = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']; ?>

<div class="p-6 space-y-6">

  <div class="flex items-start justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps"
         class="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm transition-colors flex-shrink-0">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">
          Planning enseignant
          <?php if ($enseignant): ?>
            — <?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?>
          <?php endif; ?>
        </h1>
        <p class="text-slate-500 text-sm mt-1">Année <?= htmlspecialchars($annee) ?></p>
      </div>
    </div>
    <div class="flex gap-2 items-end flex-wrap">
      <form method="GET" class="flex gap-2 items-end">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Enseignant</label>
          <select name="enseignant_id" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <?php foreach ($enseignants as $e): ?>
              <option value="<?= $e['id'] ?>" <?= ($e['id'] == $enseignant['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Année</label>
          <input type="text" name="annee" value="<?= htmlspecialchars($annee) ?>"
                 class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-28">
        </div>
        <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm transition">
          Voir
        </button>
      </form>
    </div>
  </div>

  <!-- KPI heures -->
  <?php if (!empty($heures)): ?>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php $totalCreneaux = array_sum(array_column($heures, 'nb_creneaux')); ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
      <div class="text-2xl font-bold text-violet-700"><?= $totalCreneaux ?></div>
      <div class="text-xs text-slate-500 mt-1">Créneaux / sem</div>
    </div>
    <?php foreach (array_slice($heures, 0, 3) as $h): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
      <div class="text-xl font-bold text-slate-700"><?= $h['nb_creneaux'] ?></div>
      <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($h['matiere_nom']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Grille -->
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
                  <div class="bg-violet-100 border-l-4 border-violet-500 text-violet-800 rounded-lg px-2 py-1.5 space-y-0.5">
                    <div class="font-semibold text-xs leading-tight"><?= htmlspecialchars($cr['matiere_nom']) ?></div>
                    <div class="text-xs opacity-75"><?= htmlspecialchars($cr['classe_nom']) ?></div>
                    <?php if ($cr['salle_nom']): ?>
                      <div class="text-xs opacity-60"><?= htmlspecialchars($cr['salle_nom']) ?></div>
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

</div>

