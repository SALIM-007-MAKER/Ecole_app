<?php /** @var array $classes */ ?>
<div class="space-y-4">
  <?php if (empty($classes)): ?>
  <div class="text-center py-12 text-slate-400"><i data-lucide="book-open" class="w-10 h-10 mx-auto mb-3 opacity-40"></i><p>Aucune classe assignée.</p></div>
  <?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($classes as $cls): ?>
    <div class="bg-white border border-teal-200 rounded-xl p-5 shadow-sm">
      <h3 class="font-semibold text-slate-800 text-lg"><?= htmlspecialchars($cls['nom'] ?? '') ?></h3>
      <p class="text-sm text-teal-600 mt-1"><?= htmlspecialchars($cls['matiere_nom'] ?? '') ?></p>
      <p class="text-xs text-slate-400 mt-2"><?= (int)($cls['nb_eleves'] ?? 0) ?> élève(s)</p>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
