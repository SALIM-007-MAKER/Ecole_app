<?php
/** @var array $user */
/** @var \App\Modules\VieScolaire\Discipline\Policies\DisciplinePolicy $policy */
/** @var array $dossiers */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var \App\Modules\VieScolaire\Discipline\DTO\DisciplineFiltersDTO $filters */

$title = 'Discipline';

$statutLabels = [
    'ouvert'   => ['label' => 'Ouvert',   'class' => 'bg-amber-100 text-amber-800'],
    'en_cours' => ['label' => 'En cours', 'class' => 'bg-blue-100 text-blue-800'],
    'clos'     => ['label' => 'Clos',     'class' => 'bg-slate-100 text-slate-600'],
];

function disciplineInitiales(string $nom, string $prenom): string
{
    $n = mb_strtoupper(mb_substr(trim($nom), 0, 1));
    $p = mb_strtoupper(mb_substr(trim($prenom), 0, 1));
    return $n . $p;
}
?>
    <div class="flex items-center justify-between gap-4 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="shield-alert" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Discipline</h1>
                <p class="text-slate-500 text-sm mt-0.5"><?= $total ?> dossier<?= $total > 1 ? 's' : '' ?> disciplinaire<?= $total > 1 ? 's' : '' ?></p>
            </div>
        </div>
        <?php if ($policy->canCreate($user)): ?>
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/create"
           class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex-shrink-0">
            <i data-lucide="plus" class="w-4 h-4"></i> Signaler un incident
        </a>
        <?php endif; ?>
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
                <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
                <select name="statut" class="form-select">
                    <option value="">Tous</option>
                    <option value="ouvert"   <?= ($filters->statut === 'ouvert')   ? 'selected' : '' ?>>Ouvert</option>
                    <option value="en_cours" <?= ($filters->statut === 'en_cours') ? 'selected' : '' ?>>En cours</option>
                    <option value="clos"     <?= ($filters->statut === 'clos')     ? 'selected' : '' ?>>Clos</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
                <input type="date" name="date_debut" value="<?= htmlspecialchars($filters->dateDebut ?? '') ?>"
                       class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
                <input type="date" name="date_fin" value="<?= htmlspecialchars($filters->dateFin ?? '') ?>"
                       class="form-input">
            </div>
        </div>
        <div class="flex justify-end items-center gap-4 mt-4 pt-4 border-t border-slate-100">
            <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline" class="text-sm text-slate-500 hover:text-slate-700">Réinitialiser</a>
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
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Année</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Incidents</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Ouvert le</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($dossiers)): ?>
                    <tr>
                        <td colspan="7" class="py-14">
                            <div class="flex flex-col items-center gap-2 text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center">
                                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                                </div>
                                <p class="text-sm">Aucun dossier disciplinaire</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dossiers as $d): ?>
                        <?php $info = $statutLabels[$d['statut']] ?? ['label' => $d['statut'], 'class' => 'bg-slate-100 text-slate-600']; ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                                        <?= htmlspecialchars(disciplineInitiales($d['eleve_nom'], $d['eleve_prenom'])) ?>
                                    </div>
                                    <span class="font-medium text-slate-800">
                                        <?= htmlspecialchars($d['eleve_nom'] . ' ' . $d['eleve_prenom']) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($d['classe_nom']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($d['annee_scolaire']) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold <?= $d['nb_incidents'] >= 3 ? 'text-red-600' : 'text-slate-700' ?>">
                                    <?= (int)$d['nb_incidents'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $info['class'] ?>">
                                    <?= $info['label'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500"><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/<?= $d['id'] ?>"
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
        <a href="<?= BASE_URL ?>/v2/vie-scolaire/discipline/export?<?= http_build_query($_GET) ?>"
           class="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-violet-600">
            <i data-lucide="download" class="w-4 h-4"></i> Exporter CSV
        </a>
    </div>
    <?php endif; ?>
