<?php
/** @var array $user */
/** @var array $recompenses */
/** @var int $total, $page, $pages */
/** @var \App\Modules\VieScolaire\Recompenses\DTO\RewardFiltersDTO $filters */
/** @var array $categories */
/** @var \App\Modules\VieScolaire\Recompenses\Policies\RewardPolicy $policy */

$title = 'Récompenses';

$statutBadge = [
    'attribuee' => 'bg-amber-100 text-amber-800',
    'validee'   => 'bg-green-100 text-green-800',
    'revoquee'  => 'bg-red-100 text-red-600 line-through',
];
$niveauBadge = [
    'classe'       => 'bg-slate-100 text-slate-600',
    'etablissement'=> 'bg-violet-100 text-violet-700',
    'academique'   => 'bg-yellow-100 text-yellow-700',
];

function recompenseInitiales(string $nom, string $prenom): string
{
    $n = mb_strtoupper(mb_substr(trim($nom), 0, 1));
    $p = mb_strtoupper(mb_substr(trim($prenom), 0, 1));
    return $n . $p;
}
?>
    <div class="flex items-center justify-between gap-4 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="award" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Récompenses</h1>
                <p class="text-slate-500 text-sm mt-0.5"><?= $total ?> récompense<?= $total > 1 ? 's' : '' ?> enregistrée<?= $total > 1 ? 's' : '' ?></p>
            </div>
        </div>
        <div class="flex gap-3 flex-shrink-0">
            <?php if ($policy->canCreate($user)): ?>
                <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/create"
                   class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i data-lucide="award" class="w-4 h-4"></i> Attribuer
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/classement"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i data-lucide="trophy" class="w-4 h-4"></i> Classement
            </a>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Année scolaire</label>
                <input type="text" name="annee_scolaire" value="<?= htmlspecialchars($filters->anneeScolaire ?? '') ?>"
                       placeholder="2025-2026"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Catégorie</label>
                <select name="categorie_id" class="form-select">
                    <option value="">Toutes</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filters->categorieId == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Niveau</label>
                <select name="niveau" class="form-select">
                    <option value="">Tous</option>
                    <option value="classe"        <?= $filters->niveau === 'classe'        ? 'selected' : '' ?>>Classe</option>
                    <option value="etablissement" <?= $filters->niveau === 'etablissement' ? 'selected' : '' ?>>Établissement</option>
                    <option value="academique"    <?= $filters->niveau === 'academique'    ? 'selected' : '' ?>>Académique</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <option value="attribuee" <?= $filters->statut === 'attribuee' ? 'selected' : '' ?>>Attribuée</option>
                    <option value="validee"   <?= $filters->statut === 'validee'   ? 'selected' : '' ?>>Validée</option>
                    <option value="revoquee"  <?= $filters->statut === 'revoquee'  ? 'selected' : '' ?>>Révoquée</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end items-center gap-4 mt-4 pt-4 border-t border-slate-100">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses" class="text-sm text-slate-500 hover:text-slate-700">Réinitialiser</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i data-lucide="filter" class="w-4 h-4"></i> Filtrer
            </button>
        </div>
    </form>

    <!-- Tableau -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Élève</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Classe</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Catégorie</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Motif</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Niveau</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($recompenses)): ?>
                    <tr>
                        <td colspan="8" class="py-14">
                            <div class="flex flex-col items-center gap-2 text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center">
                                    <i data-lucide="award" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm">Aucune récompense enregistrée</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recompenses as $r): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                                        <?= htmlspecialchars(recompenseInitiales($r['eleve_nom'], $r['eleve_prenom'])) ?>
                                    </div>
                                    <span class="font-medium text-slate-800">
                                        <?= htmlspecialchars($r['eleve_nom'] . ' ' . $r['eleve_prenom']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($r['classe_nom']) ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full" style="background-color: <?= htmlspecialchars($r['categorie_couleur']) ?>"></span>
                                    <?= htmlspecialchars($r['categorie_nom']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 max-w-xs truncate"><?= htmlspecialchars(mb_strimwidth($r['motif'], 0, 50, '…')) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $niveauBadge[$r['niveau']] ?? '' ?>">
                                    <?= ucfirst($r['niveau']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $statutBadge[$r['statut']] ?? '' ?>">
                                    <?= ucfirst($r['statut']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($r['date_attribution'])) ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/<?= $r['id'] ?>"
                                   class="inline-flex items-center gap-1 text-violet-600 hover:text-violet-800 font-medium text-xs">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <div class="flex justify-center gap-2 mt-6">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="px-3 py-1 rounded-lg text-sm <?= $i === $page ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <?php if ($policy->canExport($user)): ?>
        <div class="mt-4 text-right">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/recompenses/export?<?= http_build_query($_GET) ?>"
               class="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-violet-600">
                <i data-lucide="download" class="w-4 h-4"></i> Exporter CSV
            </a>
        </div>
    <?php endif; ?>
