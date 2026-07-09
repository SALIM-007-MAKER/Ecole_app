<?php
/**
 * Finance V2 — Liste des factures
 */
$result  = $result  ?? ['data' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1];
$filters = $filters ?? null;
$stats   = $stats   ?? null;
$statuts = $statuts ?? [];

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Factures</h1>
            <p class="text-sm text-slate-500 mt-1"><?= $result['total'] ?> facture(s) trouvée(s)</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($canAdmin): ?>
            <a href="<?= BASE_URL ?>/v2/finance/factures/generer"
               class="px-4 py-2 text-sm font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100">
                <i data-lucide="zap" class="inline w-4 h-4 mr-1"></i> Génération masse
            </a>
            <?php endif; ?>
            <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/v2/finance/factures/create"
               class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                <i data-lucide="plus" class="inline w-4 h-4 mr-1"></i> Nouvelle facture
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('warning')): ?>
    <div class="flex items-center gap-3 p-4 bg-amber-50 text-amber-800 rounded-xl border border-amber-200">
        <i data-lucide="alert-triangle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Stats cards -->
    <?php if ($stats): ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total facturé</div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= $fmtMontant((float)$stats->montant_total_global) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Encaissé</div>
            <div class="text-xl font-bold text-emerald-600 mt-1"><?= $fmtMontant((float)$stats->montant_paye_global) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">En retard</div>
            <div class="text-xl font-bold text-red-600 mt-1"><?= $stats->en_retard ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Brouillons</div>
            <div class="text-xl font-bold text-slate-500 mt-1"><?= $stats->brouillons ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <form method="GET" action="<?= BASE_URL ?>/v2/finance/factures" class="flex flex-wrap gap-3">
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q ?? '') ?>"
                   placeholder="N° facture ou élève..."
                   class="flex-1 min-w-48 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">

            <select name="statut" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($filters->statut ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <select name="annee_scolaire" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Toutes les années</option>
                <?php foreach ($annees as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= ($filters->anneeScolaire ?? '') === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="classe_id" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl->id ?>" <?= ($filters->classeId ?? '') == $cl->id ? 'selected' : '' ?>><?= htmlspecialchars($cl->nom) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-700 rounded-lg hover:bg-slate-800">
                <i data-lucide="search" class="inline w-4 h-4"></i>
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/factures" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700">
                Réinitialiser
            </a>
        </form>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <?php if (empty($result['data'])): ?>
        <div class="py-16 text-center text-slate-400">
            <i data-lucide="file-text" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
            <p>Aucune facture trouvée.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">N° Facture</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Élève / Classe</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Émission</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Échéance</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Payé</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Statut</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($result['data'] as $f): ?>
                    <?php
                    $badge = match ($f->statut) {
                        'brouillon'           => 'bg-slate-100 text-slate-600',
                        'emise'               => 'bg-blue-100 text-blue-700',
                        'partiellement_payee' => 'bg-amber-100 text-amber-700',
                        'payee'               => 'bg-emerald-100 text-emerald-700',
                        'en_retard'           => 'bg-red-100 text-red-700',
                        'annulee'             => 'bg-rose-100 text-rose-600',
                        'archive'             => 'bg-slate-100 text-slate-400',
                        default               => 'bg-slate-100 text-slate-600',
                    };
                    $restant = max(0, (float)$f->montant_total - (float)$f->montant_paye);
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3">
                            <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $f->id ?>"
                               class="font-mono font-medium text-violet-700 hover:text-violet-900 text-sm">
                                <?= htmlspecialchars($f->numero) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($f->eleve_nom) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($f->classe_nom ?? '—') ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600"><?= $f->date_emission ?></td>
                        <td class="px-4 py-3 text-sm <?= ($f->date_echeance && $f->date_echeance < date('Y-m-d') && $f->statut === 'emise') ? 'text-red-600 font-medium' : 'text-slate-600' ?>">
                            <?= $f->date_echeance ?? '—' ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold text-slate-800">
                            <?= $fmtMontant((float)$f->montant_total) ?>
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-emerald-600">
                            <?= $fmtMontant((float)$f->montant_paye) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full <?= $badge ?>">
                                <?= $statuts[$f->statut] ?? $f->statut ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $f->id ?>"
                                   class="p-1.5 text-slate-400 hover:text-violet-600 rounded" title="Voir">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $f->id ?>/print"
                                   class="p-1.5 text-slate-400 hover:text-slate-600 rounded" title="Imprimer">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
            <span class="text-sm text-slate-500">
                Page <?= $result['page'] ?> / <?= $result['total_pages'] ?>
            </span>
            <div class="flex gap-1">
                <?php for ($p = 1; $p <= $result['total_pages']; $p++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                   class="w-8 h-8 flex items-center justify-center text-sm rounded-lg
                          <?= $p === $result['page'] ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
