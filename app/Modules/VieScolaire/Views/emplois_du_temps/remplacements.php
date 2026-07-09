<?php $title = 'Remplacements — ' . $date; ?>
<?php ob_start(); ?>

<div class="p-6 space-y-6">

  <div class="flex items-start justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="/v2/vie-scolaire/emplois-du-temps" class="text-slate-400 hover:text-slate-600">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Remplacements</h1>
        <p class="text-slate-500 text-sm mt-1">Gestion des absences et remplaçants</p>
      </div>
    </div>
    <form method="GET" class="flex gap-2 items-end">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"
               class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm transition">
        Voir
      </button>
    </form>
  </div>

  <!-- Flash -->
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
      <?= htmlspecialchars($_SESSION['flash_success']) ?>
      <?php unset($_SESSION['flash_success']); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
      <?= $_SESSION['flash_error'] ?>
      <?php unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <!-- Liste du jour -->
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200">
      <h2 class="font-semibold text-slate-800">
        Remplacements du <?= date('d/m/Y', strtotime($date)) ?>
        <span class="ml-2 text-xs font-normal text-slate-500"><?= count($remplacements) ?> enregistré(s)</span>
      </h2>
    </div>
    <?php if (empty($remplacements)): ?>
      <div class="text-center py-8 text-slate-400 text-sm">Aucun remplacement pour cette date.</div>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Plage</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Classe</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Matière</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Absent</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Remplaçant</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-700">Statut</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($remplacements as $r): ?>
          <?php
            $statutCls = match($r['statut'] ?? 'planifie') {
              'effectue'  => 'bg-green-100 text-green-700',
              'annule'    => 'bg-red-100 text-red-700',
              default     => 'bg-yellow-100 text-yellow-700',
            };
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($r['plage_libelle']) ?><br><span class="text-xs text-slate-400"><?= $r['plage_debut'] ?></span></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($r['classe_nom']) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['matiere_nom']) ?></td>
            <td class="px-4 py-3 text-red-700"><?= htmlspecialchars($r['absent_prenom'] . ' ' . $r['absent_nom']) ?></td>
            <td class="px-4 py-3">
              <?php if ($r['remplacant_nom']): ?>
                <span class="text-green-700 font-medium"><?= htmlspecialchars($r['remplacant_prenom'] . ' ' . $r['remplacant_nom']) ?></span>
              <?php else: ?>
                <span class="text-slate-400 italic text-xs">Non assigné</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statutCls ?>">
                <?= ucfirst($r['statut'] ?? 'planifie') ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Formulaire nouveau remplacement -->
  <?php if (in_array('timetable.update', $user['permissions'] ?? [])): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5">
    <h2 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
      <i data-lucide="user-x" class="w-4 h-4 text-orange-500"></i>
      Enregistrer un remplacement
    </h2>
    <form method="POST" action="/v2/vie-scolaire/emplois-du-temps/remplacements" class="grid grid-cols-2 gap-4">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Créneau <span class="text-red-500">*</span></label>
        <select name="creneau_id" required
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <option value="">— Sélectionner —</option>
          <?php foreach ($creneaux as $cr): ?>
            <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['classe'] . ' · ' . $cr['matiere'] . ' · ' . $cr['plage']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
        <input type="date" name="date_remplacement" value="<?= htmlspecialchars($date) ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Enseignant absent <span class="text-red-500">*</span></label>
        <select name="enseignant_absent_id" required
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <option value="">— Enseignant —</option>
          <?php foreach ($enseignants as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Remplaçant</label>
        <select name="remplacant_id"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <option value="">— Non assigné —</option>
          <?php foreach ($enseignants as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Motif d'absence</label>
        <input type="text" name="motif_absence" maxlength="255"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"
               placeholder="Maladie, formation...">
      </div>

      <div class="col-span-2">
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
          Enregistrer
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>

</div>

<?php $content = ob_get_clean(); ?>
<?php include base_path('app/Views/layouts/app.php'); ?>
