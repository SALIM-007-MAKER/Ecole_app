<?php
/** @var array $enseignants */
/** @var array $pagination */
/** @var array $stats */
/** @var array $matieres */
/** @var \App\Modules\RH\Enseignants\DTO\TeacherFiltersDTO $filters */
/** @var bool $canCreate */
/** @var bool $canExport */

function hEns(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$statutColors = [
    'titulaire'   => 'emerald',
    'vacataire'   => 'blue',
    'remplacant'  => 'amber',
    'stagiaire'   => 'purple',
    'contractuel' => 'slate',
];
$statutLabels = [
    'titulaire'   => 'Titulaire',
    'vacataire'   => 'Vacataire',
    'remplacant'  => 'Remplaçant',
    'stagiaire'   => 'Stagiaire',
    'contractuel' => 'Contractuel',
];
?>

<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="graduation-cap" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900">Enseignants</h2>
            <p class="text-sm text-slate-500 mt-0.5">Profils pédagogiques</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if ($canExport): ?>
        <a href="<?= BASE_URL ?>/v2/rh/enseignants/export?<?= hEns(http_build_query($_GET)) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="download" class="w-4 h-4"></i>Export CSV
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/v2/rh/enseignants/statistiques"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i>Statistiques
        </a>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/enseignants/create"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Nouveau profil
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hEns($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>
<?php if ($flash = \Core\Session::getFlash('error')): ?>
<div class="flex items-center gap-2 p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= hEns($flash) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<!-- Stats rapides -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="badge-check" class="w-3.5 h-3.5 text-emerald-600"></i>
            </div>
            <div class="text-2xl font-bold text-emerald-600"><?= (int)($stats['par_statut']['titulaire'] ?? 0) ?></div>
        </div>
        <div class="text-xs text-slate-500">Titulaires</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="clock" class="w-3.5 h-3.5 text-blue-600"></i>
            </div>
            <div class="text-2xl font-bold text-blue-600"><?= (int)($stats['par_statut']['vacataire'] ?? 0) ?></div>
        </div>
        <div class="text-xs text-slate-500">Vacataires</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="graduation-cap" class="w-3.5 h-3.5 text-slate-600"></i>
            </div>
            <div class="text-2xl font-bold text-slate-700"><?= array_sum($stats['par_statut'] ?? []) ?></div>
        </div>
        <div class="text-xs text-slate-500">Total profils</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-2 mb-1">
            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="archive" class="w-3.5 h-3.5 text-slate-400"></i>
            </div>
            <div class="text-2xl font-bold text-slate-400"><?= (int)($stats['archives'] ?? 0) ?></div>
        </div>
        <div class="text-xs text-slate-500">Archivés</div>
    </div>
</div>

<!-- Filtres -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
            <input type="text" name="q" value="<?= hEns($filters->q) ?>" placeholder="Nom, prénom, matricule, spécialité…"
                   class="rounded-lg border border-slate-200 text-sm px-3 py-1.5 w-52 focus:ring-2 focus:ring-violet-300 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Statut pédagogique</label>
            <select name="statut" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($statutLabels as $val => $lbl): ?>
                <option value="<?= hEns($val) ?>" <?= $filters->statut === $val ? 'selected' : '' ?>><?= hEns($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Matière</label>
            <select name="matiere_id" class="form-select">
                <option value="">Toutes les matières</option>
                <?php foreach ($matieres as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $filters->matiereId === (int)$m['id'] ? 'selected' : '' ?>><?= hEns($m['nom']) ?></option>
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
        <a href="<?= BASE_URL ?>/v2/rh/enseignants" class="text-sm text-slate-500 hover:text-slate-700 self-end py-1.5">Réinitialiser</a>
    </form>
</div>

<!-- Tableau -->
<div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100">
        <span class="text-sm font-medium text-slate-700">
            <?= number_format($pagination['total']) ?> enseignant<?= $pagination['total'] > 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($enseignants)): ?>
    <div class="py-16 text-center text-slate-400">
        <i data-lucide="graduation-cap" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
        <p class="text-sm">Aucun profil enseignant trouvé.</p>
        <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/v2/rh/enseignants/create"
           class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Créer le premier profil
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Enseignant</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Spécialité</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Matières</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Charge (h/sem)</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Département</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($enseignants as $t):
                $color = $statutColors[$t['statut_pedagogique']] ?? 'slate';
                $label = $statutLabels[$t['statut_pedagogique']] ?? $t['statut_pedagogique'];
                $isArchived = !empty($t['deleted_at']);
                $chargeMax  = (int)($t['charge_horaire_max']      ?? 0);
                $chargeAct  = (int)($t['charge_horaire_actuelle'] ?? 0);
                $chargePct  = $chargeMax > 0 ? min(100, round($chargeAct / $chargeMax * 100)) : 0;
                $chargeColor = $chargePct >= 90 ? 'red' : ($chargePct >= 70 ? 'amber' : 'emerald');
            ?>
            <tr class="hover:bg-slate-50 transition-colors <?= $isArchived ? 'opacity-60' : '' ?>">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-xs shrink-0">
                            <?= mb_strtoupper(mb_substr($t['prenom'], 0, 1) . mb_substr($t['nom'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="font-medium text-slate-900"><?= hEns($t['prenom'] . ' ' . $t['nom']) ?></div>
                            <div class="text-xs text-slate-400 font-mono"><?= hEns($t['matricule']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $color ?>-100 text-<?= $color ?>-700">
                        <?= hEns($label) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs"><?= hEns($t['specialite_principale'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-700">
                        <i data-lucide="book-open" class="w-3.5 h-3.5 text-slate-400"></i>
                        <?= (int)($t['nb_matieres'] ?? 0) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-<?= $chargeColor ?>-500 rounded-full" style="width:<?= $chargePct ?>%"></div>
                        </div>
                        <span class="text-xs text-slate-600"><?= $chargeAct ?>/<?= $chargeMax ?>h</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs"><?= hEns($t['departement_nom'] ?? '—') ?></td>
                <td class="px-4 py-3">
                    <a href="<?= BASE_URL ?>/v2/rh/enseignants/<?= (int)$t['id'] ?>"
                       class="inline-flex items-center gap-1 text-xs text-violet-600 hover:text-violet-800 font-medium">
                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>Voir
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['last_page'] > 1): ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between text-sm">
        <span class="text-slate-500">Page <?= $pagination['page'] ?> / <?= $pagination['last_page'] ?></span>
        <div class="flex gap-1">
            <?php $qs = $_GET; if ($pagination['page'] > 1): $qs['page'] = $pagination['page'] - 1; ?>
            <a href="?<?= http_build_query($qs) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>
            <?php endif; if ($pagination['page'] < $pagination['last_page']): $qs['page'] = $pagination['page'] + 1; ?>
            <a href="?<?= http_build_query($qs) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
