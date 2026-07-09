<?php
/** @var array $tree, $stats, $canCreate, $canExport */
$title = 'Organigramme — Structure Organisationnelle';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — EduNova</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#7c3aed'}}}}</script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

<?php include dirname(__DIR__, 2) . '/layouts/sidebar.php'; ?>

<main class="ml-64 p-8">

  <!-- En-tête -->
  <div class="flex items-center justify-between mb-8">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Organigramme</h1>
      <p class="text-slate-500 text-sm mt-1">Structure organisationnelle de l'établissement</p>
    </div>
    <div class="flex gap-3">
      <?php if ($canExport): ?>
        <a href="/v2/rh/organisation/export/departements"
           class="flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm hover:bg-slate-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Export CSV
        </a>
      <?php endif; ?>
      <?php if ($canCreate): ?>
        <a href="/v2/rh/organisation/departements/create"
           class="flex items-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm hover:bg-violet-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
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
      ['label' => 'Départements', 'value' => $stats['nb_departements'], 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'color' => 'text-violet-600'],
      ['label' => 'Services',     'value' => $stats['nb_services'],     'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'color' => 'text-blue-600'],
      ['label' => 'Postes',       'value' => $stats['nb_postes'],       'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'text-amber-600'],
      ['label' => 'Fonctions',    'value' => $stats['nb_fonctions'],    'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'text-emerald-600'],
    ]; ?>
    <?php foreach ($statCards as $card): ?>
      <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-4">
        <div class="p-3 bg-slate-50 rounded-lg">
          <svg class="w-5 h-5 <?= $card['color'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $card['icon'] ?>"/>
          </svg>
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
    <a href="/v2/rh/organisation/departements" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <p class="font-semibold text-slate-700 text-sm">Départements</p>
      <p class="text-xs text-slate-400 mt-1">Gérer la structure</p>
    </a>
    <a href="/v2/rh/organisation/postes" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <p class="font-semibold text-slate-700 text-sm">Postes</p>
      <p class="text-xs text-slate-400 mt-1">Fiches de poste</p>
    </a>
    <a href="/v2/rh/organisation/fonctions" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <p class="font-semibold text-slate-700 text-sm">Fonctions</p>
      <p class="text-xs text-slate-400 mt-1">Fonctions transversales</p>
    </a>
    <a href="/v2/rh/organisation/statistiques" class="bg-white border border-slate-200 rounded-lg p-4 hover:border-violet-300 hover:bg-violet-50 transition-colors text-center">
      <p class="font-semibold text-slate-700 text-sm">Statistiques</p>
      <p class="text-xs text-slate-400 mt-1">Tableaux de bord</p>
    </a>
  </div>

  <!-- Organigramme -->
  <div class="bg-white rounded-xl border border-slate-200 p-6">
    <h2 class="text-lg font-semibold text-slate-800 mb-6">Organigramme hiérarchique</h2>

    <?php if (empty($tree)): ?>
      <p class="text-slate-400 text-center py-8">Aucun département configuré.</p>
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
                  <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                <?php else: ?>
                  <svg class="w-5 h-5 text-violet-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/>
                  </svg>
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
                  <a href="/v2/rh/organisation/departements/<?= (int)$node['id'] ?>"
                     class="text-violet-600 hover:underline">Voir →</a>
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

</main>
</body>
</html>
