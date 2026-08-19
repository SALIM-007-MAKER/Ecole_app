<?php $title = 'Statistiques — Activités'; ?>

<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Statistiques — Activités</h1>
      <p class="text-slate-500 text-sm mt-1">Répartition par catégorie · Année <?= htmlspecialchars($annee) ?></p>
    </div>
    <form method="GET" class="flex gap-2 items-end">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Année scolaire</label>
        <input type="text" name="annee" value="<?= htmlspecialchars($annee) ?>" placeholder="2025-2026"
               class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-32">
      </div>
      <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm transition">
        Voir
      </button>
    </form>
  </div>

  <?php if (empty($stats)): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-400">
      Aucune donnée pour cette année.
    </div>
  <?php else: ?>

    <!-- KPI globaux -->
    <?php
      $totalActivites = array_sum(array_column($stats, 'nb_activites'));
      $totalTerminees = array_sum(array_column($stats, 'nb_terminees'));
      $totalAnnulees  = array_sum(array_column($stats, 'nb_annulees'));
      $totalInscrits  = array_sum(array_column($stats, 'total_inscrits'));
    ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <div class="text-3xl font-bold text-violet-700"><?= $totalActivites ?></div>
        <div class="text-xs text-slate-500 mt-1">Activités total</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <div class="text-3xl font-bold text-green-600"><?= $totalTerminees ?></div>
        <div class="text-xs text-slate-500 mt-1">Terminées</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <div class="text-3xl font-bold text-red-600"><?= $totalAnnulees ?></div>
        <div class="text-xs text-slate-500 mt-1">Annulées</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <div class="text-3xl font-bold text-blue-600"><?= $totalInscrits ?></div>
        <div class="text-xs text-slate-500 mt-1">Participations</div>
      </div>
    </div>

    <!-- Tableau par catégorie -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-semibold text-slate-800">Répartition par catégorie</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-slate-700">Catégorie</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-700">Activités</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-700">Terminées</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-700">Annulées</th>
            <th class="px-4 py-3 text-center font-semibold text-slate-700">Participants</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-700">Part</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($stats as $s): ?>
            <?php $pct = $totalActivites > 0 ? round($s['nb_activites'] / $totalActivites * 100) : 0; ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full"
                        style="background-color:<?= htmlspecialchars($s['categorie_couleur']) ?>"></span>
                  <span class="font-medium text-slate-800"><?= htmlspecialchars($s['categorie_nom']) ?></span>
                </div>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-violet-700"><?= $s['nb_activites'] ?></td>
              <td class="px-4 py-3 text-center text-green-600"><?= $s['nb_terminees'] ?></td>
              <td class="px-4 py-3 text-center text-red-600"><?= $s['nb_annulees'] ?></td>
              <td class="px-4 py-3 text-center text-blue-600"><?= $s['total_inscrits'] ?></td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <div class="w-16 bg-slate-100 rounded-full h-2">
                    <div class="h-2 rounded-full" style="width:<?= $pct ?>%;background-color:<?= htmlspecialchars($s['categorie_couleur']) ?>"></div>
                  </div>
                  <span class="text-xs text-slate-500 w-8 text-right"><?= $pct ?>%</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-slate-50 border-t border-slate-200">
          <tr>
            <td class="px-4 py-3 font-semibold text-slate-700">Total</td>
            <td class="px-4 py-3 text-center font-bold text-violet-700"><?= $totalActivites ?></td>
            <td class="px-4 py-3 text-center font-bold text-green-600"><?= $totalTerminees ?></td>
            <td class="px-4 py-3 text-center font-bold text-red-600"><?= $totalAnnulees ?></td>
            <td class="px-4 py-3 text-center font-bold text-blue-600"><?= $totalInscrits ?></td>
            <td class="px-4 py-3 text-right text-xs text-slate-400">100%</td>
          </tr>
        </tfoot>
      </table>
    </div>

  <?php endif; ?>

</div>

