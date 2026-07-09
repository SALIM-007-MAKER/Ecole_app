<?php /** @var array $modules */ ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($modules as $key => $mod): ?>
  <div class="bg-white border <?= ($mod['enabled'] ?? false) ? 'border-green-200' : 'border-slate-200' ?> rounded-xl p-4 shadow-sm">
    <div class="flex items-center justify-between mb-2">
      <h3 class="font-semibold text-slate-800 capitalize"><?= htmlspecialchars($key) ?></h3>
      <?php if ($mod['enabled'] ?? false): ?>
      <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
        <i data-lucide="check-circle" class="w-3 h-3"></i> Actif
      </span>
      <?php else: ?>
      <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
        <i data-lucide="pause-circle" class="w-3 h-3"></i> Inactif
      </span>
      <?php endif; ?>
    </div>
    <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($mod['namespace'] ?? '') ?></p>
  </div>
  <?php endforeach; ?>
</div>
