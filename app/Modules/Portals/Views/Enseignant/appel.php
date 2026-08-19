<?php /** @var array $classes, string $date */ ?>
<div class="space-y-4">
  <p class="text-sm text-slate-500">Date : <strong><?= htmlspecialchars(date('d/m/Y', strtotime($date))) ?></strong></p>
  <?php foreach ($classes as $cls): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-4">
    <h3 class="font-semibold text-slate-800 mb-3"><?= htmlspecialchars($cls['nom'] ?? '') ?> — <?= htmlspecialchars($cls['matiere_nom'] ?? '') ?></h3>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/presences/appel?classe_id=<?= (int)$cls['id'] ?>&date=<?= htmlspecialchars($date) ?>"
       class="inline-flex items-center gap-2 bg-teal-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-teal-700 transition-colors">
      <i data-lucide="check-square" class="w-4 h-4"></i> Faire l'appel
    </a>
  </div>
  <?php endforeach; ?>
</div>
