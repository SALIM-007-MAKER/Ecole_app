<?php /** @var array $eleve */ ?>
<div class="max-w-lg">
  <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
    <?php if (!empty($eleve)): ?>
    <dl class="space-y-3 text-sm">
      <div class="flex justify-between">
        <dt class="text-slate-500">Classe</dt>
        <dd class="font-medium text-slate-800"><?= htmlspecialchars($eleve['classe_nom'] ?? '—') ?></dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-slate-500">Niveau</dt>
        <dd class="font-medium text-slate-800"><?= htmlspecialchars($eleve['niveau'] ?? '—') ?></dd>
      </div>
    </dl>
    <?php else: ?>
    <p class="text-slate-400 text-sm">Profil non disponible.</p>
    <?php endif; ?>
  </div>
</div>
