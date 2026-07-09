<?php
$parCompte = $parCompte ?? [];
$filters   = $filters   ?? null;
$exercice  = $exercice  ?? null;
$exercices = $exercices ?? [];
$comptes   = $comptes   ?? [];
$user      = $user      ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
?>
<div class="p-6 space-y-5">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Grand Livre</h1>
      <p class="text-slate-500 text-sm">
        Mouvements par compte — <?= $exercice ? htmlspecialchars($exercice->libelle) : 'Sélectionnez un exercice' ?>
        — <?= count($parCompte) ?> compte(s)
      </p>
    </div>
    <a href="/v2/finance/comptabilite" class="text-sm text-slate-500 hover:text-slate-700">← Retour</a>
  </div>

  <!-- Filtres -->
  <div class="bg-white rounded-xl border border-slate-200 p-4">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <select name="exercice_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Exercice courant</option>
        <?php foreach ($exercices as $ex): ?>
        <option value="<?= $ex->id ?>" <?= ($filters->exerciceId ?? 0) == $ex->id ? 'selected' : '' ?>>
          <?= htmlspecialchars($ex->libelle) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <select name="classe" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Toutes classes</option>
        <?php foreach ([1=>'Classe 1 — Capitaux',4=>'Classe 4 — Tiers',5=>'Classe 5 — Financier',6=>'Classe 6 — Charges',7=>'Classe 7 — Produits'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= ($filters->classe ?? '') == $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>

      <select name="type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Tous types</option>
        <option value="actif"   <?= ($filters->type ?? '') === 'actif'   ? 'selected' : '' ?>>Actif</option>
        <option value="passif"  <?= ($filters->type ?? '') === 'passif'  ? 'selected' : '' ?>>Passif</option>
        <option value="charge"  <?= ($filters->type ?? '') === 'charge'  ? 'selected' : '' ?>>Charge</option>
        <option value="produit" <?= ($filters->type ?? '') === 'produit' ? 'selected' : '' ?>>Produit</option>
      </select>

      <input type="text" name="compte_code" placeholder="Code compte (ex: 53)" value="<?= htmlspecialchars($filters->compteCode ?? '') ?>"
             class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
      <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
             class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
      <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
             class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

      <div class="flex gap-2 col-span-2 md:col-span-1">
        <button class="flex-1 bg-violet-600 text-white rounded-lg py-2 text-sm font-medium hover:bg-violet-700">Filtrer</button>
        <a href="/v2/finance/comptabilite/grand-livre" class="flex-1 bg-slate-100 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-200 text-center">Reset</a>
      </div>
    </form>
  </div>

  <?php if (empty($parCompte)): ?>
  <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
    <i data-lucide="layers" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
    <p>Aucun mouvement trouvé pour les critères sélectionnés.</p>
  </div>
  <?php else: ?>

  <!-- Grand livre par compte -->
  <?php foreach ($parCompte as $codeCompte => $lignes): ?>
  <?php
  $totalDebit  = array_sum(array_map(fn($l) => (float)$l->debit,  $lignes));
  $totalCredit = array_sum(array_map(fn($l) => (float)$l->credit, $lignes));
  $solde       = $totalDebit - $totalCredit;
  $meta        = $lignes[0] ?? null;
  ?>
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="font-mono font-bold text-slate-700"><?= htmlspecialchars($codeCompte) ?></span>
        <span class="font-medium text-slate-800"><?= htmlspecialchars($meta->compte_libelle ?? '') ?></span>
        <?php
        $typeCls = match ($meta->compte_type ?? '') {
            'actif'   => 'bg-blue-100 text-blue-700',
            'passif'  => 'bg-violet-100 text-violet-700',
            'charge'  => 'bg-rose-100 text-rose-700',
            'produit' => 'bg-emerald-100 text-emerald-700',
            default   => 'bg-slate-100 text-slate-600',
        };
        $typeLabel = ['actif'=>'Actif','passif'=>'Passif','charge'=>'Charge','produit'=>'Produit'][$meta->compte_type ?? ''] ?? '';
        ?>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium <?= $typeCls ?>"><?= $typeLabel ?></span>
      </div>
      <div class="flex gap-6 text-sm font-medium">
        <span class="text-slate-600">D: <span class="text-slate-800"><?= $fmt($totalDebit) ?></span></span>
        <span class="text-slate-600">C: <span class="text-slate-800"><?= $fmt($totalCredit) ?></span></span>
        <span class="<?= $solde >= 0 ? 'text-emerald-700' : 'text-rose-600' ?> font-bold">
          Solde: <?= ($solde >= 0 ? '+' : '') . $fmt($solde) ?>
        </span>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2 text-left font-medium text-slate-400">Date</th>
            <th class="px-4 py-2 text-left font-medium text-slate-400">Écriture</th>
            <th class="px-4 py-2 text-left font-medium text-slate-400">Journal</th>
            <th class="px-4 py-2 text-left font-medium text-slate-400">Libellé</th>
            <th class="px-4 py-2 text-left font-medium text-slate-400">Réf.</th>
            <th class="px-4 py-2 text-right font-medium text-slate-400">Débit</th>
            <th class="px-4 py-2 text-right font-medium text-slate-400">Crédit</th>
            <th class="px-4 py-2 text-right font-medium text-slate-400">Solde</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php $running = 0; ?>
          <?php foreach ($lignes as $l): ?>
          <?php
          $running += (float)$l->debit - (float)$l->credit;
          ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-2 text-slate-600"><?= date('d/m/Y', strtotime($l->date_ecriture)) ?></td>
            <td class="px-4 py-2 font-mono text-slate-700"><?= htmlspecialchars($l->ecriture_numero) ?></td>
            <td class="px-4 py-2"><span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded"><?= htmlspecialchars($l->journal_code) ?></span></td>
            <td class="px-4 py-2 text-slate-700 max-w-xs truncate"><?= htmlspecialchars($l->ligne_libelle) ?></td>
            <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($l->reference ?? '—') ?></td>
            <td class="px-4 py-2 text-right <?= (float)$l->debit > 0 ? 'text-slate-800 font-medium' : 'text-slate-300' ?>">
              <?= (float)$l->debit > 0 ? $fmt((float)$l->debit) : '—' ?>
            </td>
            <td class="px-4 py-2 text-right <?= (float)$l->credit > 0 ? 'text-slate-800 font-medium' : 'text-slate-300' ?>">
              <?= (float)$l->credit > 0 ? $fmt((float)$l->credit) : '—' ?>
            </td>
            <td class="px-4 py-2 text-right font-semibold <?= $running >= 0 ? 'text-emerald-700' : 'text-rose-600' ?>">
              <?= ($running >= 0 ? '' : '-') . $fmt(abs($running)) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="bg-slate-50 font-semibold">
            <td colspan="5" class="px-4 py-2.5 text-slate-600">Total</td>
            <td class="px-4 py-2.5 text-right text-slate-800"><?= $fmt($totalDebit) ?></td>
            <td class="px-4 py-2.5 text-right text-slate-800"><?= $fmt($totalCredit) ?></td>
            <td class="px-4 py-2.5 text-right <?= $solde >= 0 ? 'text-emerald-700' : 'text-rose-600' ?>">
              <?= ($solde >= 0 ? '+' : '') . $fmt($solde) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
