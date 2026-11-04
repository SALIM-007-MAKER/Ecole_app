<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard */ ?>
<div class="space-y-6">

  <!-- KPI Bar -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
      <p class="text-xs font-medium text-violet-600 uppercase tracking-wide">Élèves</p>
      <p class="text-2xl font-bold text-violet-900" id="kpi-eleves">—</p>
    </div>
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
      <p class="text-xs font-medium text-violet-600 uppercase tracking-wide">Employés</p>
      <p class="text-2xl font-bold text-violet-900" id="kpi-employes">—</p>
    </div>
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
      <p class="text-xs font-medium text-violet-600 uppercase tracking-wide">Classes</p>
      <p class="text-2xl font-bold text-violet-900" id="kpi-classes">—</p>
    </div>
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
      <p class="text-xs font-medium text-violet-600 uppercase tracking-wide">Utilisateurs</p>
      <p class="text-2xl font-bold text-violet-900" id="kpi-users">—</p>
    </div>
  </div>

  <!-- Widget Grid -->
  <?php if (!empty($dashboard->widgets)): ?>
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <?php foreach ($dashboard->widgets as $widget): ?>
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm
                <?= $widget->taille === 'lg' || $widget->taille === 'xl' ? 'md:col-span-2' : '' ?>"
         data-widget-id="<?= htmlspecialchars($widget->id) ?>"
         <?php if ($widget->refreshable && $widget->refreshInterval > 0): ?>
         data-refresh="<?= $widget->refreshInterval ?>"
         <?php endif; ?>>
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-4 h-4 text-violet-500"></i>
          <?= htmlspecialchars($widget->titre) ?>
        </h3>
        <?php if ($widget->refreshable): ?>
        <button onclick="refreshWidget('<?= htmlspecialchars($widget->id) ?>')"
                class="text-slate-400 hover:text-violet-600 transition-colors">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        </button>
        <?php endif; ?>
      </div>
      <?php if (!empty($widget->data)): ?>
      <div class="widget-content text-sm text-slate-600">
        <?php if (isset($widget->data['nb_eleves'])): ?>
          <dl class="grid grid-cols-2 gap-2">
            <div><dt class="text-xs text-slate-400">Élèves</dt><dd class="font-semibold text-slate-800"><?= number_format((int)$widget->data['nb_eleves']) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Classes</dt><dd class="font-semibold text-slate-800"><?= number_format((int)($widget->data['nb_classes'] ?? 0)) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Employés</dt><dd class="font-semibold text-slate-800"><?= number_format((int)($widget->data['nb_employes'] ?? 0)) ?></dd></div>
            <div><dt class="text-xs text-slate-400">Utilisateurs</dt><dd class="font-semibold text-slate-800"><?= number_format((int)($widget->data['nb_users'] ?? 0)) ?></dd></div>
          </dl>
        <?php elseif (isset($widget->data['logs'])): ?>
          <ul class="space-y-1">
            <?php foreach (array_slice((array)$widget->data['logs'], 0, 5) as $log): ?>
            <li class="flex items-center justify-between text-xs">
              <span class="text-slate-500"><?= htmlspecialchars((string)($log['action'] ?? '')) ?></span>
              <span class="text-slate-400"><?= htmlspecialchars(substr((string)($log['created_at'] ?? ''), 11, 5)) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <pre class="text-xs text-slate-400 overflow-x-auto"><?= htmlspecialchars(substr(json_encode($widget->data, JSON_PRETTY_PRINT), 0, 200)) ?></pre>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="text-center py-12 text-slate-400">
    <i data-lucide="layout-dashboard" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p>Aucun widget disponible.</p>
  </div>
  <?php endif; ?>
</div>

<script>
function refreshWidget(widgetId) {
  fetch(`<?= BASE_URL ?>/api/v2/portals/admin/widgets/${encodeURIComponent(widgetId)}`, {
    headers: {'Accept': 'application/json'}
  }).then(r => r.json()).then(data => {
    console.log('Widget refreshed:', widgetId, data);
  });
}
</script>
