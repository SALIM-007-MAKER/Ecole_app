<?php
/** @var array $employes */
/** @var array $pagination */
/** @var array $stats */
/** @var array $departements */
/** @var \App\Modules\RH\Employes\DTO\EmployeeFiltersDTO $filters */
/** @var bool $canCreate */
/** @var bool $canExport */

$statutColors = [
    'actif'          => 'emerald',
    'inactif'        => 'slate',
    'suspendu'       => 'orange',
    'conge'          => 'blue',
    'retraite'       => 'purple',
    'demissionnaire' => 'red',
];
$statutLabels = [
    'actif'          => 'Actif',
    'inactif'        => 'Inactif',
    'suspendu'       => 'Suspendu',
    'conge'          => 'En congé',
    'retraite'       => 'Retraité',
    'demissionnaire' => 'Démissionnaire',
];
$typeLabels = [
    'enseignant'    => 'Enseignant',
    'administratif' => 'Administratif',
    'support'       => 'Support',
    'direction'     => 'Direction',
    'technique'     => 'Technique',
];

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="users" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900">Employés</h2>
            <p class="text-sm text-slate-500 mt-0.5">Gestion du personnel</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($canExport): ?>
        <a href="<?= BASE_URL ?>/v2/rh/employes/export?<?= http_build_query($_GET) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="download" class="w-4 h-4"></i>Export CSV
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/v2/rh/employes/statistiques"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i>Statistiques
        </a>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/employes/create"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Nouvel employé
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= h($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= h($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<!-- Statistiques rapides -->
<?php
$totalActifs = (int)($stats['par_statut']['actif'] ?? 0);
$totalArchives = (int)($stats['archives'] ?? 0);
$totalSuspendus = (int)($stats['par_statut']['suspendu'] ?? 0);
?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
            </div>
            <div class="text-2xl font-bold text-emerald-600"><?= $totalActifs ?></div>
        </div>
        <div class="text-xs text-slate-500">Employés actifs</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="users" class="w-3.5 h-3.5 text-slate-600"></i>
            </div>
            <div class="text-2xl font-bold text-slate-700"><?= array_sum($stats['par_type'] ?? []) ?></div>
        </div>
        <div class="text-xs text-slate-500">Total personnel</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="pause-circle" class="w-3.5 h-3.5 text-orange-600"></i>
            </div>
            <div class="text-2xl font-bold text-orange-500"><?= $totalSuspendus ?></div>
        </div>
        <div class="text-xs text-slate-500">Suspendus</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="archive" class="w-3.5 h-3.5 text-slate-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-400"><?= $totalArchives ?></div>
        </div>
        <div class="text-xs text-slate-500">Archivés</div>
    </div>
</div>

<!-- Filtres -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
            <input type="text" name="q" value="<?= h($filters->q) ?>" placeholder="Nom, prénom, matricule, email…"
                   class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 w-52 focus:ring-2 focus:ring-violet-300 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut</label>
            <select name="statut" class="form-select">
                <option value="">Tous les statuts</option>
                <?php foreach ($statutLabels as $val => $lbl): ?>
                <option value="<?= h($val) ?>" <?= $filters->statut === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
            <select name="type" class="form-select">
                <option value="">Tous les types</option>
                <?php foreach ($typeLabels as $val => $lbl): ?>
                <option value="<?= h($val) ?>" <?= $filters->type === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Département</label>
            <select name="depart_id" class="form-select">
                <option value="">Tous les départements</option>
                <?php foreach ($departements as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= $filters->departId === (int)$d['id'] ? 'selected' : '' ?>>
                    <?= h($d['nom']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <label class="flex items-center gap-1.5 text-sm text-slate-600 pb-1.5 cursor-pointer">
                <input type="checkbox" name="archive" value="1" <?= $filters->includeArch === '1' ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-violet-600 focus:ring-violet-300">
                Inclure archivés
            </label>
        </div>
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="search" class="w-4 h-4"></i>Filtrer
        </button>
        <a href="<?= BASE_URL ?>/v2/rh/employes" class="text-sm text-slate-500 hover:text-slate-700 self-end py-1.5">Réinitialiser</a>
    </form>
</div>

<!-- Tableau -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <span class="text-sm font-medium text-slate-700">
            <?= number_format($pagination['total']) ?> employé<?= $pagination['total'] > 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($employes)): ?>
    <div class="py-16 text-center text-slate-400">
        <i data-lucide="users" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
        <p class="text-sm">Aucun employé trouvé.</p>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/employes/create"
           class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Créer le premier employé
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Employé</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Matricule</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Type</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Département</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Poste</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($employes as $e):
                $color = $statutColors[$e['statut']] ?? 'slate';
                $label = $statutLabels[$e['statut']] ?? $e['statut'];
                $type  = $typeLabels[$e['type_personnel']] ?? $e['type_personnel'];
                $isArchived = !empty($e['deleted_at']);
            ?>
            <tr class="hover:bg-slate-50 transition-colors <?= $isArchived ? 'opacity-60' : '' ?>">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                            <?= mb_strtoupper(mb_substr($e['prenom'], 0, 1) . mb_substr($e['nom'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">
                                <?= h($e['prenom'] . ' ' . $e['nom']) ?>
                                <?php if ($isArchived): ?>
                                <span class="ml-1 text-xs text-slate-400">(archivé)</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-400"><?= h($e['email_pro'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-500 font-mono text-xs"><?= h($e['matricule']) ?></td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                        <?= h($type) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-600"><?= h($e['departement_nom'] ?? '—') ?></td>
                <td class="px-4 py-3 text-slate-500 text-xs"><?= h($e['poste_intitule'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                        <?= h($label) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <a href="<?= BASE_URL ?>/v2/rh/employes/<?= (int)$e['id'] ?>"
                       class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>Voir
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['last_page'] > 1): ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between text-sm">
        <span class="text-slate-500">
            Page <?= $pagination['page'] ?> / <?= $pagination['last_page'] ?>
        </span>
        <div class="flex gap-1">
            <?php
            $qs = $_GET;
            if ($pagination['page'] > 1):
                $qs['page'] = $pagination['page'] - 1;
            ?>
            <a href="?<?= http_build_query($qs) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
            <?php if ($pagination['page'] < $pagination['last_page']):
                $qs['page'] = $pagination['page'] + 1;
            ?>
            <a href="?<?= http_build_query($qs) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
