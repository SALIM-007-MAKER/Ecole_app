<?php
/**
 * Finance V2 — Espace parent/élève : scolarité et paiements de l'enfant.
 * Remplace l'ancien /parent/paiements (V1).
 */
$enfants   = $enfants   ?? [];
$eleveId   = $eleveId   ?? 0;
$factures  = $factures  ?? [];
$paiements = $paiements ?? [];
$totaux    = $totaux    ?? ['total_facture' => 0.0, 'total_paye' => 0.0, 'total_reste' => 0.0];
$annee     = $annee     ?? '';
$annees    = $annees    ?? [];
$isEleve   = $isEleve   ?? false;

$e = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$statutBadge = function (string $s): array {
    return match ($s) {
        'brouillon'           => ['Brouillon', 'bg-slate-100 text-slate-500'],
        'emise'               => ['Émise', 'bg-blue-100 text-blue-700'],
        'partiellement_payee' => ['Partiel', 'bg-amber-100 text-amber-700'],
        'payee'               => ['Soldée', 'bg-emerald-100 text-emerald-700'],
        'en_retard'           => ['En retard', 'bg-red-100 text-red-700'],
        'annulee'             => ['Annulée', 'bg-rose-100 text-rose-500'],
        'archive'             => ['Archivée', 'bg-slate-100 text-slate-400'],
        default               => [$s, 'bg-slate-100 text-slate-500'],
    };
};

$enfantCourant = null;
foreach ($enfants as $en) {
    if ((int)$en->id === (int)$eleveId) { $enfantCourant = $en; break; }
}
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                <i data-lucide="wallet" class="w-5 h-5 text-emerald-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Scolarité et paiements</h1>
                <?php if ($enfantCourant): ?>
                <p class="text-sm text-slate-500 mt-0.5"><?= $e($enfantCourant->prenom . ' ' . $enfantCourant->nom) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <?php if (!$isEleve && count($enfants) > 1): ?>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Enfant</label>
                <select name="eleve_id" onchange="this.form.submit()"
                        class="form-select">
                    <?php foreach ($enfants as $en): ?>
                    <option value="<?= (int)$en->id ?>" <?= (int)$en->id === (int)$eleveId ? 'selected' : '' ?>>
                        <?= $e($en->prenom . ' ' . $en->nom) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
                <select name="annee_scolaire" onchange="this.form.submit()"
                        class="form-select">
                    <?php foreach ($annees as $a): ?>
                    <option value="<?= $e($a) ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $e($a) ?></option>
                    <?php endforeach; ?>
                    <?php if (!in_array($annee, $annees, true)): ?>
                    <option value="<?= $e($annee) ?>" selected><?= $e($annee) ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (!$eleveId): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-10 text-center text-slate-400">
        <i data-lucide="user" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
        Aucun enfant associé à ce compte.
    </div>
    <?php else: ?>

    <!-- Résumé -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
            <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="file-text" class="w-5 h-5 text-violet-600"></i>
            </div>
            <p class="text-2xl font-bold text-slate-800"><?= $fmt($totaux['total_facture']) ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Total facturé</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
            </div>
            <p class="text-2xl font-bold text-slate-800"><?= $fmt($totaux['total_paye']) ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Total payé</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
            <div class="w-10 h-10 rounded-xl <?= $totaux['total_reste'] > 0 ? 'bg-amber-100' : 'bg-emerald-100' ?> flex items-center justify-center mx-auto mb-3">
                <i data-lucide="alert-circle" class="w-5 h-5 <?= $totaux['total_reste'] > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"></i>
            </div>
            <p class="text-2xl font-bold <?= $totaux['total_reste'] > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $fmt($totaux['total_reste']) ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Reste à payer</p>
        </div>
    </div>

    <!-- Factures -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50">
            <span class="text-sm font-semibold text-slate-700">Factures — <?= $e($annee) ?></span>
        </div>
        <?php if (empty($factures)): ?>
        <div class="p-8 text-center text-slate-400 text-sm">Aucune facture pour cette année.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">N° facture</th>
                        <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Émission</th>
                        <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Montant</th>
                        <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Payé</th>
                        <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Reste</th>
                        <th class="text-center px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($factures as $f):
                    [$label, $cls] = $statutBadge($f->statut);
                    $reste = max(0.0, (float)$f->montant_total - (float)$f->montant_paye);
                ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs text-violet-700"><?= $e($f->numero) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= $f->date_emission ? date('d/m/Y', strtotime($f->date_emission)) : '—' ?></td>
                        <td class="px-4 py-3 text-right"><?= $fmt((float)$f->montant_total) ?></td>
                        <td class="px-4 py-3 text-right text-emerald-600"><?= $fmt((float)$f->montant_paye) ?></td>
                        <td class="px-4 py-3 text-right font-semibold <?= $reste > 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= $fmt($reste) ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= $e($label) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Historique des paiements -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
            <span class="text-sm font-semibold text-slate-700">Historique des paiements</span>
            <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?= count($paiements) ?></span>
        </div>
        <?php if (empty($paiements)): ?>
        <div class="p-8 text-center text-slate-400 text-sm">Aucun paiement enregistré.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Date</th>
                        <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Facture</th>
                        <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Mode</th>
                        <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Montant</th>
                        <th class="text-center px-4 py-2.5 font-medium text-slate-500 text-xs uppercase">Reçu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($paiements as $p): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600"><?= $p->date_paiement ? date('d/m/Y', strtotime($p->date_paiement)) : '—' ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-violet-700"><?= $e($p->facture_numero) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?= $e($p->mode_nom ?? $p->mode_code) ?></span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-emerald-600"><?= $fmt((float)$p->montant_applique) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if (!empty($p->recu_id)): ?>
                            <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= (int)$p->id ?>/recu" class="text-violet-600 hover:text-violet-800" title="Voir le reçu">
                                <i data-lucide="receipt" class="w-4 h-4 inline"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-slate-300">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
