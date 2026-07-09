<?php /** @var array $evaluations */ ?>
<div class="space-y-3">
  <?php foreach ($evaluations as $e): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between">
    <div>
      <p class="font-semibold text-slate-800"><?= htmlspecialchars($e['employe_nom'] ?? '') ?></p>
      <p class="text-xs text-slate-400"><?= htmlspecialchars($e['type_evaluation'] ?? '') ?> — <?= htmlspecialchars($e['date_prevue'] ?? '') ?></p>
    </div>
    <a href="/v2/rh/evaluations/<?= (int)$e['id'] ?>" class="text-xs bg-rose-50 text-rose-700 px-3 py-1 rounded-lg hover:bg-rose-100 transition-colors">Voir</a>
  </div>
  <?php endforeach; ?>
  <?php if (empty($evaluations)): ?>
  <div class="text-center py-8 text-slate-400"><p>Aucune évaluation planifiée.</p></div>
  <?php endif; ?>
</div>
