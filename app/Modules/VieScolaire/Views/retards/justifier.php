<?php
$title = 'Soumettre une justification — Retards V2';
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
?>
<div class="max-w-xl mx-auto px-4 py-6">

  <div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards" class="hover:text-violet-600">Retards</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>" class="hover:text-violet-600">Retard #<?= $retard['id'] ?></a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Justifier</span>
  </div>

  <h1 class="text-2xl font-bold text-slate-800 mb-2">Soumettre une justification</h1>
  <p class="text-slate-500 text-sm mb-6">
    Retard de <strong><?= htmlspecialchars($retard['eleve_prenom'] . ' ' . $retard['eleve_nom']) ?></strong>
    le <?= htmlspecialchars($retard['date_retard']) ?>
    (<?= (int)$retard['duree_minutes'] ?> min)
  </p>

  <?php if (!empty($errors)): ?>
  <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <ul class="list-disc list-inside">
      <?php foreach ($errors as $msgs): foreach ((array)$msgs as $m): ?>
      <li><?= htmlspecialchars($m) ?></li>
      <?php endforeach; endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data"
        action="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>/justifier"
        class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

    <div>
      <label class="form-label">Motif de la justification</label>
      <textarea name="motif_description" rows="4"
                placeholder="Expliquez le motif du retard (minimum 10 caractères)..."
                class="form-textarea resize-none"></textarea>
      <p class="text-xs text-slate-400 mt-1">Un motif textuel ou un fichier joint est requis.</p>
    </div>

    <div>
      <label class="form-label">Fichier justificatif (optionnel)</label>
      <input type="file" name="fichier_justificatif" accept=".pdf,.jpg,.jpeg,.png"
             class="w-full text-sm text-slate-600 border border-slate-300 rounded-lg px-3 py-2 cursor-pointer
                    file:mr-3 file:px-3 file:py-1 file:border-0 file:rounded file:bg-violet-50 file:text-violet-700 file:text-sm">
      <p class="text-xs text-slate-400 mt-1">PDF, JPG, PNG — max 5 Mo</p>
    </div>

    <div class="flex justify-end gap-3 pt-2">
      <a href="<?= BASE_URL ?>/v2/vie-scolaire/retards/<?= $retard['id'] ?>"
         class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Annuler</a>
      <button type="submit"
              class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
        <i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Soumettre
      </button>
    </div>
  </form>
</div>
