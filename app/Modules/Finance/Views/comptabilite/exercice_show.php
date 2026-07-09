<?php
$exercice = $exercice ?? null;
$periodes = $periodes ?? [];
$canGerer = $canGerer ?? false;
$user     = $user     ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$statutCls = ['ouverte'=>'bg-emerald-100 text-emerald-700','cloturee'=>'bg-slate-100 text-slate-600'];
?>
<div class="p-6 space-y-6">

  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($exercice->libelle ?? '') ?></h1>
      <p class="text-slate-500 text-sm">
        <?= date('d/m/Y', strtotime($exercice->date_debut ?? 'now')) ?>
        → <?= date('d/m/Y', strtotime($exercice->date_fin   ?? 'now')) ?>
        <span class="px-2 py-0.5 text-xs font-medium rounded-full ml-2
          <?= ['ouvert'=>'bg-emerald-100 text-emerald-700','cloture'=>'bg-slate-100 text-slate-600','reouvert'=>'bg-amber-100 text-amber-700'][$exercice->statut ?? ''] ?? '' ?>">
          <?= ['ouvert'=>'Ouvert','cloture'=>'Clôturé','reouvert'=>'Réouvert'][$exercice->statut ?? ''] ?? '' ?>
        </span>
      </p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="/v2/finance/comptabilite/journal?exercice_id=<?= $exercice->id ?? '' ?>"
         class="text-sm text-violet-600 hover:text-violet-800 font-medium">Voir le journal →</a>
      <a href="/v2/finance/comptabilite/exercices" class="text-sm text-slate-500 hover:text-slate-700">← Exercices</a>
    </div>
  </div>

  <!-- KPI exercice -->
  <?php
  $nbTotal  = (int)($exercice->nb_periodes          ?? 0);
  $nbOpen   = (int)($exercice->nb_periodes_ouvertes ?? 0);
  $nbEcr    = (int)($exercice->nb_ecritures         ?? 0);
  $produits = (float)($exercice->total_produits ?? 0);
  $charges  = (float)($exercice->total_charges  ?? 0);
  $resultat = $produits - $charges;
  ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white border border-slate-200 rounded-xl p-4">
      <p class="text-xs text-slate-400 uppercase mb-1">Périodes</p>
      <p class="text-2xl font-bold text-slate-800"><?= $nbTotal - $nbOpen ?><span class="text-slate-400 font-normal text-base">/<?= $nbTotal ?></span></p>
      <p class="text-xs text-slate-400">clôturées</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
      <p class="text-xs text-slate-400 uppercase mb-1">Écritures</p>
      <p class="text-2xl font-bold text-slate-800"><?= number_format($nbEcr) ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
      <p class="text-xs text-slate-400 uppercase mb-1">Produits</p>
      <p class="text-xl font-bold text-emerald-700"><?= $fmt($produits) ?></p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4">
      <p class="text-xs text-slate-400 uppercase mb-1">Résultat net</p>
      <p class="text-xl font-bold <?= $resultat >= 0 ? 'text-violet-700' : 'text-rose-600' ?>">
        <?= ($resultat >= 0 ? '+' : '') . $fmt(abs($resultat)) ?>
      </p>
    </div>
  </div>

  <!-- Action clôture exercice -->
  <?php if ($canGerer && in_array($exercice->statut ?? '', ['ouvert','reouvert'], true)): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <i data-lucide="lock" class="w-5 h-5 text-amber-600"></i>
      <div>
        <p class="font-medium text-amber-800">Clôture de l'exercice</p>
        <p class="text-xs text-amber-600 mt-0.5">
          <?= $nbOpen > 0
              ? "Clôturez d'abord toutes les périodes ({$nbOpen} ouverte(s)) avant de clôturer l'exercice."
              : 'Toutes les périodes sont clôturées. Vous pouvez clôturer l\'exercice.' ?>
        </p>
      </div>
    </div>
    <?php if ($nbOpen === 0): ?>
    <form method="POST" action="/v2/finance/comptabilite/exercices/<?= $exercice->id ?>/cloturer"
          onsubmit="return confirm('Clôturer définitivement l\'exercice ? Une écriture de clôture sera créée automatiquement.')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
      <button class="bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-700 whitespace-nowrap">
        Clôturer l'exercice
      </button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Liste des périodes -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
      <h2 class="font-semibold text-slate-700">Périodes comptables</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">N°</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Période</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Du</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Au</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500">Écritures</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500">Débit</th>
            <th class="px-4 py-2.5 text-right font-medium text-slate-500">Crédit</th>
            <th class="px-4 py-2.5 text-left font-medium text-slate-500">Statut</th>
            <?php if ($canGerer): ?>
            <th class="px-4 py-2.5 text-center font-medium text-slate-500">Action</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($periodes as $p): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500 font-mono">P<?= sprintf('%02d', $p->numero) ?></td>
            <td class="px-4 py-3 font-medium text-slate-800"><?= htmlspecialchars($p->libelle) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= date('d/m/Y', strtotime($p->date_debut)) ?></td>
            <td class="px-4 py-3 text-slate-600"><?= date('d/m/Y', strtotime($p->date_fin)) ?></td>
            <td class="px-4 py-3 text-right text-slate-700"><?= number_format((int)($p->nb_ecritures ?? 0)) ?></td>
            <td class="px-4 py-3 text-right text-slate-600"><?= (float)($p->total_debit ?? 0) > 0 ? number_format((float)$p->total_debit, 0, ',', ' ') : '—' ?></td>
            <td class="px-4 py-3 text-right text-slate-600"><?= (float)($p->total_credit ?? 0) > 0 ? number_format((float)$p->total_credit, 0, ',', ' ') : '—' ?></td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-medium rounded-full <?= $statutCls[$p->statut] ?? 'bg-slate-100 text-slate-600' ?>">
                <?= $p->statut === 'ouverte' ? 'Ouverte' : 'Clôturée' ?>
              </span>
            </td>
            <?php if ($canGerer): ?>
            <td class="px-4 py-3 text-center">
              <?php if ($p->statut === 'ouverte'): ?>
              <form method="POST" action="/v2/finance/comptabilite/periodes/<?= $p->id ?>/cloturer"
                    onsubmit="return confirm('Clôturer la période <?= htmlspecialchars($p->libelle) ?> ?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button class="text-xs text-amber-600 hover:text-amber-800 font-medium">Clôturer</button>
              </form>
              <?php else: ?>
              <span class="text-xs text-slate-400">—</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
