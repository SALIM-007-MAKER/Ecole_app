<?php /** @var array $bulletins */ ?>
<div class="space-y-3">
  <?php if (empty($bulletins)): ?>
  <div class="text-center py-10 text-slate-400"><i data-lucide="file-text" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Aucun bulletin disponible.</p></div>
  <?php else: ?>
  <?php foreach ($bulletins as $b): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-5 flex items-center justify-between shadow-sm">
    <div>
      <p class="font-semibold text-slate-800"><?= htmlspecialchars($b['periode_nom'] ?? '') ?></p>
      <p class="text-xs text-slate-400"><?= htmlspecialchars($b['annee_scolaire'] ?? '') ?></p>
    </div>
    <div class="text-center mr-6">
      <p class="text-2xl font-bold text-blue-600"><?= number_format((float)($b['moyenne_generale'] ?? 0), 2) ?></p>
      <p class="text-xs text-slate-400">moyenne générale</p>
    </div>
    <div class="text-center">
      <span class="bg-<?= ($b['mention'] ?? '') === 'TB' ? 'green' : (($b['mention'] ?? '') === 'B' ? 'blue' : 'slate') ?>-100 text-<?= ($b['mention'] ?? '') === 'TB' ? 'green' : (($b['mention'] ?? '') === 'B' ? 'blue' : 'slate') ?>-700 text-sm font-medium px-3 py-1 rounded-full"><?= htmlspecialchars($b['mention'] ?? 'N/A') ?></span>
    </div>
    <a href="/v2/academique/bulletins/<?= (int)$b['id'] ?>/pdf" class="text-blue-600 hover:text-blue-800 transition-colors" title="Télécharger PDF">
      <i data-lucide="download" class="w-5 h-5"></i>
    </a>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
