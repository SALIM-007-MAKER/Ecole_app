<?php
/**
 * Finance V2 — Décaissements
 * GET /v2/finance/decaissements
 */
$statuts = $statuts ?? [];
$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center gap-2 text-sm text-slate-500">
        <a href="<?= BASE_URL ?>/v2/finance/rapports/dashboard" class="hover:text-violet-600">Finance</a>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <span class="text-slate-700">Décaissements</span>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="arrow-down-circle" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Décaissements</h1>
                <p class="text-sm text-slate-500 mt-0.5">Dépenses, décaissements et suivi des paiements fournisseurs</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
            <a href="<?= BASE_URL ?>/v2/finance/fournisseurs"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                <i data-lucide="truck" class="w-4 h-4"></i> Fournisseurs
            </a>
            <a href="<?= BASE_URL ?>/v2/finance/decaissements/categories"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                <i data-lucide="tag" class="w-4 h-4"></i> Catégories
            </a>
            <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/v2/finance/decaissements/create"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i> Nouveau décaissement
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cartes stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center mb-2">
                <i data-lucide="arrow-down-circle" class="w-4 h-4 text-slate-600"></i>
            </div>
            <div class="text-2xl font-bold text-slate-800"><?= (int)$stats->total_decaissements ?></div>
            <div class="text-xs text-slate-500 mt-1">Total décaissements</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center mb-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
            </div>
            <div class="text-2xl font-bold text-emerald-600"><?= $fmt((float)$stats->montant_paye) ?></div>
            <div class="text-xs text-slate-500 mt-1">Montant payé</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center mb-2">
                <i data-lucide="hourglass" class="w-4 h-4 text-amber-500"></i>
            </div>
            <div class="text-2xl font-bold text-amber-500"><?= $fmt((float)$stats->montant_en_attente) ?></div>
            <div class="text-xs text-slate-500 mt-1">En attente</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mb-2">
                <i data-lucide="clipboard-check" class="w-4 h-4 text-blue-600"></i>
            </div>
            <div class="text-2xl font-bold text-blue-600"><?= (int)$stats->nb_a_valider ?></div>
            <div class="text-xs text-slate-500 mt-1">À valider</div>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q ?? '') ?>"
                   placeholder="Numéro, libellé, fournisseur..."
                   class="form-input">

            <select name="statut" class="form-select">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts as $val => $label): ?>
                <option value="<?= $val ?>" <?= $filters->statut === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <select name="categorie_id" class="form-select">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat->id ?>" <?= (int)$filters->categorieId === (int)$cat->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat->nom) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
                   class="form-input">
            <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
                   class="form-input">
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                <i data-lucide="search" class="inline w-4 h-4 mr-1"></i> Filtrer
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/decaissements" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Réinitialiser</a>
        </div>
    </form>

    <!-- Flash messages -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Numéro / Libellé</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Catégorie</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Fournisseur</th>
                    <th class="px-4 py-3 text-right font-medium text-slate-600">Montant</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Date dépense</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Justificatifs</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="7" class="py-14">
                        <div class="flex flex-col items-center gap-2 text-slate-400">
                            <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center">
                                <i data-lucide="arrow-down-circle" class="w-5 h-5"></i>
                            </div>
                            <p class="text-sm">Aucun décaissement trouvé.</p>
                            <?php if ($canCreate): ?>
                            <a href="<?= BASE_URL ?>/v2/finance/decaissements/create"
                               class="inline-flex items-center gap-1 mt-2 text-violet-600 hover:text-violet-800 text-sm font-medium">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Créer le premier décaissement
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $d): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3">
                        <a href="<?= BASE_URL ?>/v2/finance/decaissements/<?= $d->id ?>"
                           class="font-medium text-slate-800 hover:text-violet-700">
                            <?= htmlspecialchars($d->libelle) ?>
                        </a>
                        <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($d->numero) ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($d->categorie_nom): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium text-white"
                              style="background-color:<?= htmlspecialchars($d->categorie_couleur ?? '#64748b') ?>">
                            <?= htmlspecialchars($d->categorie_nom) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($d->fournisseur_nom ?? '—') ?></td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-slate-800"><?= $fmt((float)$d->montant) ?></td>
                    <td class="px-4 py-3 text-slate-600"><?= $d->date_depense ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ((int)$d->nb_justificatifs > 0): ?>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                            <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> <?= (int)$d->nb_justificatifs ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= \App\Modules\Finance\Models\DecaissementModel::statutColor($d->statut) ?>">
                            <?= $statuts[$d->statut] ?? $d->statut ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-500">
                <?= $result['total'] ?> résultat(s) — page <?= $result['page'] ?> / <?= $result['total_pages'] ?>
            </span>
            <div class="flex gap-1">
                <?php if ($result['page'] > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] - 1])) ?>"
                   class="px-3 py-1 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
                <?php if ($result['page'] < $result['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] + 1])) ?>"
                   class="px-3 py-1 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
