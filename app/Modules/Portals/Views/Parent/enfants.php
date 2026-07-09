<?php /** @var array $enfants */ ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ($enfants as $enfant): ?>
  <div class="bg-white border border-emerald-200 rounded-xl p-5 shadow-sm">
    <div class="flex items-center gap-4 mb-4">
      <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center font-bold text-emerald-700">
        <?= strtoupper(substr((string)($enfant['prenom'] ?? 'E'), 0, 1)) ?>
      </div>
      <div>
        <p class="font-semibold text-slate-800"><?= htmlspecialchars(($enfant['prenom'] ?? '') . ' ' . ($enfant['nom'] ?? '')) ?></p>
        <p class="text-xs text-emerald-600"><?= htmlspecialchars($enfant['classe_nom'] ?? 'Classe non assignée') ?> — <?= htmlspecialchars($enfant['niveau'] ?? '') ?></p>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <a href="/v2/portals/parent/notes" class="text-center text-xs bg-amber-50 text-amber-700 rounded-lg py-2 hover:bg-amber-100 transition-colors">Notes</a>
      <a href="/v2/portals/parent/absences" class="text-center text-xs bg-red-50 text-red-700 rounded-lg py-2 hover:bg-red-100 transition-colors">Absences</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($enfants)): ?>
  <div class="col-span-3 text-center py-10 text-slate-400"><i data-lucide="users" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Aucun enfant enregistré.</p></div>
  <?php endif; ?>
</div>
