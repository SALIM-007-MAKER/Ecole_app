<?php /** @var array $sessions */ ?>
<div class="space-y-4">
  <a href="<?= BASE_URL ?>/v2/rh/formations" class="inline-flex items-center gap-2 text-sm text-rose-600 hover:underline">
    <i data-lucide="book-open" class="w-4 h-4"></i> Voir toutes les formations
  </a>
  <?php if (empty($sessions)): ?>
  <div class="text-center py-8 text-slate-400"><p>Aucune session planifiée.</p></div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($sessions as $s): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
      <p class="font-semibold text-slate-800"><?= htmlspecialchars($s['formation_titre'] ?? '') ?></p>
      <p class="text-xs text-slate-400 mt-1">du <?= htmlspecialchars($s['date_debut'] ?? '') ?> au <?= htmlspecialchars($s['date_fin'] ?? '') ?> • <?= (int)($s['nb_inscrits'] ?? 0) ?> inscrits</p>
      <span class="mt-2 inline-block text-xs bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full"><?= htmlspecialchars($s['statut'] ?? '') ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
