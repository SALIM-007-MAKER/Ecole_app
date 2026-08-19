<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard, array $kpi */ ?>
<div class="space-y-6">

  <!-- KPI Bar RH -->
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
      <p class="text-xs font-medium text-rose-600 uppercase tracking-wide">Effectif actif</p>
      <p class="text-2xl font-bold text-rose-700"><?= number_format((int)($kpi['nb_employes'] ?? 0)) ?></p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
      <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Présents</p>
      <p class="text-2xl font-bold text-green-700"><?= number_format((int)($kpi['nb_presents'] ?? 0)) ?></p>
      <p class="text-xs text-green-500"><?= (float)($kpi['taux_presence'] ?? 0) ?>%</p>
    </div>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
      <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Congés en attente</p>
      <p class="text-2xl font-bold text-amber-700"><?= number_format((int)($kpi['conges_en_attente'] ?? 0)) ?></p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
      <p class="text-xs font-medium text-red-600 uppercase tracking-wide">Contrats expirant</p>
      <p class="text-2xl font-bold text-red-700"><?= number_format((int)($kpi['contrats_expirant'] ?? 0)) ?></p>
      <p class="text-xs text-red-400">dans 30 jours</p>
    </div>
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
      <p class="text-xs font-medium text-slate-600 uppercase tracking-wide">Date</p>
      <p class="text-lg font-semibold text-slate-700"><?= date('d/m/Y') ?></p>
    </div>
  </div>

  <!-- Navigation RH -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php foreach ([
      ['Présences','check-circle','rh/presences','bg-green-50 text-green-700'],
      ['Congés','umbrella','rh/conges','bg-amber-50 text-amber-700'],
      ['Contrats','file-text','rh/contrats','bg-blue-50 text-blue-700'],
      ['Formations','book-open','rh/formations','bg-purple-50 text-purple-700'],
      ['Évaluations','clipboard','rh/evaluations','bg-rose-50 text-rose-700'],
      ['Documents','folder','rh/documents','bg-slate-50 text-slate-700'],
    ] as [$label, $icon, $path, $colors]): ?>
    <a href="<?= BASE_URL ?>/v2/portals/<?= $path ?>"
       class="flex flex-col items-center gap-2 p-3 rounded-xl border border-slate-200 hover:shadow-sm transition-shadow <?= $colors ?>">
      <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
      <span class="text-xs font-medium"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Widgets RH -->
  <?php if (!empty($dashboard->widgets)): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($dashboard->widgets as $widget): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm
                <?= in_array($widget->taille, ['lg','xl']) ? 'md:col-span-2' : '' ?>"
         <?php if ($widget->refreshable): ?> data-refresh="<?= $widget->refreshInterval ?>"<?php endif; ?>>
      <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2 mb-3">
        <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-4 h-4 text-rose-500"></i>
        <?= htmlspecialchars($widget->titre) ?>
      </h3>
      <?php if (!empty($widget->data)): ?>
      <div class="text-sm text-slate-600">
        <?php if (isset($widget->data['nb_employes'])): ?>
          <p class="text-2xl font-bold text-rose-600"><?= (int)$widget->data['nb_employes'] ?></p>
          <p class="text-xs text-slate-400"><?= (float)($widget->data['taux'] ?? 0) ?>% présents</p>
        <?php elseif (isset($widget->data['conges'])): ?>
          <p class="text-xl font-bold text-amber-700"><?= (int)($widget->data['total'] ?? 0) ?></p>
          <p class="text-xs text-slate-400">en attente de validation</p>
        <?php elseif (isset($widget->data['urgent'])): ?>
          <p class="text-xl font-bold <?= (int)$widget->data['urgent'] > 0 ? 'text-red-600' : 'text-slate-700' ?>"><?= (int)($widget->data['total'] ?? 0) ?></p>
          <?php if ((int)$widget->data['urgent'] > 0): ?>
          <p class="text-xs text-red-500"><?= (int)$widget->data['urgent'] ?> expirent dans 30j</p>
          <?php endif; ?>
        <?php elseif (isset($widget->data['alertes'])): ?>
          <?php if (empty($widget->data['alertes'])): ?>
            <p class="text-xs text-green-600">Aucune alerte</p>
          <?php else: ?>
            <ul class="space-y-1">
              <?php foreach ($widget->data['alertes'] as $a): ?>
              <li class="text-xs text-amber-700 flex gap-1">
                <i data-lucide="alert-triangle" class="w-3 h-3 mt-0.5 flex-shrink-0"></i>
                <?= htmlspecialchars($a['message']) ?>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
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
