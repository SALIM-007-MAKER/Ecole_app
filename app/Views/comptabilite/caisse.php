<?php
$date          = $date          ?? date('Y-m-d');
$paiements     = $paiements     ?? [];
$depenses      = $depenses      ?? [];
$totalRecettes = $totalRecettes ?? 0;
$totalDepenses = $totalDepenses ?? 0;
$solde         = $solde         ?? 0;
$modes         = $modes         ?? [];

function fmtC(float $n): string {
    return number_format($n, 2, ',', ' ') . ' FCFA';
}
$csrfToken   = \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canEdit     = in_array('comptabilite.edit', $currentUser['permissions'] ?? [], true);

$yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
$tomorrow  = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday   = $date === date('Y-m-d');
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="vault" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-800">Caisse journalière</h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= date('l d F Y', strtotime($date)) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/comptabilite/caisse?date=<?= $yesterday ?>"
           class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
        <form method="GET">
            <input type="date" name="date" class="form-input py-1.5 text-sm"
                   value="<?= $date ?>" onchange="this.form.submit()">
        </form>
        <?php if (!$isToday): ?>
        <a href="<?= BASE_URL ?>/comptabilite/caisse?date=<?= date('Y-m-d') ?>"
           class="btn btn-outline py-1.5 px-3 text-sm">Aujourd'hui</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/comptabilite/caisse?date=<?= $tomorrow ?>"
           class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
        <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-outline">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<!-- Résumé KPI -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-center">
        <p class="text-xs text-slate-500 mb-1">Recettes du jour</p>
        <p class="text-2xl font-bold text-emerald-600"><?= fmtC((float)$totalRecettes) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= count($paiements) ?> paiement(s)</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-center">
        <p class="text-xs text-slate-500 mb-1">Dépenses du jour</p>
        <p class="text-2xl font-bold text-red-600"><?= fmtC((float)$totalDepenses) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= count($depenses) ?> dépense(s)</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 text-center">
        <p class="text-xs text-slate-500 mb-1">Solde net</p>
        <p class="text-2xl font-bold <?= $solde >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
            <?= ($solde >= 0 ? '+' : '') . fmtC((float)$solde) ?>
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Paiements / Recettes -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <span class="font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="trending-up" class="w-4 h-4 text-emerald-500"></i>Recettes
            </span>
            <?php if (in_array('comptabilite.create', $currentUser['permissions'] ?? [], true)): ?>
            <a href="<?= BASE_URL ?>/paiements/create" class="p-1.5 rounded-lg border border-emerald-200 hover:bg-emerald-50 text-emerald-600" title="Nouveau paiement">
                <i data-lucide="plus" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php if (empty($paiements)): ?>
        <div class="px-5 py-10 text-center text-slate-400">
            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm">Aucun paiement ce jour</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-600">Élève</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-600">Frais</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-600">Mode</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-600">Montant</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($paiements as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5">
                        <p class="font-semibold text-slate-800 text-xs"><?= htmlspecialchars($p->eleve_nom ?? '', ENT_QUOTES) ?></p>
                        <p class="text-slate-400" style="font-size:.7rem"><?= htmlspecialchars($p->classe_niveau . ' ' . $p->classe_nom, ENT_QUOTES) ?></p>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-500"><?= htmlspecialchars($p->frais_nom ?? 'Divers', ENT_QUOTES) ?></td>
                    <td class="px-4 py-2.5 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                            <?= ucfirst($p->mode_paiement) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right font-bold text-emerald-600 text-xs"><?= fmtC((float)$p->montant) ?></td>
                    <td class="px-4 py-2.5">
                        <a href="<?= BASE_URL ?>/paiements/<?= $p->id ?>/recu" target="_blank"
                           class="p-1 rounded border border-slate-200 hover:bg-slate-50 text-slate-500" title="Reçu">
                            <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold text-sm">
                    <tr>
                        <td colspan="3" class="px-4 py-2.5 text-right text-slate-600 text-xs">Total :</td>
                        <td class="px-4 py-2.5 text-right text-emerald-600 text-xs"><?= fmtC((float)$totalRecettes) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Dépenses -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <span class="font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="trending-down" class="w-4 h-4 text-red-500"></i>Dépenses
            </span>
            <?php if (in_array('comptabilite.create', $currentUser['permissions'] ?? [], true)): ?>
            <a href="<?= BASE_URL ?>/depenses/create" class="p-1.5 rounded-lg border border-red-200 hover:bg-red-50 text-red-500" title="Nouvelle dépense">
                <i data-lucide="plus" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php if (empty($depenses)): ?>
        <div class="px-5 py-10 text-center text-slate-400">
            <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm">Aucune dépense ce jour</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-600">Libellé</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-600">Catégorie</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-600">Mode</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-600">Montant</th>
                        <?php if ($canEdit): ?><th class="px-4 py-2.5"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach ($depenses as $d): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5 font-semibold text-slate-800 text-xs"><?= htmlspecialchars($d->libelle, ENT_QUOTES) ?></td>
                    <td class="px-4 py-2.5 text-xs text-slate-500">
                        <span style="color:<?= $d->categorie_couleur ?>">●</span>
                        <?= htmlspecialchars($d->categorie_nom ?? 'Divers', ENT_QUOTES) ?>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                            <?= ucfirst($d->mode_paiement) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right font-bold text-red-600 text-xs"><?= fmtC((float)$d->montant) ?></td>
                    <?php if ($canEdit): ?>
                    <td class="px-4 py-2.5">
                        <form method="POST" action="<?= BASE_URL ?>/depenses/<?= $d->id ?>/delete"
                              onsubmit="return confirm('Supprimer ?')">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <button class="p-1 rounded border border-red-200 hover:bg-red-50 text-red-500" title="Supprimer">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold text-sm">
                    <tr>
                        <td colspan="3" class="px-4 py-2.5 text-right text-slate-600 text-xs">Total :</td>
                        <td class="px-4 py-2.5 text-right text-red-600 text-xs"><?= fmtC((float)$totalDepenses) ?></td>
                        <?php if ($canEdit): ?><td></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
