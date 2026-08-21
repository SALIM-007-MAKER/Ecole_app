<?php
$ecriture  = $ecriture  ?? null;
$lignes    = $lignes    ?? [];
$canSaisir = $canSaisir ?? false;
$user      = $user      ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$totalDebit  = array_sum(array_map(fn($l) => (float)$l->debit,  $lignes));
$totalCredit = array_sum(array_map(fn($l) => (float)$l->credit, $lignes));
$equilibre   = abs($totalDebit - $totalCredit) < 0.01;

$sourcesLabels = [
    'payment_completed'        => 'Paiement encaissé',
    'payment_refunded'         => 'Remboursement',
    'invoice_cancelled'        => 'Annulation facture',
    'cash_recette_manuel'      => 'Recette caisse (manuel)',
    'cash_decaissement_manuel' => 'Décaissement caisse (manuel)',
    'expense_validated'        => 'Dépense validée',
    'exercice_cloture'         => 'Clôture exercice',
    'extourne'                 => 'Écriture d\'extourne',
];
?>
<div class="p-6 space-y-6 max-w-4xl">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-3 mb-1">
        <h1 class="text-2xl font-bold text-slate-800 font-mono"><?= htmlspecialchars($ecriture->numero ?? '') ?></h1>
        <span class="px-2 py-0.5 text-xs font-medium rounded-full
          <?= ['brouillon'=>'bg-amber-100 text-amber-700','valide'=>'bg-emerald-100 text-emerald-700','extourne'=>'bg-slate-100 text-slate-600','cloture'=>'bg-violet-100 text-violet-700'][$ecriture->statut ?? ''] ?? 'bg-slate-100' ?>">
          <?= ['brouillon'=>'Brouillon','valide'=>'Validée','extourne'=>'Extournée','cloture'=>'Clôturée'][$ecriture->statut ?? ''] ?? '' ?>
        </span>
        <span class="font-mono bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($ecriture->journal_code ?? '') ?></span>
      </div>
      <p class="text-slate-500 text-sm"><?= htmlspecialchars($ecriture->libelle ?? '') ?></p>
    </div>
    <div class="flex gap-2">
      <a href="<?= BASE_URL ?>/v2/finance/comptabilite/journal" class="text-sm text-slate-500 hover:text-slate-700">← Journal</a>
    </div>
  </div>

  <!-- Infos -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-white rounded-xl border border-slate-200 p-5">
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Date</p>
      <p class="font-semibold text-slate-800"><?= $ecriture ? date('d/m/Y', strtotime($ecriture->date_ecriture)) : '—' ?></p>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Journal</p>
      <p class="font-semibold text-slate-800"><?= htmlspecialchars($ecriture->journal_libelle ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Exercice</p>
      <p class="font-semibold text-slate-800"><?= htmlspecialchars($ecriture->exercice_libelle ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Référence</p>
      <p class="font-semibold text-slate-800 font-mono"><?= htmlspecialchars($ecriture->reference ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Source</p>
      <p class="text-slate-700"><?= $sourcesLabels[$ecriture->source ?? ''] ?? htmlspecialchars($ecriture->source ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Période</p>
      <p class="text-slate-700"><?= htmlspecialchars($ecriture->periode_libelle ?? '—') ?></p>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Équilibre</p>
      <?php if ($equilibre): ?>
      <span class="text-emerald-700 font-medium text-sm flex items-center gap-1"><i data-lucide="check-circle" class="w-4 h-4"></i>Équilibrée</span>
      <?php else: ?>
      <span class="text-rose-600 font-medium text-sm flex items-center gap-1"><i data-lucide="alert-circle" class="w-4 h-4"></i>Déséquilibrée</span>
      <?php endif; ?>
    </div>
    <div>
      <p class="text-xs text-slate-400 uppercase mb-0.5">Créée le</p>
      <p class="text-slate-700"><?= $ecriture ? date('d/m/Y H:i', strtotime($ecriture->created_at)) : '—' ?></p>
    </div>
  </div>

  <!-- Lignes -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
      <h2 class="font-semibold text-slate-700">Lignes d'écriture</h2>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-4 py-2.5 text-left font-medium text-slate-500">Compte</th>
          <th class="px-4 py-2.5 text-left font-medium text-slate-500">Libellé</th>
          <th class="px-4 py-2.5 text-left font-medium text-slate-500">Type</th>
          <th class="px-4 py-2.5 text-right font-medium text-slate-500">Débit</th>
          <th class="px-4 py-2.5 text-right font-medium text-slate-500">Crédit</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($lignes as $l): ?>
        <?php
        $tCls = ['actif'=>'text-blue-700','passif'=>'text-violet-700','charge'=>'text-rose-700','produit'=>'text-emerald-700'][$l->compte_type ?? ''] ?? 'text-slate-600';
        ?>
        <tr class="hover:bg-slate-50">
          <td class="px-4 py-3 font-mono font-medium text-slate-700">
            <?= htmlspecialchars($l->compte_code) ?>
            <span class="text-xs text-slate-400 font-sans ml-1"><?= htmlspecialchars($l->compte_libelle) ?></span>
          </td>
          <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($l->libelle) ?></td>
          <td class="px-4 py-3 text-xs <?= $tCls ?> font-medium capitalize"><?= $l->compte_type ?? '—' ?></td>
          <td class="px-4 py-3 text-right font-semibold <?= (float)$l->debit > 0 ? 'text-slate-800' : 'text-slate-200' ?>">
            <?= (float)$l->debit > 0 ? $fmt((float)$l->debit) : '—' ?>
          </td>
          <td class="px-4 py-3 text-right font-semibold <?= (float)$l->credit > 0 ? 'text-slate-800' : 'text-slate-200' ?>">
            <?= (float)$l->credit > 0 ? $fmt((float)$l->credit) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="bg-slate-50 font-bold border-t-2 border-slate-200">
          <td colspan="3" class="px-4 py-3 text-slate-600">Totaux</td>
          <td class="px-4 py-3 text-right text-slate-800"><?= $fmt($totalDebit) ?></td>
          <td class="px-4 py-3 text-right text-slate-800"><?= $fmt($totalCredit) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Action extourne -->
  <?php if ($canSaisir && ($ecriture->statut ?? '') === 'valide'): ?>
  <div class="bg-white rounded-xl border border-slate-200 p-5">
    <h3 class="font-semibold text-slate-700 mb-3">Créer une écriture d'extourne</h3>
    <form method="POST" action="<?= BASE_URL ?>/v2/finance/comptabilite/ecritures/<?= $ecriture->id ?>/extourner"
          onsubmit="return confirm('Confirmer l\'extourne ? L\'écriture originale sera marquée comme extournée et une nouvelle écriture inverse sera créée.')">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
      <div class="flex gap-3">
        <input type="text" name="motif" placeholder="Motif d'extourne (obligatoire)…" required
               class="form-input flex-1">
        <button type="submit" class="btn btn-warning">
          Extourner
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
