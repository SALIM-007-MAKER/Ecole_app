<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard, array $enfants */ ?>
<div class="space-y-6">

  <!-- Sélecteur enfants -->
  <?php if (!empty($enfants)): ?>
  <div class="flex flex-wrap gap-3">
    <?php foreach ($enfants as $enfant): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
      <div class="w-8 h-8 rounded-full bg-emerald-200 flex items-center justify-center text-emerald-700 font-bold text-sm">
        <?= strtoupper(substr((string)($enfant['prenom'] ?? 'E'), 0, 1)) ?>
      </div>
      <div>
        <p class="text-sm font-medium text-emerald-900"><?= htmlspecialchars(($enfant['prenom'] ?? '') . ' ' . ($enfant['nom'] ?? '')) ?></p>
        <p class="text-xs text-emerald-600"><?= htmlspecialchars($enfant['classe_nom'] ?? 'Classe non assignée') ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Actions rapides -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php foreach ([
      ['Enfants','users','parent/enfants','bg-emerald-50 text-emerald-700'],
      ['Notes','star','parent/notes','bg-amber-50 text-amber-700'],
      ['Absences','user-x','parent/absences','bg-red-50 text-red-700'],
      ['Paiements','credit-card','parent/paiements','bg-violet-50 text-violet-700'],
      ['Documents','file','parent/documents','bg-slate-50 text-slate-700'],
      ['Messagerie','mail','parent/messagerie','bg-teal-50 text-teal-700'],
    ] as [$label, $icon, $path, $colors]): ?>
    <a href="/v2/portals/<?= $path ?>"
       class="flex flex-col items-center gap-2 p-3 rounded-xl border border-slate-200 hover:shadow-sm transition-shadow <?= $colors ?>">
      <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
      <span class="text-xs font-medium"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Widgets -->
  <?php if (!empty($dashboard->widgets)): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($dashboard->widgets as $widget): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm
                <?= in_array($widget->taille, ['lg','xl']) ? 'md:col-span-2' : '' ?>">
      <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2 mb-3">
        <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-4 h-4 text-emerald-500"></i>
        <?= htmlspecialchars($widget->titre) ?>
      </h3>
      <?php if (!empty($widget->data)): ?>
      <div class="text-sm text-slate-600">
        <?php if (isset($widget->data['total_du'])): ?>
          <p class="text-xl font-bold <?= (float)$widget->data['total_du'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
            <?= number_format((float)$widget->data['total_du'], 0, ',', ' ') ?> FCFA
          </p>
          <p class="text-xs text-slate-400"><?= (int)($widget->data['nb'] ?? 0) ?> facture(s) en attente</p>
        <?php elseif (isset($widget->data['non_justifiees'])): ?>
          <p class="text-xl font-bold text-slate-800"><?= count((array)($widget->data['absences'] ?? [])) ?></p>
          <p class="text-xs text-red-500"><?= (int)$widget->data['non_justifiees'] ?> non justifiée(s)</p>
        <?php else: ?>
          <p class="text-slate-400 text-xs">—</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
