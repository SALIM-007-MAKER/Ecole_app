<?php
/**
 * Finance V2 — Paiements d'une facture
 */
$facture   = $facture   ?? null;
$paiements = $paiements ?? [];
$statuts   = $statuts   ?? [];

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$badgeStatut = function(string $s): string {
    return match ($s) {
        'complete'  => 'bg-emerald-100 text-emerald-700',
        'valide'    => 'bg-purple-100 text-purple-700',
        'initie'    => 'bg-slate-100 text-slate-600',
        'annule'    => 'bg-rose-100 text-rose-600',
        'rembourse' => 'bg-amber-100 text-amber-700',
        default     => 'bg-slate-100 text-slate-600',
    };
};
?>
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>"
           class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Paiements</h1>
            <p class="text-sm text-slate-500">Facture <?= htmlspecialchars($facture->numero) ?> — <?= htmlspecialchars($facture->eleve_nom) ?></p>
        </div>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/finance/paiements/create?facture_id=<?= $facture->id ?>"
           class="ml-auto px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
            <i data-lucide="plus" class="inline w-4 h-4 mr-1"></i> Payer
        </a>
        <?php endif; ?>
    </div>

    <!-- Recap facture -->
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div class="text-center">
                <div class="text-xs text-slate-400">Total facture</div>
                <div class="font-bold text-slate-800"><?= $fmtMontant((float)$facture->montant_total) ?></div>
            </div>
            <div class="text-center">
                <div class="text-xs text-slate-400">Payé</div>
                <div class="font-bold text-emerald-700"><?= $fmtMontant((float)$facture->montant_paye) ?></div>
            </div>
            <div class="text-center">
                <div class="text-xs text-slate-400">Restant</div>
                <div class="font-bold text-rose-600"><?= $fmtMontant(max(0, (float)$facture->montant_total - (float)$facture->montant_paye)) ?></div>
            </div>
        </div>
    </div>

    <!-- Liste paiements -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Numéro</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Mode</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600">Montant appliqué</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Reçu</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($paiements)): ?>
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-7 h-7 mx-auto mb-2 opacity-40"></i>
                        <p class="text-sm">Aucun paiement enregistré</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($paiements as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs font-semibold text-violet-700"><?= htmlspecialchars($p->numero) ?></td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= ($p->mode_icone ? htmlspecialchars($p->mode_icone) . ' ' : '') . htmlspecialchars($p->mode_nom ?? $p->mode_code ?? '—') ?>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800"><?= $fmtMontant((float)$p->montant_applique) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= $p->date_paiement ? date('d/m/Y', strtotime($p->date_paiement)) : '—' ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeStatut($p->statut) ?>">
                            <?= $statuts[$p->statut] ?? $p->statut ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($p->recu_numero): ?>
                        <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $p->id ?>/recu"
                           class="text-xs text-violet-600 hover:underline font-mono"><?= htmlspecialchars($p->recu_numero) ?></a>
                        <?php else: ?>
                        <span class="text-xs text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $p->id ?>"
                           class="p-1.5 text-slate-400 hover:text-violet-600 rounded">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
