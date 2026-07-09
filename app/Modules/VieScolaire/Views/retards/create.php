<?php
$titre = 'Saisir un retard — Vie Scolaire V2';
ob_start();
$errors   = $_SESSION['errors']    ?? [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['errors'], $_SESSION['old_input']);
?>
<div class="max-w-2xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="/v2/vie-scolaire/retards" class="hover:text-violet-600">Retards</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Saisie manuelle</span>
  </div>

  <h1 class="text-2xl font-bold text-slate-800 mb-6">Saisir un retard</h1>

  <?php if (!empty($errors)): ?>
  <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <ul class="list-disc list-inside">
      <?php foreach ($errors as $field => $msgs): foreach ($msgs as $m): ?>
      <li><?= htmlspecialchars($m) ?></li>
      <?php endforeach; endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="POST" action="/v2/vie-scolaire/retards" class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Classe <span class="text-red-500">*</span></label>
        <select name="classe_id" id="classe_id" required
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
          <option value="">Sélectionner</option>
          <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($oldInput['classe_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['nom']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Numéro élève <span class="text-red-500">*</span></label>
        <input type="number" name="eleve_id" required min="1"
               value="<?= htmlspecialchars($oldInput['eleve_id'] ?? '') ?>"
               placeholder="ID élève"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
        <input type="date" name="date_retard" required
               value="<?= htmlspecialchars($oldInput['date_retard'] ?? date('Y-m-d')) ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Année scolaire <span class="text-red-500">*</span></label>
        <input type="text" name="annee_scolaire" required placeholder="ex: 2024-2025"
               value="<?= htmlspecialchars($oldInput['annee_scolaire'] ?? (date('Y') . '-' . (date('Y') + 1))) ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure prévue</label>
        <input type="time" name="heure_prevue"
               value="<?= htmlspecialchars($oldInput['heure_prevue'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure d'arrivée <span class="text-red-500">*</span></label>
        <input type="time" name="heure_arrivee" required
               value="<?= htmlspecialchars($oldInput['heure_arrivee'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Observation</label>
      <textarea name="observation" rows="3" placeholder="Observation optionnelle..."
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"><?= htmlspecialchars($oldInput['observation'] ?? '') ?></textarea>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="/v2/vie-scolaire/retards"
         class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Annuler</a>
      <button type="submit"
              class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        Enregistrer le retard
      </button>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../../Views/layouts/app.php';
