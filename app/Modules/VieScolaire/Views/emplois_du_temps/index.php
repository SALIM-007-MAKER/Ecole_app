<?php $title = 'Emplois du temps'; ?>

<div class="p-6 space-y-6">

  <!-- En-tête -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Emplois du temps</h1>
      <p class="text-slate-500 text-sm mt-1">Planification hebdomadaire par classe</p>
    </div>
    <?php if (in_array('timetable.create', $user['permissions'] ?? [])): ?>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/create"
       class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
      <i data-lucide="plus" class="w-4 h-4"></i> Nouvel EDT
    </a>
    <?php endif; ?>
  </div>

  <!-- Flash -->

  <!-- Filtres -->
  <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs text-slate-500 mb-1">Classe</label>
      <select name="classe_id" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Toutes</option>
        <?php foreach ($classes as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= ($filters->classeId == $cl['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cl['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Année scolaire</label>
      <select name="annee_scolaire" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Toutes</option>
        <?php foreach ($annees as $a): ?>
          <option value="<?= $a['annee_scolaire'] ?>" <?= ($filters->anneeScolaire === $a['annee_scolaire']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['annee_scolaire']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Statut</label>
      <select name="statut" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Tous</option>
        <option value="brouillon"  <?= ($filters->statut === 'brouillon') ? 'selected' : '' ?>>Brouillon</option>
        <option value="publie"     <?= ($filters->statut === 'publie') ? 'selected' : '' ?>>Publié</option>
        <option value="archive"    <?= ($filters->statut === 'archive') ? 'selected' : '' ?>>Archivé</option>
      </select>
    </div>
    <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm transition">
      Filtrer
    </button>
  </form>

  <!-- Tableau -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Classe</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Année</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-700">Créneaux</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-700">Statut</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-700">Version</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-700">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($data)): ?>
          <tr><td colspan="7" class="text-center py-8 text-slate-400">Aucun emploi du temps.</td></tr>
        <?php endif; ?>
        <?php foreach ($data as $edt): ?>
          <?php
            $statutCls = match($edt['statut']) {
              'publie'   => 'bg-green-100 text-green-700',
              'archive'  => 'bg-slate-100 text-slate-500',
              default    => 'bg-yellow-100 text-yellow-700',
            };
            $statutLib = match($edt['statut']) {
              'publie'  => 'Publié',
              'archive' => 'Archivé',
              default   => 'Brouillon',
            };
          ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($edt['classe_nom']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($edt['annee_scolaire']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($edt['semaine_type']) ?></td>
            <td class="px-4 py-3 text-center">
              <span class="font-semibold text-violet-700"><?= $edt['nb_creneaux'] ?></span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statutCls ?>">
                <?= $statutLib ?>
              </span>
            </td>
            <td class="px-4 py-3 text-center text-slate-500">v<?= $edt['version'] ?></td>
            <td class="px-4 py-3 text-center">
              <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/<?= $edt['id'] ?>"
                 class="inline-flex items-center gap-1 text-violet-600 hover:text-violet-800 text-sm font-medium">
                <i data-lucide="calendar" class="w-4 h-4"></i> Voir
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="flex justify-center gap-2">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="?page=<?= $p ?>&classe_id=<?= $filters->classeId ?>&annee_scolaire=<?= $filters->anneeScolaire ?>&statut=<?= $filters->statut ?>"
         class="px-3 py-1 rounded-lg text-sm border <?= ($p == $page) ? 'bg-violet-600 text-white border-violet-600' : 'border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>

