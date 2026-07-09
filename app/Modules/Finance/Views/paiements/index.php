<?php
/**
 * Finance V2 — Liste des encaissements
 */
$result   = $result  ?? ['data' => [], 'total' => 0, 'total_pages' => 1, 'page' => 1];
$filters  = $filters ?? null;
$stats    = $stats   ?? null;
$par_mode = $par_mode ?? [];
$modes    = $modes   ?? [];
$annees   = $annees  ?? [];
$statuts  = $statuts ?? [];

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$badgeStatut = function(string $s): string {
    return match ($s) {
        'initie'    => 'bg-slate-100 text-slate-600',
        'valide'    => 'bg-purple-100 text-purple-700',
        'complete'  => 'bg-emerald-100 text-emerald-700',
        'annule'    => 'bg-rose-100 text-rose-600',
        'rembourse' => 'bg-amber-100 text-amber-700',
        default     => 'bg-slate-100 text-slate-600',
    };
};
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Encaissements</h1>
            <p class="text-sm text-slate-500 mt-1"><?= $result['total'] ?> paiement(s) trouvé(s)</p>
        </div>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/finance/factures"
           class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
            <i data-lucide="plus" class="inline w-4 h-4 mr-1"></i> Nouveau paiement
        </a>
        <?php endif; ?>
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

    <!-- Stats cards -->
    <?php if ($stats): ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total encaissé</div>
            <div class="text-xl font-bold text-emerald-700 mt-1"><?= $fmtMontant((float)$stats->montant_total_encaisse) ?></div>
            <div class="text-xs text-slate-400 mt-1"><?= $stats->completes ?> paiement(s)</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">En attente</div>
            <div class="text-xl font-bold text-slate-800 mt-1"><?= (int)$stats->inities ?></div>
            <div class="text-xs text-slate-400 mt-1">paiement(s) initiés</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Remboursés</div>
            <div class="text-xl font-bold text-amber-600 mt-1"><?= $fmtMontant((float)$stats->montant_rembourse) ?></div>
            <div class="text-xs text-slate-400 mt-1"><?= $stats->rembourses ?> remboursement(s)</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Annulés</div>
            <div class="text-xl font-bold text-rose-600 mt-1"><?= (int)$stats->annules ?></div>
            <div class="text-xs text-slate-400 mt-1">paiement(s)</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modes de paiement -->
    <?php if ($par_mode): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-3">Répartition par mode</div>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($par_mode as $m): ?>
            <div class="flex items-center gap-2 px-3 py-2 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-sm font-medium text-slate-700"><?= htmlspecialchars($m->mode ?? $m->code) ?></span>
                <span class="text-sm font-bold text-violet-700"><?= $fmtMontant((float)$m->total) ?></span>
                <span class="text-xs text-slate-500">(<?= $m->nb ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
            <div class="lg:col-span-2">
                <input type="text" name="q" value="<?= htmlspecialchars($filters->q ?? '') ?>"
                       placeholder="Nº paiement, nº facture, élève…"
                       class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300 focus:border-violet-400">
            </div>
            <div>
                <select name="statut" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
                    <option value="">Tous statuts</option>
                    <?php foreach ($statuts as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filters->statut ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select name="mode_paiement" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
                    <option value="">Tous modes</option>
                    <?php foreach ($modes as $m): ?>
                    <option value="<?= $m->code ?>" <?= ($filters->modePaiement ?? '') === $m->code ? 'selected' : '' ?>><?= htmlspecialchars($m->nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select name="annee_scolaire" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
                    <option value="">Toutes années</option>
                    <?php foreach ($annees as $a): ?>
                    <option value="<?= $a ?>" <?= ($filters->anneeScolaire ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    <i data-lucide="search" class="inline w-4 h-4"></i>
                </button>
                <a href="<?= BASE_URL ?>/v2/finance/paiements"
                   class="flex-1 flex items-center justify-center px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                    <i data-lucide="x" class="inline w-4 h-4"></i>
                </a>
            </div>
        </form>
        <!-- Filtre dates -->
        <form method="GET" class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
            <?php foreach (['q','statut','mode_paiement','annee_scolaire'] as $hf): ?>
            <?php if (!empty($filters->$hf ?? ($_GET[$hf] ?? ''))): ?>
            <input type="hidden" name="<?= $hf ?>" value="<?= htmlspecialchars($_GET[$hf] ?? '') ?>">
            <?php endif; ?>
            <?php endforeach; ?>
            <div>
                <label class="text-xs text-slate-500">Du</label>
                <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
                       class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
            </div>
            <div>
                <label class="text-xs text-slate-500">Au</label>
                <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
                       class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-300">
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">
                    Filtrer par date
                </button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Numéro</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Élève</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Facture</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Mode</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600">Montant</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Reçu</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                        <p>Aucun paiement trouvé</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs font-semibold text-violet-700">
                        <?= htmlspecialchars($p->numero) ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($p->eleve_nom) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($p->eleve_matricule) ?></div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-600">
                        <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $p->facture_id ?>" class="hover:text-violet-600">
                            <?= htmlspecialchars($p->facture_numero) ?>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-700">
                            <?php if ($p->mode_icone): ?><span><?= htmlspecialchars($p->mode_icone) ?></span><?php endif; ?>
                            <?= htmlspecialchars($p->mode_nom ?? $p->mode_code ?? '—') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800">
                        <?= $fmtMontant((float)$p->montant_applique) ?>
                        <?php if ($p->montant_applique != $p->montant): ?>
                        <div class="text-xs text-slate-400 font-normal">payé: <?= $fmtMontant((float)$p->montant) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= $p->date_paiement ? date('d/m/Y', strtotime($p->date_paiement)) : '—' ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeStatut($p->statut) ?>">
                            <?= $statuts[$p->statut] ?? $p->statut ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($p->recu_numero): ?>
                        <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $p->id ?>/recu"
                           class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800">
                            <i data-lucide="file-text" class="w-3 h-3"></i>
                            <?= htmlspecialchars($p->recu_numero) ?>
                        </a>
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

    <!-- Pagination -->
    <?php if ($result['total_pages'] > 1): ?>
    <div class="flex items-center justify-between text-sm text-slate-600">
        <span>Page <?= $result['page'] ?> / <?= $result['total_pages'] ?> — <?= $result['total'] ?> résultat(s)</span>
        <div class="flex gap-1">
            <?php
            $qs = $_GET;
            for ($i = 1; $i <= $result['total_pages']; $i++):
                $qs['page'] = $i;
                $active = $i === $result['page'];
            ?>
            <a href="?<?= http_build_query($qs) ?>"
               class="px-3 py-1 rounded <?= $active ? 'bg-violet-600 text-white' : 'bg-white border border-slate-300 hover:bg-slate-50' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
