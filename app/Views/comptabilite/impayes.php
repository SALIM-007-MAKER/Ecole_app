<?php
$impayes       = $impayes       ?? [];
$totalReste    = $totalReste    ?? 0;
$filters       = $filters       ?? [];
$classes       = $classes       ?? [];
$fraisTypes    = $fraisTypes    ?? [];
$anneesOptions = $anneesOptions ?? [];

function fmtFCFA(float $n): string {
    return number_format($n, 2, ',', ' ') . ' FCFA';
}
function statutBadge(string $s): string {
    return match($s) {
        'partiel'    => '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700">Partiel</span>',
        'en_attente' => '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-red-100 text-red-700">Non payé</span>',
        default      => '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600">' . htmlspecialchars($s, ENT_QUOTES) . '</span>',
    };
}
$csrfToken = \Core\Session::getCsrfToken();
?>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
        </div>
        <div>
        <h2 class="text-xl font-bold text-slate-800">Impayés</h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= count($impayes) ?> frais en attente — Reste dû :
            <strong class="text-red-600"><?= fmtFCFA((float)$totalReste) ?></strong>
        </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/comptabilite/frais/affecter" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Affecter des frais
        </a>
        <a href="<?= BASE_URL ?>/comptabilite" class="btn btn-outline">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Dashboard
        </a>
    </div>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6">
    <form method="GET" action="<?= BASE_URL ?>/comptabilite/impayes" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-36">
            <label class="form-label">Année</label>
            <select name="annee" class="form-select">
                <?php foreach ($anneesOptions as $a): ?>
                <option value="<?= $a ?>" <?= ($filters['annee'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-44">
            <label class="form-label">Classe</label>
            <select name="classe_id" class="form-select">
                <option value="">— Toutes les classes —</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl->id ?>" <?= ($filters['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1 min-w-44">
            <label class="form-label">Type de frais</label>
            <select name="frais_type_id" class="form-select">
                <option value="">— Tous les frais —</option>
                <?php foreach ($fraisTypes as $ft): ?>
                <option value="<?= $ft->id ?>" <?= ($filters['frais_type_id'] ?? '') == $ft->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ft->nom, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-primary">
                <i data-lucide="search" class="w-4 h-4"></i>Filtrer
            </button>
            <a href="<?= BASE_URL ?>/comptabilite/impayes" class="btn btn-outline">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </div>
    </form>
</div>

<!-- Tableau -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-800 text-slate-100">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Élève</th>
                    <th class="px-4 py-3 text-left font-semibold">Classe</th>
                    <th class="px-4 py-3 text-left font-semibold">Frais</th>
                    <th class="px-4 py-3 text-right font-semibold">Total</th>
                    <th class="px-4 py-3 text-right font-semibold">Payé</th>
                    <th class="px-4 py-3 text-right font-semibold text-red-300">Reste dû</th>
                    <th class="px-4 py-3 font-semibold">Progression</th>
                    <th class="px-4 py-3 text-center font-semibold">Statut</th>
                    <th class="px-4 py-3 font-semibold">Échéance</th>
                    <th class="px-4 py-3 text-right font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (empty($impayes)): ?>
            <tr>
                <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                    <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-3 text-emerald-400"></i>
                    <p>Aucun impayé pour cette période</p>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($impayes as $imp): ?>
            <?php
                $paye    = (float)$imp->montant_paye;
                $montant = (float)$imp->montant;
                $reste   = (float)$imp->reste;
                $pct     = $montant > 0 ? min(100, round($paye / $montant * 100)) : 0;
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($imp->eleve_nom, ENT_QUOTES) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($imp->matricule ?? '', ENT_QUOTES) ?></p>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                    <?= htmlspecialchars($imp->classe_niveau . ' ' . $imp->classe_nom, ENT_QUOTES) ?>
                </td>
                <td class="px-4 py-3 text-slate-700"><?= htmlspecialchars($imp->frais_nom, ENT_QUOTES) ?></td>
                <td class="px-4 py-3 text-right text-slate-700"><?= fmtFCFA($montant) ?></td>
                <td class="px-4 py-3 text-right text-emerald-600"><?= fmtFCFA($paye) ?></td>
                <td class="px-4 py-3 text-right font-bold text-red-600"><?= fmtFCFA($reste) ?></td>
                <td class="px-4 py-3" style="min-width:100px">
                    <div class="w-full bg-slate-200 rounded-full h-1.5">
                        <div class="<?= $pct > 50 ? 'bg-amber-400' : 'bg-red-500' ?> h-1.5 rounded-full"
                             style="width:<?= $pct ?>%"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5"><?= $pct ?>%</p>
                </td>
                <td class="px-4 py-3 text-center"><?= statutBadge($imp->statut) ?></td>
                <td class="px-4 py-3 text-xs text-slate-500">
                    <?php if ($imp->echeance): ?>
                    <?php $isLate = strtotime($imp->echeance) < time(); ?>
                    <span class="<?= $isLate ? 'text-red-600 font-semibold' : '' ?>">
                        <?= date('d/m/Y', strtotime($imp->echeance)) ?>
                        <?php if ($isLate): ?>
                        <i data-lucide="alert-circle" class="w-3 h-3 inline"></i>
                        <?php endif; ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="<?= BASE_URL ?>/paiements/create?eleve_id=<?= $imp->eleve_id ?>&annee=<?= urlencode($imp->annee_scolaire) ?>"
                       class="btn btn-primary py-1.5 px-3 text-xs">
                        <i data-lucide="banknote" class="w-3.5 h-3.5"></i>Payer
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($impayes)): ?>
            <tfoot class="bg-slate-50 border-t border-slate-200 font-semibold text-sm">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-right text-slate-600">Total restant dû :</td>
                    <td class="px-4 py-3 text-right text-red-600"><?= fmtFCFA((float)$totalReste) ?></td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
