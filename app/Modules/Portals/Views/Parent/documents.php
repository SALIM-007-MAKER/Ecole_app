<?php /** @var array $documents */ ?>
<div class="space-y-3">
  <?php if (empty($documents)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="folder" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Aucun document disponible.</p></div>
  <?php else: ?>
  <?php foreach ($documents as $d): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center gap-4 shadow-sm">
    <i data-lucide="file" class="w-8 h-8 text-slate-400 flex-shrink-0"></i>
    <div class="flex-1 min-w-0">
      <p class="font-medium text-slate-800 truncate"><?= htmlspecialchars($d['titre'] ?? $d['nom_fichier'] ?? '') ?></p>
      <p class="text-xs text-slate-400"><?= htmlspecialchars($d['created_at'] ?? '') ?></p>
    </div>
    <a href="<?= BASE_URL ?>/v2/documents/<?= (int)$d['id'] ?>/download" class="text-slate-400 hover:text-emerald-600 transition-colors">
      <i data-lucide="download" class="w-5 h-5"></i>
    </a>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
