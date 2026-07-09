<?php /** @var array $contrats */ ?>
<div class="space-y-4">
  <p class="text-sm text-slate-500">Contrats expirant dans les 60 prochains jours.</p>
  <?php if (empty($contrats)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-green-300"></i><p>Aucun contrat expirant.</p></div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($contrats as $c): ?>
    <div class="bg-white border <?= (int)($c['jours_restants'] ?? 999) <= 30 ? 'border-red-300' : 'border-amber-200' ?> rounded-xl p-4 flex items-center justify-between shadow-sm">
      <div>
        <p class="font-semibold text-slate-800"><?= htmlspecialchars($c['employe_nom'] ?? '') ?></p>
        <p class="text-xs text-slate-400"><?= htmlspecialchars($c['type_contrat'] ?? '') ?> — expire le <?= htmlspecialchars($c['date_fin'] ?? '') ?></p>
      </div>
      <span class="text-sm font-bold <?= (int)($c['jours_restants'] ?? 999) <= 30 ? 'text-red-600' : 'text-amber-600' ?>">
        <?= (int)($c['jours_restants'] ?? 0) ?>j
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
