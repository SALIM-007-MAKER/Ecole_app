<?php
$titre = 'Modifier un retard — Vie Scolaire V2';
ob_start();
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
?>
<div class="max-w-2xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="/v2/vie-scolaire/retards" class="hover:text-violet-600">Retards</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="/v2/vie-scolaire/retards/<?= $retard['id'] ?>" class="hover:text-violet-600">Retard #<?= $retard['id'] ?></a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Modifier</span>
  </div>

  <h1 class="text-2xl font-bold text-slate-800 mb-6">Modifier le retard</h1>

  <?php if (!empty($errors)): ?>
  <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <ul class="list-disc list-inside">
      <?php foreach ($errors as $field => $msgs): foreach ($msgs as $m): ?>
      <li><?= htmlspecialchars($m) ?></li>
      <?php endforeach; endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6 text-sm text-amber-700">
    <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
    Toute modification est enregistrée dans l'historique d'audit.
    <?php if ($retard['presence_id']): ?>
    Ce retard provient d'un appel — la modification ici ne met pas à jour la session de présence.
    <?php endif; ?>
  </div>

  <form method="POST" action="/v2/vie-scolaire/retards/<?= $retard['id'] ?>/update"
        class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure prévue</label>
        <input type="time" name="heure_prevue"
               value="<?= htmlspecialchars($retard['heure_prevue'] ?? '') ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Heure d'arrivée <span class="text-red-500">*</span></label>
        <input type="time" name="heure_arrivee" required
               value="<?= htmlspecialchars($retard['heure_arrivee']) ?>"
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 mb-1">Observation</label>
      <textarea name="observation" rows="3"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"><?= htmlspecialchars($retard['observation'] ?? '') ?></textarea>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="/v2/vie-scolaire/retards/<?= $retard['id'] ?>"
         class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Annuler</a>
      <button type="submit"
              class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        Enregistrer les modifications
      </button>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../../Views/layouts/app.php';
