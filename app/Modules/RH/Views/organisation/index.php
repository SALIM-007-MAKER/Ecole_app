<?php
/** @var array $tree, $stats, $canCreate, $canExport */
$title = 'Organigramme — Structure Organisationnelle';
?>

  <!-- En-tête -->
  <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="network" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Organigramme</h1>
        <p class="text-slate-500 text-sm mt-0.5">Structure organisationnelle de l'établissement</p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap flex-shrink-0">
      <?php if ($canExport): ?>
        <a href="<?= BASE_URL ?>/v2/rh/organisation/export/departements"
           class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
          <i data-lucide="download" class="w-4 h-4"></i>
          Export CSV
        </a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/organisation/departements/create"
           class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700 transition-colors">
          <i data-lucide="plus" class="w-4 h-4"></i>
          Nouveau département
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Flash messages -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
      <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>
  <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
      <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <!-- Compteurs rapides -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <?php $statCards = [
      ['label' => 'Départements', 'value' => $stats['nb_departements'], 'icon' => 'building-2',  'color' => 'text-violet-600',  'bg' => 'bg-violet-100'],
      ['label' => 'Services',     'value' => $stats['nb_services'],     'icon' => 'boxes',        'color' => 'text-blue-600',    'bg' => 'bg-blue-100'],
      ['label' => 'Postes',       'value' => $stats['nb_postes'],       'icon' => 'briefcase',    'color' => 'text-amber-600',   'bg' => 'bg-amber-100'],
      ['label' => 'Fonctions',    'value' => $stats['nb_fonctions'],    'icon' => 'badge-check',  'color' => 'text-emerald-600', 'bg' => 'bg-emerald-100'],
    ]; ?>
    <?php foreach ($statCards as $card): ?>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg <?= $card['bg'] ?> flex items-center justify-center flex-shrink-0">
          <i data-lucide="<?= $card['icon'] ?>" class="w-5 h-5 <?= $card['color'] ?>"></i>
        </div>
        <div>
          <p class="text-2xl font-bold text-slate-900"><?= $card['value'] ?></p>
          <p class="text-xs text-slate-500"><?= $card['label'] ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Navigation rapide -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
    <a href="<?= BASE_URL ?>/v2/rh/organisation/departements" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <i data-lucide="building-2" class="w-4 h-4 text-violet-500 mx-auto mb-1.5"></i>
      <p class="font-semibold text-slate-700 text-sm">Départements</p>
      <p class="text-xs text-slate-400 mt-1">Gérer la structure</p>
    </a>
    <a href="<?= BASE_URL ?>/v2/rh/organisation/postes" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <i data-lucide="briefcase" class="w-4 h-4 text-violet-500 mx-auto mb-1.5"></i>
      <p class="font-semibold text-slate-700 text-sm">Postes</p>
      <p class="text-xs text-slate-400 mt-1">Fiches de poste</p>
    </a>
    <a href="<?= BASE_URL ?>/v2/rh/organisation/fonctions" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <i data-lucide="badge-check" class="w-4 h-4 text-violet-500 mx-auto mb-1.5"></i>
      <p class="font-semibold text-slate-700 text-sm">Fonctions</p>
      <p class="text-xs text-slate-400 mt-1">Fonctions transversales</p>
    </a>
    <a href="<?= BASE_URL ?>/v2/rh/organisation/statistiques" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-500 mx-auto mb-1.5"></i>
      <p class="font-semibold text-slate-700 text-sm">Statistiques</p>
      <p class="text-xs text-slate-400 mt-1">Tableaux de bord</p>
    </a>
  </div>

  <!-- Organigramme -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-6">Organigramme hiérarchique</h2>

    <?php if (empty($tree)): ?>
      <div class="text-center py-8">
        <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-3">
          <i data-lucide="network" class="w-5 h-5 text-slate-400"></i>
        </div>
        <p class="text-slate-400 text-sm">Aucun département configuré.</p>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <?php
        function renderTreeNode(array $node, int $depth = 0): void {
            $indent   = str_repeat('ml-6 ', $depth);
            $bgColor  = $depth === 0 ? 'bg-violet-50 border-violet-200' : 'bg-slate-50 border-slate-200';
            $textSize = $depth === 0 ? 'font-semibold text-slate-900' : 'font-medium text-slate-700';
            $nom    = htmlspecialchars($node['nom'],  ENT_QUOTES, 'UTF-8');
            $code   = htmlspecialchars($node['code'], ENT_QUOTES, 'UTF-8');
            $resp   = isset($node['resp_nom']) ? htmlspecialchars(($node['resp_prenom'] ?? '') . ' ' . $node['resp_nom'], ENT_QUOTES, 'UTF-8') : '';
            ?>
            <div class="<?= $indent ?> mb-2">
              <div class="flex items-center gap-3 p-3 rounded-lg border <?= $bgColor ?>">
                <?php if ($depth > 0): ?>
                  <i data-lucide="corner-down-right" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                <?php else: ?>
                  <i data-lucide="building-2" class="w-5 h-5 text-violet-500 flex-shrink-0"></i>
                <?php endif; ?>
                <div class="flex-1 min-w-0">
                  <span class="<?= $textSize ?> text-sm"><?= $nom ?></span>
                  <span class="ml-2 text-xs font-mono text-slate-400 bg-slate-100 px-1 rounded"><?= $code ?></span>
                  <?php if ($resp): ?>
                    <span class="ml-3 text-xs text-slate-500">• <?= $resp ?></span>
                  <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                  <span><?= (int)($node['nb_employes'] ?? 0) ?> employé(s)</span>
                  <?php if (!$node['actif']): ?>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">Inactif</span>
                  <?php endif; ?>
                  <a href="<?= BASE_URL ?>/v2/rh/organisation/departements/<?= (int)$node['id'] ?>"
                     class="inline-flex items-center gap-1 text-violet-600 hover:text-violet-800 font-medium">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Voir
                  </a>
                </div>
              </div>
              <?php foreach ($node['children'] ?? [] as $child): ?>
                <?php renderTreeNode($child, $depth + 1); ?>
              <?php endforeach; ?>
            </div>
            <?php
        }
        foreach ($tree as $root): renderTreeNode($root); endforeach;
        ?>
      </div>
    <?php endif; ?>
  </div>
