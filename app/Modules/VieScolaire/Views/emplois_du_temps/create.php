<?php $title = 'Nouvel emploi du temps'; ?>
<?php ob_start(); ?>

<div class="p-6 max-w-2xl mx-auto space-y-6">

  <div class="flex items-center gap-3">
    <a href="/v2/vie-scolaire/emplois-du-temps" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Nouvel emploi du temps</h1>
      <p class="text-slate-500 text-sm mt-1">Un EDT par classe / année / type de semaine</p>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
      <?= $_SESSION['flash_error'] ?>
      <?php unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/v2/vie-scolaire/emplois-du-temps" class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Classe <span class="text-red-500">*</span></label>
      <select name="classe_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="">— Sélectionner —</option>
        <?php foreach ($classes as $cl): ?>
          <option value="<?= $cl['id'] ?>" <?= (($_POST['classe_id'] ?? '') == $cl['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cl['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Année scolaire <span class="text-red-500">*</span></label>
      <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($_POST['annee_scolaire'] ?? (date('Y') . '-' . (date('Y')+1))) ?>"
             placeholder="2025-2026" required
             class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Type de semaine</label>
      <select name="semaine_type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="standard" <?= (($_POST['semaine_type'] ?? '') === 'standard') ? 'selected' : '' ?>>Standard</option>
        <option value="paire"    <?= (($_POST['semaine_type'] ?? '') === 'paire') ? 'selected' : '' ?>>Semaine paire</option>
        <option value="impaire"  <?= (($_POST['semaine_type'] ?? '') === 'impaire') ? 'selected' : '' ?>>Semaine impaire</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Période (optionnel)</label>
      <select name="periode_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="">— Toute l'année —</option>
        <?php foreach ($periodes as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
        Créer l'EDT
      </button>
      <a href="/v2/vie-scolaire/emplois-du-temps" class="border border-slate-300 text-slate-600 hover:bg-slate-50 px-6 py-2 rounded-lg text-sm font-medium transition">
        Annuler
      </a>
    </div>
  </form>

</div>

<?php $content = ob_get_clean(); ?>
<?php include base_path('app/Views/layouts/app.php'); ?>
