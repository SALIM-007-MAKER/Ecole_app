<?php
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
$filters    = $filters    ?? null;
$exercice   = $exercice   ?? null;
$exercices  = $exercices  ?? [];
$journaux   = $journaux   ?? [];
$periodes   = $periodes   ?? [];
$canSaisir  = $canSaisir  ?? false;
$user       = $user       ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$statutColors = [
    'brouillon' => 'bg-amber-100 text-amber-700',
    'valide'    => 'bg-emerald-100 text-emerald-700',
    'extourne'  => 'bg-slate-100 text-slate-600',
    'cloture'   => 'bg-violet-100 text-violet-700',
];
$sourcesLabels = [
    'payment_completed'        => 'Paiement',
    'payment_refunded'         => 'Remboursement',
    'invoice_cancelled'        => 'Annul. facture',
    'cash_recette_manuel'      => 'Recette caisse',
    'cash_decaissement_manuel' => 'Décaissement',
    'expense_validated'        => 'Dépense',
    'exercice_cloture'         => 'Clôture exercice',
    'extourne'                 => 'Extourne',
];
?>
<div class="p-6 space-y-5">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Journal comptable</h1>
      <p class="text-slate-500 text-sm"><?= $pagination['total'] ?> écriture(s) — <?= $exercice ? htmlspecialchars($exercice->libelle) : 'Tous exercices' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/v2/finance/comptabilite" class="text-sm text-slate-500 hover:text-slate-700">← Retour</a>
  </div>

  <!-- Filtres -->
  <div class="bg-white rounded-xl border border-slate-200 p-4">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
      <input type="text" name="q" placeholder="Numéro, libellé, réf…" value="<?= htmlspecialchars($filters->q ?? '') ?>"
             class="form-input col-span-2">

      <select name="exercice_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Tous les exercices</option>
        <?php foreach ($exercices as $ex): ?>
        <option value="<?= $ex->id ?>" <?= ($filters->exerciceId ?? 0) == $ex->id ? 'selected' : '' ?>>
          <?= htmlspecialchars($ex->libelle) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <select name="periode_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Toutes périodes</option>
        <?php foreach ($periodes as $p): ?>
        <option value="<?= $p->id ?>" <?= ($filters->periodeId ?? 0) == $p->id ? 'selected' : '' ?>>
          <?= htmlspecialchars($p->libelle) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <select name="journal_code" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Tous journaux</option>
        <?php foreach ($journaux as $j): ?>
        <option value="<?= $j->code ?>" <?= ($filters->journalCode ?? '') === $j->code ? 'selected' : '' ?>>
          <?= $j->code ?> — <?= htmlspecialchars($j->libelle) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <select name="statut" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">Tous statuts</option>
        <option value="valide"    <?= ($filters->statut ?? '') === 'valide'    ? 'selected' : '' ?>>Validées</option>
        <option value="brouillon" <?= ($filters->statut ?? '') === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
        <option value="extourne"  <?= ($filters->statut ?? '') === 'extourne'  ? 'selected' : '' ?>>Extournées</option>
        <option value="cloture"   <?= ($filters->statut ?? '') === 'cloture'   ? 'selected' : '' ?>>Clôturées</option>
      </select>

      <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
             class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
      <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
             class="rounded-lg border border-slate-200 px-3 py-2 text-sm">

      <div class="flex gap-2">
        <button class="flex-1 bg-violet-600 text-white rounded-lg py-2 text-sm font-medium hover:bg-violet-700">Filtrer</button>
        <a href="<?= BASE_URL ?>/v2/finance/comptabilite/journal" class="flex-1 bg-slate-100 text-slate-600 rounded-lg py-2 text-sm font-medium hover:bg-slate-200 text-center">Reset</a>
      </div>
    </form>
  </div>

  <!-- Tableau -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Date</th>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Numéro</th>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Journal</th>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Libellé</th>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Réf.</th>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Source</th>
            <th class="px-4 py-3 text-right font-medium text-slate-500">Débit</th>
            <th class="px-4 py-3 text-right font-medium text-slate-500">Crédit</th>
            <th class="px-4 py-3 text-left font-medium text-slate-500">Statut</th>
            <th class="px-4 py-3 text-center font-medium text-slate-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php if (empty($pagination['items'])): ?>
          <tr><td colspan="10" class="px-4 py-10 text-center text-slate-400">Aucune écriture trouvée.</td></tr>
          <?php else: ?>
          <?php foreach ($pagination['items'] as $e): ?>
          <tr class="hover:bg-slate-50 <?= $e->statut === 'extourne' ? 'opacity-60' : '' ?>">
            <td class="px-4 py-3 text-slate-600 whitespace-nowrap"><?= date('d/m/Y', strtotime($e->date_ecriture)) ?></td>
            <td class="px-4 py-3 font-mono text-slate-700 text-xs whitespace-nowrap"><?= htmlspecialchars($e->numero) ?></td>
            <td class="px-4 py-3">
              <span class="font-mono bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-xs"><?= htmlspecialchars($e->journal_code) ?></span>
            </td>
            <td class="px-4 py-3 text-slate-800 max-w-xs truncate"><?= htmlspecialchars($e->libelle) ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs font-mono"><?= htmlspecialchars($e->reference ?? '—') ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs"><?= $sourcesLabels[$e->source ?? ''] ?? ($e->source ?? '—') ?></td>
            <td class="px-4 py-3 text-right font-medium text-slate-700"><?= $fmt((float)$e->total_debit) ?></td>
            <td class="px-4 py-3 text-right font-medium text-slate-700"><?= $fmt((float)$e->total_credit) ?></td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $statutColors[$e->statut] ?? 'bg-slate-100 text-slate-600' ?>">
                <?= ['brouillon'=>'Brouillon','valide'=>'Validée','extourne'=>'Extournée','cloture'=>'Clôturée'][$e->statut] ?? $e->statut ?>
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <a href="<?= BASE_URL ?>/v2/finance/comptabilite/ecritures/<?= $e->id ?>" class="text-violet-600 hover:text-violet-800 text-xs font-medium">Détail</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
    <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
      <span class="text-xs text-slate-500">
        Page <?= $pagination['page'] ?> / <?= $pagination['total_pages'] ?> — <?= $pagination['total'] ?> écriture(s)
      </span>
      <div class="flex gap-1">
        <?php if ($pagination['page'] > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] - 1])) ?>"
           class="px-3 py-1.5 text-xs bg-slate-100 text-slate-600 rounded hover:bg-slate-200">‹ Préc.</a>
        <?php endif; ?>
        <?php if ($pagination['page'] < $pagination['total_pages']): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['page'] + 1])) ?>"
           class="px-3 py-1.5 text-xs bg-slate-100 text-slate-600 rounded hover:bg-slate-200">Suiv. ›</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
