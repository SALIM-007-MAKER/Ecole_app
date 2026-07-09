<?php $title = 'Statistiques EDT'; ?>
<?php ob_start(); ?>

<div class="p-6 space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Statistiques — Emplois du temps</h1>
      <p class="text-slate-500 text-sm mt-1">Répartition horaire par classe et matière</p>
    </div>
    <?php if ($classeId && in_array('timetable.export', $user['permissions'] ?? [])): ?>
      <a href="/v2/vie-scolaire/emplois-du-temps/export?classe_id=<?= $classeId ?>&annee=<?= urlencode($annee) ?>"
         class="inline-flex items-center gap-2 border border-slate-300 text-slate-600 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium transition">
        <i data-lucide="download" class="w-4 h-4"></i> Export CSV
      </a>
    <?php endif; ?>
  </div>

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs text-slate-500 mb-1">Classe</label>
      <select name="classe_id" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">— Sélectionner —</option>
        <?php foreach ($classes as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= ($classeId == $cl['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cl['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Année scolaire</label>
      <input type="text" name="annee" value="<?= htmlspecialchars($annee) ?>" placeholder="2025-2026"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-32">
    </div>
    <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm transition">
      Analyser
    </button>
  </form>

  <?php if (!empty($stats)): ?>
  <!-- Tableau répartition -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200">
      <h2 class="font-semibold text-slate-800">Répartition par matière</h2>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Matière</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Enseignant</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-700">Créneaux / sem</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-700">Part</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php $totalC = array_sum(array_column($stats, 'nb_creneaux')); ?>
        <?php foreach ($stats as $s): ?>
          <?php $pct = $totalC > 0 ? round($s['nb_creneaux'] / $totalC * 100) : 0; ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($s['matiere_nom']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($s['enseignant_prenom'] . ' ' . $s['enseignant_nom']) ?></td>
            <td class="px-4 py-3 text-center">
              <span class="font-semibold text-violet-700"><?= $s['nb_creneaux'] ?></span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <div class="w-20 bg-slate-100 rounded-full h-2">
                  <div class="bg-violet-500 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="text-xs text-slate-500 w-8 text-right"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="bg-slate-50 border-t border-slate-200">
        <tr>
          <td colspan="2" class="px-4 py-3 font-semibold text-slate-700">Total</td>
          <td class="px-4 py-3 text-center font-bold text-violet-700"><?= $totalC ?></td>
          <td class="px-4 py-3 text-right text-xs text-slate-400">100%</td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php elseif ($classeId): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-400 text-sm">
      Aucun créneau trouvé pour cette classe et cette année.
    </div>
  <?php endif; ?>

</div>

<?php $content = ob_get_clean(); ?>
<?php include base_path('app/Views/layouts/app.php'); ?>
