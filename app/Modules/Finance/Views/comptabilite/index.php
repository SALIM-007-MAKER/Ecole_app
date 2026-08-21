<?php
$exercice  = $exercice  ?? null;
$stats     = $stats     ?? null;
$recent    = $recent    ?? [];
$exercices = $exercices ?? [];
$journaux  = $journaux  ?? [];
$user      = $user      ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$pct = fn(float $v, float $t): string => $t > 0 ? number_format(($v / $t) * 100, 1) . '%' : '0%';
?>
<div class="p-6 space-y-6">

  <!-- En-tête -->
  <div class="flex items-center gap-2 text-sm text-slate-500">
    <a href="<?= BASE_URL ?>/v2/finance/rapports/dashboard" class="hover:text-violet-600">Finance</a>
    <i data-lucide="chevron-right" class="w-3 h-3"></i>
    <span class="text-slate-700">Comptabilité</span>
  </div>

  <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
    <div class="flex items-start gap-4">
      <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <i data-lucide="calculator" class="w-5 h-5 text-violet-600"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Comptabilité</h1>
        <p class="text-slate-500 text-sm mt-0.5">
          <?= $exercice ? htmlspecialchars($exercice->libelle) : 'Aucun exercice ouvert' ?>
          <?php if ($exercice): ?>
            <span class="text-slate-400">—
              <?= date('d/m/Y', strtotime($exercice->date_debut)) ?> au
              <?= date('d/m/Y', strtotime($exercice->date_fin)) ?>
            </span>
          <?php endif; ?>
        </p>
      </div>
    </div>
    <div class="flex gap-2 flex-wrap flex-shrink-0">
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite/journal" class="btn btn-primary">
        <i data-lucide="book-open" class="w-4 h-4"></i>Journal
      </a>
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite/balance" class="btn btn-outline">
        <i data-lucide="scale" class="w-4 h-4"></i>Balance
      </a>
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite/grand-livre" class="btn btn-outline">
        <i data-lucide="layers" class="w-4 h-4"></i>Grand Livre
      </a>
    </div>
  </div>

  <?php if (!$exercice): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
    <i data-lucide="alert-triangle" class="w-10 h-10 text-amber-500 mx-auto mb-3"></i>
    <p class="text-amber-800 font-medium text-lg">Aucun exercice comptable ouvert</p>
    <p class="text-amber-600 text-sm mt-1 mb-4">Créez un exercice pour commencer à enregistrer des écritures comptables.</p>
    <a href="<?= BASE_URL ?>/v2/finance/comptabilite/exercices/create" class="btn btn-warning">
      <i data-lucide="plus" class="w-4 h-4"></i>Créer un exercice
    </a>
  </div>
  <?php else: ?>

  <!-- KPI Cards -->
  <?php
  $produits = (float)($stats->total_produits ?? 0);
  $charges  = (float)($stats->total_charges  ?? 0);
  $resultat = $produits - $charges;
  $caisse   = (float)($stats->solde_caisse ?? 0);
  $banque   = (float)($stats->solde_banque  ?? 0);
  ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Produits</span>
        <span class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
          <i data-lucide="trending-up" class="w-4 h-4 text-emerald-600"></i>
        </span>
      </div>
      <p class="text-2xl font-bold text-emerald-600"><?= $fmt($produits) ?></p>
      <p class="text-xs text-slate-400 mt-1">Classe 7 — recettes</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Charges</span>
        <span class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center">
          <i data-lucide="trending-down" class="w-4 h-4 text-rose-600"></i>
        </span>
      </div>
      <p class="text-2xl font-bold text-rose-600"><?= $fmt($charges) ?></p>
      <p class="text-xs text-slate-400 mt-1">Classe 6 — dépenses</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Résultat net</span>
        <span class="w-8 h-8 <?= $resultat >= 0 ? 'bg-violet-100' : 'bg-rose-100' ?> rounded-lg flex items-center justify-center">
          <i data-lucide="calculator" class="w-4 h-4 <?= $resultat >= 0 ? 'text-violet-600' : 'text-rose-600' ?>"></i>
        </span>
      </div>
      <p class="text-2xl font-bold <?= $resultat >= 0 ? 'text-violet-700' : 'text-rose-600' ?>">
        <?= ($resultat >= 0 ? '+' : '') . $fmt(abs($resultat)) ?>
      </p>
      <p class="text-xs text-slate-400 mt-1"><?= $resultat >= 0 ? 'Bénéfice' : 'Déficit' ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="flex items-center justify-between mb-2">
        <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Écritures</span>
        <span class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
          <i data-lucide="file-text" class="w-4 h-4 text-blue-600"></i>
        </span>
      </div>
      <p class="text-2xl font-bold text-blue-700"><?= number_format((int)($stats->nb_ecritures ?? 0)) ?></p>
      <p class="text-xs text-slate-400 mt-1">Solde caisse : <?= $fmt($caisse) ?></p>
    </div>
  </div>

  <!-- Navigation rapide -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php $navItems = [
      ['/v2/finance/comptabilite/plan-comptable', 'list',       'bg-blue-50 text-blue-700 border-blue-200',    'Plan comptable', 'Comptes et classes'],
      ['/v2/finance/comptabilite/journal',         'book-open',  'bg-violet-50 text-violet-700 border-violet-200','Journal',       'Toutes les écritures'],
      ['/v2/finance/comptabilite/grand-livre',     'layers',     'bg-slate-50 text-slate-700 border-slate-200',  'Grand Livre',    'Par compte'],
      ['/v2/finance/comptabilite/balance',         'scale',      'bg-emerald-50 text-emerald-700 border-emerald-200','Balance',   'Vérification équilibre'],
    ]; ?>
    <?php foreach ($navItems as [$url, $icon, $cls, $title, $sub]): ?>
    <a href="<?= BASE_URL . $url ?>" class="bg-white border <?= $cls ?> rounded-xl p-4 hover:shadow-sm transition-shadow group">
      <i data-lucide="<?= $icon ?>" class="w-6 h-6 mb-2"></i>
      <p class="font-semibold text-sm"><?= $title ?></p>
      <p class="text-xs opacity-70 mt-0.5"><?= $sub ?></p>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Écritures récentes -->
  <?php if (!empty($recent)): ?>
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h2 class="font-semibold text-slate-700">Écritures récentes</h2>
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite/journal"
         class="inline-flex items-center gap-1 text-sm text-violet-600 hover:text-violet-800 font-medium">
        Voir tout <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
      </a>
    </div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($recent as $e): ?>
      <div class="px-5 py-3 flex items-center justify-between hover:bg-slate-50">
        <div class="flex items-center gap-3">
          <span class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded"><?= htmlspecialchars($e->journal_code) ?></span>
          <div>
            <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($e->libelle) ?></p>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($e->numero) ?> — <?= date('d/m/Y', strtotime($e->date_ecriture)) ?></p>
          </div>
        </div>
        <span class="text-sm font-bold text-slate-700"><?= $fmt((float)$e->total_debit) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <!-- Lien exercices -->
  <div class="flex items-center justify-between text-sm text-slate-500">
    <a href="<?= BASE_URL ?>/v2/finance/comptabilite/exercices" class="hover:text-violet-600">
      <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>Gérer les exercices comptables
    </a>
  </div>
</div>
