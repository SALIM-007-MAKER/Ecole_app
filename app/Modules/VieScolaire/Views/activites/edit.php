<?php $title = 'Modifier — ' . htmlspecialchars($activity['titre']); ?>
<?php ob_start(); ?>

<?php $classeIds = array_column($activity['classes'] ?? [], 'id'); ?>
<?php $respIds   = array_column($activity['responsables'] ?? [], 'id'); ?>

<div class="p-6 max-w-3xl mx-auto space-y-6">

  <div class="flex items-center gap-3">
    <a href="/v2/vie-scolaire/activites/<?= $activity['id'] ?>" class="text-slate-400 hover:text-slate-600">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <h1 class="text-2xl font-bold text-slate-800">Modifier l'activité</h1>
  </div>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
      <?= $_SESSION['flash_error'] ?> <?php unset($_SESSION['flash_error']); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/v2/vie-scolaire/activites/<?= $activity['id'] ?>/update"
        class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="grid grid-cols-2 gap-4">
      <div class="col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Titre <span class="text-red-500">*</span></label>
        <input type="text" name="titre" value="<?= htmlspecialchars($activity['titre']) ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Catégorie <span class="text-red-500">*</span></label>
        <select name="categorie_id" required
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $activity['categorie_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Année scolaire</label>
        <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($activity['annee_scolaire']) ?>"
               required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
        <input type="date" name="date_activite" value="<?= htmlspecialchars($activity['date_activite']) ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Lieu</label>
        <input type="text" name="lieu" value="<?= htmlspecialchars($activity['lieu'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure de début <span class="text-red-500">*</span></label>
        <input type="time" name="heure_debut" value="<?= htmlspecialchars($activity['heure_debut']) ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure de fin <span class="text-red-500">*</span></label>
        <input type="time" name="heure_fin" value="<?= htmlspecialchars($activity['heure_fin']) ?>" required
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Capacité maximale</label>
        <input type="number" name="capacite_max" min="1" value="<?= $activity['capacite_max'] ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Organisateur</label>
        <select name="organisateur_id"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
          <option value="">— Optionnel —</option>
          <?php foreach ($enseignants as $e): ?>
            <option value="<?= $e['id'] ?>" <?= ($e['id'] == $activity['organisateur_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
      <textarea name="description" rows="3"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500"><?= htmlspecialchars($activity['description'] ?? '') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Classes concernées</label>
      <div class="grid grid-cols-3 gap-2 max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-3">
        <?php foreach ($classes as $cl): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="classe_ids[]" value="<?= $cl['id'] ?>"
                   class="rounded text-violet-600"
                   <?= in_array($cl['id'], $classeIds) ? 'checked' : '' ?>>
            <?= htmlspecialchars($cl['nom']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-2">Responsables</label>
      <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto border border-slate-200 rounded-lg p-3">
        <?php foreach ($enseignants as $e): ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="responsable_ids[]" value="<?= $e['id'] ?>"
                   class="rounded text-violet-600"
                   <?= in_array($e['id'], $respIds) ? 'checked' : '' ?>>
            <?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
        Enregistrer
      </button>
      <a href="/v2/vie-scolaire/activites/<?= $activity['id'] ?>"
         class="border border-slate-300 text-slate-600 hover:bg-slate-50 px-6 py-2 rounded-lg text-sm font-medium transition">
        Annuler
      </a>
    </div>
  </form>
</div>

<?php $content = ob_get_clean(); ?>
<?php include base_path('app/Views/layouts/app.php'); ?>
