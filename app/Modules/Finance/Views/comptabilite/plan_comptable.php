<?php
$comptes  = $comptes  ?? [];
$exercice = $exercice ?? null;
$user     = $user     ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ');

// Grouper par classe puis par type
$parClasse = [];
foreach ($comptes as $c) {
    $parClasse[$c->classe][] = $c;
}
ksort($parClasse);

$classesLabels = [
    1 => ['Capitaux', 'bg-violet-100 text-violet-800'],
    4 => ['Comptes de tiers', 'bg-blue-100 text-blue-800'],
    5 => ['Comptes financiers', 'bg-cyan-100 text-cyan-800'],
    6 => ['Comptes de charges', 'bg-rose-100 text-rose-800'],
    7 => ['Comptes de produits', 'bg-emerald-100 text-emerald-800'],
];
?>
<div class="p-6 space-y-6">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Plan comptable</h1>
      <p class="text-slate-500 text-sm">PCG adapté — établissements scolaires
        <?= $exercice ? '— ' . htmlspecialchars($exercice->libelle) : '' ?>
      </p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite" class="text-sm text-slate-500 hover:text-slate-700">← Retour</a>
    </div>
  </div>

  <!-- Résumé par type -->
  <?php if ($exercice): ?>
  <?php
  $totaux = ['actif' => 0, 'passif' => 0, 'charge' => 0, 'produit' => 0];
  foreach ($comptes as $c) {
      if (isset($c->solde)) {
          $totaux[$c->type] = ($totaux[$c->type] ?? 0) + (float)$c->solde;
      }
  }
  ?>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
      <p class="text-xs font-medium text-blue-600 uppercase">Actif net</p>
      <p class="text-xl font-bold text-blue-800"><?= $fmt($totaux['actif']) ?></p>
    </div>
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
      <p class="text-xs font-medium text-violet-600 uppercase">Passif net</p>
      <p class="text-xl font-bold text-violet-800"><?= $fmt(abs($totaux['passif'])) ?></p>
    </div>
    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
      <p class="text-xs font-medium text-rose-600 uppercase">Charges nettes</p>
      <p class="text-xl font-bold text-rose-800"><?= $fmt($totaux['charge']) ?></p>
    </div>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
      <p class="text-xs font-medium text-emerald-600 uppercase">Produits nets</p>
      <p class="text-xl font-bold text-emerald-800"><?= $fmt($totaux['produit']) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Comptes par classe -->
  <?php foreach ($parClasse as $classe => $lignes): ?>
  <?php [$classeLabel, $classeCls] = $classesLabels[$classe] ?? ["Classe {$classe}", 'bg-slate-100 text-slate-700']; ?>
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-3">
      <span class="w-8 h-8 <?= $classeCls ?> rounded-lg flex items-center justify-center font-bold text-sm"><?= $classe ?></span>
      <h2 class="font-semibold text-slate-700"><?= $classeLabel ?></h2>
      <span class="text-xs text-slate-400 ml-auto"><?= count($lignes) ?> compte(s)</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Code</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Libellé</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Type</th>
            <?php if ($exercice): ?>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500">Débit cumulé</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500">Crédit cumulé</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500">Solde</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($lignes as $c): ?>
          <tr class="hover:bg-slate-50 <?= !$c->actif ? 'opacity-50' : '' ?>">
            <td class="px-4 py-2.5 font-mono text-slate-700 font-medium"><?= htmlspecialchars($c->code) ?></td>
            <td class="px-4 py-2.5 text-slate-800">
              <?= htmlspecialchars($c->libelle) ?>
              <?php if ($c->systeme ?? false): ?>
                <span class="ml-1 text-xs text-slate-400">(système)</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-2.5">
              <?php
              $typeCls = match ($c->type) {
                  'actif'   => 'bg-blue-100 text-blue-700',
                  'passif'  => 'bg-violet-100 text-violet-700',
                  'charge'  => 'bg-rose-100 text-rose-700',
                  'produit' => 'bg-emerald-100 text-emerald-700',
                  default   => 'bg-slate-100 text-slate-700',
              };
              $typeLabel = ['actif'=>'Actif','passif'=>'Passif','charge'=>'Charge','produit'=>'Produit'][$c->type] ?? $c->type;
              ?>
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $typeCls ?>"><?= $typeLabel ?></span>
            </td>
            <?php if ($exercice): ?>
            <?php
            $td = (float)($c->total_debit ?? 0);
            $tc = (float)($c->total_credit ?? 0);
            $sl = (float)($c->solde ?? 0);
            ?>
            <td class="px-4 py-2.5 text-right text-slate-600"><?= $td > 0 ? $fmt($td) : '—' ?></td>
            <td class="px-4 py-2.5 text-right text-slate-600"><?= $tc > 0 ? $fmt($tc) : '—' ?></td>
            <td class="px-4 py-2.5 text-right font-semibold <?= $sl >= 0 ? 'text-emerald-700' : 'text-rose-600' ?>">
              <?= $sl != 0 ? ($sl > 0 ? '+' : '') . $fmt($sl) : '—' ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>

</div>
