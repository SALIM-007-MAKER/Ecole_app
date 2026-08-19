<?php /** @var \App\Modules\Portals\DTOs\DashboardDTO $dashboard, array $kpi */ ?>
<div class="space-y-6">

  <!-- KPI Barre -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
      <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Encaissé aujourd'hui</p>
      <p class="text-2xl font-bold text-green-700"><?= number_format((float)($kpi['encaisse_jour'] ?? 0), 0, ',', ' ') ?> <span class="text-sm font-normal">FCFA</span></p>
    </div>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
      <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Impayés</p>
      <p class="text-2xl font-bold text-amber-700"><?= number_format((float)($kpi['impayes_montant'] ?? 0), 0, ',', ' ') ?> <span class="text-sm font-normal">FCFA</span></p>
      <p class="text-xs text-amber-500"><?= (int)($kpi['impayes_nb'] ?? 0) ?> dossier(s)</p>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
      <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Recettes ce mois</p>
      <p class="text-2xl font-bold text-blue-700"><?= number_format((float)($kpi['recettes_mois'] ?? 0), 0, ',', ' ') ?> <span class="text-sm font-normal">FCFA</span></p>
    </div>
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
      <p class="text-xs font-medium text-slate-600 uppercase tracking-wide">Date</p>
      <p class="text-lg font-semibold text-slate-700"><?= date('d/m/Y') ?></p>
    </div>
  </div>

  <!-- Actions rapides -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
    <?php foreach ([
      ['Factures','file-minus','comptabilite/factures','bg-blue-50 text-blue-700'],
      ['Paiements','credit-card','comptabilite/paiements','bg-green-50 text-green-700'],
      ['Caisse','dollar-sign','comptabilite/caisse','bg-amber-50 text-amber-700'],
      ['Impayés','alert-triangle','comptabilite/impayes','bg-red-50 text-red-700'],
      ['Rapports','bar-chart-2','comptabilite/rapports','bg-slate-50 text-slate-700'],
    ] as [$label, $icon, $path, $colors]): ?>
    <a href="<?= BASE_URL ?>/v2/portals/<?= $path ?>"
       class="flex flex-col items-center gap-2 p-3 rounded-xl border border-slate-200 hover:shadow-sm transition-shadow <?= $colors ?>">
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
                <?= in_array($widget->taille, ['lg','xl']) ? 'md:col-span-2' : '' ?>"
         <?php if ($widget->refreshable): ?> data-refresh="<?= $widget->refreshInterval ?>"<?php endif; ?>>
      <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide flex items-center gap-1 mb-2">
        <i data-lucide="<?= htmlspecialchars($widget->icon) ?>" class="w-3.5 h-3.5 text-amber-500"></i>
        <?= htmlspecialchars($widget->titre) ?>
      </h3>
      <?php if (!empty($widget->data)): ?>
      <div class="text-sm">
        <?php if (isset($widget->data['total']) && isset($widget->data['mois'])): ?>
          <p class="text-xl font-bold text-amber-600"><?= number_format((float)$widget->data['total'], 0, ',', ' ') ?></p>
          <?php if (isset($widget->data['variation_pct'])): ?>
          <p class="text-xs <?= (float)($widget->data['variation_pct'] ?? 0) >= 0 ? 'text-green-500' : 'text-red-500' ?>">
            <?= (float)$widget->data['variation_pct'] >= 0 ? '+' : '' ?><?= $widget->data['variation_pct'] ?>% vs mois précédent
          </p>
          <?php endif; ?>
        <?php elseif (isset($widget->data['taux'])): ?>
          <p class="text-2xl font-bold <?= (float)$widget->data['taux'] >= 80 ? 'text-green-600' : 'text-amber-600' ?>"><?= $widget->data['taux'] ?>%</p>
          <p class="text-xs text-slate-400">taux de recouvrement</p>
        <?php elseif (isset($widget->data['nb_en_retard'])): ?>
          <p class="text-xl font-bold text-red-600"><?= (int)($widget->data['nb'] ?? 0) ?> factures</p>
          <p class="text-xs text-red-500"><?= (int)$widget->data['nb_en_retard'] ?> en retard</p>
        <?php elseif (isset($widget->data['paiements'])): ?>
          <ul class="space-y-1">
            <?php foreach (array_slice((array)$widget->data['paiements'], 0, 4) as $p): ?>
            <li class="flex justify-between text-xs">
              <span class="truncate text-slate-600"><?= htmlspecialchars($p['eleve_nom'] ?? '') ?></span>
              <span class="font-medium text-green-700 ml-2"><?= number_format((float)($p['montant'] ?? 0), 0) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
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
