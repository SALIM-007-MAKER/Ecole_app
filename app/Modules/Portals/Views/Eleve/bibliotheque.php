<?php /** @var array $emprunts */ ?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h2 class="text-sm font-semibold text-slate-700">Mes emprunts en cours</h2>
    <a href="<?= BASE_URL ?>/v2/bibliotheque" class="text-xs text-blue-600 hover:underline">Catalogue complet →</a>
  </div>
  <?php if (empty($emprunts)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="book" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Aucun emprunt en cours.</p></div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($emprunts as $e): ?>
    <div class="bg-white border <?= (int)strtotime($e['date_echeance'] ?? '') < time() ? 'border-red-300' : 'border-slate-200' ?> rounded-xl p-4 flex items-center gap-4 shadow-sm">
      <i data-lucide="book" class="w-8 h-8 text-purple-400 flex-shrink-0"></i>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($e['livre_titre'] ?? '') ?></p>
        <p class="text-xs text-slate-400"><?= htmlspecialchars($e['isbn'] ?? '') ?></p>
      </div>
      <div class="text-right text-xs">
        <p class="text-slate-500">Retour avant</p>
        <p class="font-medium <?= (int)strtotime($e['date_echeance'] ?? '') < time() ? 'text-red-600' : 'text-slate-700' ?>"><?= htmlspecialchars($e['date_echeance'] ?? '') ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
