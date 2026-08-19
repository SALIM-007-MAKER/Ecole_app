<?php
$lignesBalance = $lignesBalance ?? [];
$totalDebit    = $totalDebit    ?? 0.0;
$totalCredit   = $totalCredit   ?? 0.0;
$exercice      = $exercice      ?? null;
$exercices     = $exercices     ?? [];
$user          = $user          ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$equilibre = abs($totalDebit - $totalCredit) < 0.01;

// Grouper par classe pour les totaux
$parClasse = [];
foreach ($lignesBalance as $l) {
    $parClasse[$l->classe][] = $l;
}
ksort($parClasse);
?>
<div class="p-6 space-y-5">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Balance générale</h1>
      <p class="text-slate-500 text-sm"><?= $exercice ? htmlspecialchars($exercice->libelle) : 'Tous exercices' ?></p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite" class="text-sm text-slate-500 hover:text-slate-700">← Retour</a>
    </div>
  </div>

  <!-- Sélecteur exercice -->
  <form method="GET" class="flex gap-3 items-center">
    <select name="exercice_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
      <option value="">Exercice courant</option>
      <?php foreach ($exercices as $ex): ?>
      <option value="<?= $ex->id ?>" <?= ($exercice && $exercice->id == $ex->id) ? 'selected' : '' ?>>
        <?= htmlspecialchars($ex->libelle) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button class="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700">Afficher</button>
  </form>

  <!-- Indicateur équilibre -->
  <div class="flex items-center gap-3 p-4 rounded-xl border <?= $equilibre ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200' ?>">
    <i data-lucide="<?= $equilibre ? 'check-circle' : 'alert-circle' ?>"
       class="w-6 h-6 <?= $equilibre ? 'text-emerald-600' : 'text-rose-600' ?>"></i>
    <div>
      <p class="font-semibold <?= $equilibre ? 'text-emerald-800' : 'text-rose-800' ?>">
        <?= $equilibre ? 'Balance équilibrée — Débit = Crédit' : 'Balance déséquilibrée — Vérifiez les écritures' ?>
      </p>
      <p class="text-sm <?= $equilibre ? 'text-emerald-600' : 'text-rose-600' ?>">
        Total Débit : <?= $fmt($totalDebit) ?> — Total Crédit : <?= $fmt($totalCredit) ?>
        <?= !$equilibre ? ' — Écart : ' . $fmt(abs($totalDebit - $totalCredit)) : '' ?>
      </p>
    </div>
  </div>

  <?php if (empty($lignesBalance)): ?>
  <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
    <i data-lucide="scale" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p>Aucune donnée pour cet exercice.</p>
  </div>
  <?php else: ?>

  <?php foreach ($parClasse as $classe => $lignes): ?>
  <?php
  $classeTotalDebit  = array_sum(array_map(fn($l) => (float)$l->total_debit,  $lignes));
  $classeTotalCredit = array_sum(array_map(fn($l) => (float)$l->total_credit, $lignes));
  $classeSolde       = array_sum(array_map(fn($l) => (float)$l->solde, $lignes));
  $classesLabels = [1=>'Capitaux',4=>'Comptes de tiers',5=>'Comptes financiers',6=>'Charges',7=>'Produits'];
  ?>
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="w-7 h-7 bg-slate-200 text-slate-700 rounded-lg flex items-center justify-center font-bold text-xs"><?= $classe ?></span>
        <span class="font-semibold text-slate-700"><?= $classesLabels[$classe] ?? "Classe {$classe}" ?></span>
        <span class="text-xs text-slate-400"><?= count($lignes) ?> compte(s)</span>
      </div>
      <div class="text-xs text-slate-500 flex gap-4">
        <span>D: <strong class="text-slate-700"><?= $fmt($classeTotalDebit) ?></strong></span>
        <span>C: <strong class="text-slate-700"><?= $fmt($classeTotalCredit) ?></strong></span>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 w-24">Code</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Libellé</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500 w-20">Type</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500 w-36">Total Débit</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500 w-36">Total Crédit</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500 w-36">Solde</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($lignes as $l): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2.5 font-mono font-medium text-slate-700"><?= htmlspecialchars($l->code) ?></td>
            <td class="px-4 py-2.5 text-slate-800"><?= htmlspecialchars($l->libelle) ?></td>
            <td class="px-4 py-2.5">
              <?php
              $tc = ['actif'=>'bg-blue-100 text-blue-700','passif'=>'bg-violet-100 text-violet-700','charge'=>'bg-rose-100 text-rose-700','produit'=>'bg-emerald-100 text-emerald-700'][$l->type] ?? 'bg-slate-100 text-slate-600';
              $tl = ['actif'=>'A','passif'=>'P','charge'=>'C','produit'=>'Pr'][$l->type] ?? '?';
              ?>
              <span class="px-1.5 py-0.5 text-xs font-bold rounded <?= $tc ?>"><?= $tl ?></span>
            </td>
            <td class="px-4 py-2.5 text-right text-slate-600"><?= (float)$l->total_debit > 0 ? $fmt((float)$l->total_debit) : '—' ?></td>
            <td class="px-4 py-2.5 text-right text-slate-600"><?= (float)$l->total_credit > 0 ? $fmt((float)$l->total_credit) : '—' ?></td>
            <td class="px-4 py-2.5 text-right font-semibold <?= (float)$l->solde >= 0 ? 'text-emerald-700' : 'text-rose-600' ?>">
              <?= number_format(abs((float)$l->solde), 0, ',', ' ') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="bg-slate-50 font-semibold text-sm border-t border-slate-200">
            <td colspan="3" class="px-4 py-3 text-slate-600">Sous-total classe <?= $classe ?></td>
            <td class="px-4 py-3 text-right text-slate-800"><?= $fmt($classeTotalDebit) ?></td>
            <td class="px-4 py-3 text-right text-slate-800"><?= $fmt($classeTotalCredit) ?></td>
            <td class="px-4 py-3 text-right <?= $classeSolde >= 0 ? 'text-emerald-700' : 'text-rose-600' ?>">
              <?= $fmt(abs($classeSolde)) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Total général -->
  <div class="bg-slate-800 text-white rounded-xl p-5 flex items-center justify-between">
    <div>
      <p class="font-bold text-lg">TOTAL GÉNÉRAL</p>
      <p class="text-slate-400 text-sm"><?= count($lignesBalance) ?> compte(s) mouvementés</p>
    </div>
    <div class="flex gap-8 text-right">
      <div>
        <p class="text-xs text-slate-400 uppercase">Total Débit</p>
        <p class="text-xl font-bold"><?= $fmt($totalDebit) ?></p>
      </div>
      <div>
        <p class="text-xs text-slate-400 uppercase">Total Crédit</p>
        <p class="text-xl font-bold"><?= $fmt($totalCredit) ?></p>
      </div>
      <div>
        <p class="text-xs uppercase <?= $equilibre ? 'text-emerald-400' : 'text-rose-400' ?>">Écart</p>
        <p class="text-xl font-bold <?= $equilibre ? 'text-emerald-400' : 'text-rose-400' ?>">
          <?= $fmt(abs($totalDebit - $totalCredit)) ?>
        </p>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
