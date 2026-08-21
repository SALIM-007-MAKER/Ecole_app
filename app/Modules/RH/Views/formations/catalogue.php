<?php
$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$typeColors = $model::TYPE_COLORS ?? [];
?>
<div class="space-y-6">
  <div class="flex justify-between items-center">
    <div>
      <h2 class="text-xl font-semibold text-slate-800">Catalogue des formations</h2>
      <p class="text-sm text-slate-500"><?= count($formations) ?> formation(s)</p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/rh/formations" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour sessions
      </a>
      <?php if ($policy->canManageCatalog($user)): ?>
      <a href="<?= BASE_URL ?>/v2/rh/formations/catalogue/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Ajouter formation
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtre type -->
  <div class="flex gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>/v2/rh/formations/catalogue"
       class="px-3 py-1.5 rounded-full text-sm <?= $type === '' ? 'bg-violet-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
      Tous
    </a>
    <?php foreach ($model::TYPES_FORMATION as $t): ?>
    <a href="<?= BASE_URL ?>/v2/rh/formations/catalogue?type=<?= urlencode($t) ?>"
       class="px-3 py-1.5 rounded-full text-sm <?= $type === $t ? 'bg-violet-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
      <?= $e(ucfirst($t)) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Flash -->
  <?php if ($flash = \Core\Session::getFlash('success')): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm"><?= $e($flash) ?></div>
  <?php endif; ?>

  <!-- Grille formations -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (empty($formations)): ?>
    <div class="col-span-3 text-center py-16 text-slate-400">
      <i data-lucide="book-open" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
      <p>Aucune formation dans le catalogue</p>
    </div>
    <?php endif; ?>
    <?php foreach ($formations as $f):
      $color = $typeColors[$f['type']] ?? 'slate';
    ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 flex flex-col gap-3 hover:shadow-md transition">
      <div class="flex items-start justify-between">
        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $e($color) ?>-100 text-<?= $e($color) ?>-700">
          <?= $e(ucfirst($f['type'])) ?>
        </span>
        <span class="text-xs text-slate-400 font-mono"><?= $e($f['code']) ?></span>
      </div>
      <div>
        <h3 class="font-semibold text-slate-800 leading-tight"><?= $e($f['titre']) ?></h3>
        <?php if ($f['description']): ?>
        <p class="text-sm text-slate-500 mt-1 line-clamp-2"><?= $e($f['description']) ?></p>
        <?php endif; ?>
      </div>
      <div class="grid grid-cols-2 gap-2 text-xs text-slate-500">
        <div class="flex items-center gap-1">
          <i data-lucide="clock" class="w-3.5 h-3.5"></i>
          <?= $e($model::formatDuree((float)($f['duree_heures'] ?? 0))) ?>
        </div>
        <div class="flex items-center gap-1">
          <i data-lucide="layers" class="w-3.5 h-3.5"></i>
          <?= $e(ucfirst($f['niveau'] ?? 'tous')) ?>
        </div>
        <div class="flex items-center gap-1">
          <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
          <?= $e(ucfirst($f['modalite'] ?? '—')) ?>
        </div>
        <div class="flex items-center gap-1">
          <i data-lucide="building" class="w-3.5 h-3.5"></i>
          <?= $e($f['organisme_nom'] ?? 'Interne') ?>
        </div>
      </div>
      <?php if ((float)($f['cout_unitaire'] ?? 0) > 0): ?>
      <div class="text-xs text-slate-400">
        Coût unitaire : <span class="font-medium text-slate-600"><?= number_format((float)$f['cout_unitaire'], 0, ',', ' ') ?> FCFA</span>
      </div>
      <?php endif; ?>
      <?php if ($policy->canCreate($user)): ?>
      <a href="<?= BASE_URL ?>/v2/rh/formations/sessions/create?formation_id=<?= (int)$f['id'] ?>"
         class="mt-auto text-center border border-violet-300 text-violet-700 px-3 py-1.5 rounded-lg text-xs hover:bg-violet-50 transition">
        Créer une session
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script>if(window.lucide)lucide.createIcons();</script>
