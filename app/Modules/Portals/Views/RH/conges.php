<?php /** @var array $conges, string $statut */ ?>
<div class="space-y-4">
  <div class="flex items-center gap-3">
    <?php foreach (['en_attente'=>'En attente','approuve'=>'Approuvés','refuse'=>'Refusés'] as $s => $label): ?>
    <a href="?statut=<?= $s ?>"
       class="text-sm px-3 py-1.5 rounded-full <?= $statut === $s ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?> transition-colors">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($conges)): ?>
  <div class="text-center py-10 text-slate-400"><p>Aucune demande.</p></div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($conges as $c): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between shadow-sm">
      <div>
        <p class="font-semibold text-slate-800"><?= htmlspecialchars($c['employe_nom'] ?? '') ?></p>
        <p class="text-xs text-slate-400"><?= htmlspecialchars($c['type_conge'] ?? '') ?> — du <?= htmlspecialchars($c['date_debut'] ?? '') ?> au <?= htmlspecialchars($c['date_fin'] ?? '') ?></p>
      </div>
      <?php if ($statut === 'en_attente'): ?>
      <div class="flex gap-2">
        <a href="/v2/rh/conges/<?= (int)$c['id'] ?>/approuver" class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 transition-colors">Approuver</a>
        <a href="/v2/rh/conges/<?= (int)$c['id'] ?>/refuser" class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded-lg hover:bg-red-200 transition-colors">Refuser</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
