<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard */ ?>
<div class="space-y-6">

  <!-- Nav rapide -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php foreach ([
      ['Scolarité','users','direction/scolarite','bg-blue-50 text-blue-700'],
      ['Académique','award','direction/academique','bg-green-50 text-green-700'],
      ['Finance','trending-up','direction/finance','bg-amber-50 text-amber-700'],
      ['Vie scolaire','calendar','direction/vie-scolaire','bg-teal-50 text-teal-700'],
      ['RH','briefcase','direction/rh','bg-rose-50 text-rose-700'],
      ['Rapports','bar-chart-2','direction/rapports','bg-indigo-50 text-indigo-700'],
    ] as [$label, $icon, $path, $colors]): ?>
    <a href="<?= BASE_URL ?>/v2/portals/<?= $path ?>"
       class="flex flex-col items-center gap-2 p-3 rounded-xl border border-slate-200 hover:shadow-md transition-shadow <?= $colors ?>">
      <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
      <span class="text-xs font-medium"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Widgets -->
  <?php if (!empty($dashboard->widgets)): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($dashboard->widgets as $widget): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm
                <?= in_array($widget->taille, ['lg','xl']) ? 'md:col-span-2' : '' ?>">
      <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 flex items-center gap-1">
        <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-3.5 h-3.5 text-indigo-500"></i>
        <?= htmlspecialchars($widget->titre) ?>
      </h3>
      <?php if (!empty($widget->data)): ?>
      <div class="text-sm">
        <?php if (isset($widget->data['nb_eleves'])): ?>
          <p class="text-2xl font-bold text-indigo-600"><?= number_format((int)$widget->data['nb_eleves']) ?></p>
          <p class="text-xs text-slate-400"><?= number_format((int)($widget->data['nb_classes'] ?? 0)) ?> classes • <?= number_format((int)($widget->data['nb_inscrits'] ?? 0)) ?> inscrits</p>
        <?php elseif (isset($widget->data['recettes_mois'])): ?>
          <p class="text-2xl font-bold text-amber-600"><?= number_format((float)$widget->data['recettes_mois'], 0, ',', ' ') ?> FCFA</p>
          <p class="text-xs <?= ($widget->data['impayes_total'] ?? 0) > 0 ? 'text-red-500' : 'text-slate-400' ?>">
            Impayés: <?= number_format((float)($widget->data['impayes_total'] ?? 0), 0, ',', ' ') ?> FCFA
          </p>
        <?php elseif (isset($widget->data['effectif'])): ?>
          <p class="text-2xl font-bold text-rose-600"><?= number_format((int)$widget->data['effectif']) ?></p>
          <p class="text-xs text-slate-400"><?= (float)($widget->data['taux_presence'] ?? 0) ?>% présents aujourd'hui</p>
        <?php elseif (isset($widget->data['par_jour'])): ?>
          <p class="text-2xl font-bold text-slate-700"><?= (int)($widget->data['total'] ?? 0) ?></p>
          <p class="text-xs text-slate-400">absences cette semaine</p>
        <?php elseif (isset($widget->data['alertes'])): ?>
          <?php if (empty($widget->data['alertes'])): ?>
            <p class="text-xs text-green-600 flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Aucune alerte</p>
          <?php else: ?>
            <ul class="space-y-1">
              <?php foreach ($widget->data['alertes'] as $a): ?>
              <li class="text-xs text-amber-700 flex items-start gap-1">
                <i data-lucide="alert-triangle" class="w-3 h-3 mt-0.5 flex-shrink-0"></i>
                <?= htmlspecialchars($a['message']) ?>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-xs text-slate-400"><?= htmlspecialchars(array_key_first($widget->data) . ': ' . (string)array_values($widget->data)[0]) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php foreach ($dashboard->alertes as $alerte): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-center gap-2 text-sm text-amber-800">
    <i data-lucide="bell" class="w-4 h-4 text-amber-500 flex-shrink-0"></i>
    <?= htmlspecialchars($alerte) ?>
  </div>
  <?php endforeach; ?>
</div>
