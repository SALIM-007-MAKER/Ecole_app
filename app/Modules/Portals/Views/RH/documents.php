<?php /** @var array $documents */ ?>
<div class="space-y-3">
  <?php foreach ($documents as $d): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center gap-4 shadow-sm">
    <i data-lucide="file" class="w-8 h-8 text-slate-400 flex-shrink-0"></i>
    <div class="flex-1 min-w-0">
      <p class="font-medium text-slate-800 truncate"><?= htmlspecialchars($d['nom_document'] ?? $d['type_document'] ?? '') ?></p>
      <p class="text-xs text-slate-400"><?= htmlspecialchars($d['employe_nom'] ?? '') ?> — <?= htmlspecialchars($d['created_at'] ?? '') ?></p>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($documents)): ?>
  <div class="text-center py-8 text-slate-400"><p>Aucun document.</p></div>
  <?php endif; ?>
</div>
