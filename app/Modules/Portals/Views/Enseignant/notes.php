<?php /** @var array $classes */ ?>
<div class="space-y-4">
  <p class="text-sm text-slate-500">Sélectionnez une classe pour saisir les notes.</p>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php foreach ($classes as $cls): ?>
    <a href="<?= BASE_URL ?>/v2/academique/notes?classe_id=<?= (int)$cls['id'] ?>"
       class="flex items-center justify-between bg-white border border-slate-200 rounded-xl p-4 hover:border-teal-400 transition-colors group">
      <div>
        <p class="font-medium text-slate-800"><?= htmlspecialchars($cls['nom'] ?? '') ?></p>
        <p class="text-xs text-slate-400"><?= htmlspecialchars($cls['matiere_nom'] ?? '') ?></p>
      </div>
      <i data-lucide="edit-3" class="w-4 h-4 text-slate-400 group-hover:text-teal-600 transition-colors"></i>
    </a>
    <?php endforeach; ?>
  </div>
</div>
