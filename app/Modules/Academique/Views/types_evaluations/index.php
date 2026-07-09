<?php
$pagination = $pagination ?? ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];
$stats      = $stats      ?? [];
$filters    = $filters    ?? null;
$policy     = $policy     ?? null;
$user       = $user       ?? [];
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="layers" class="w-5 h-5 text-violet-500"></i>
            Types d'évaluations — Académique V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= $stats['total'] ?? 0 ?> type(s) · <?= $stats['systeme'] ?? 0 ?> système(s) V1
        </p>
    </div>
    <?php if ($policy && $policy->canCreate($user)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/types-evaluations/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouveau type
    </a>
    <?php endif; ?>
</div>

<?php $flash = \Core\Session::getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4" role="alert">
    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-triangle' ?>" class="w-4 h-4 shrink-0"></i>
    <span class="flex-1 text-sm"><?= htmlspecialchars($flash['message'], ENT_QUOTES) ?></span>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<!-- Statistiques -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-violet-600"><?= $stats['total'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Total</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-emerald-600"><?= $stats['actifs'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Actifs</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-amber-600"><?= $stats['inactifs'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Inactifs</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-slate-500"><?= $stats['archives'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Archivés</p>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/academique/types-evaluations"
      class="flex flex-wrap gap-3 mb-6">
    <input type="text" name="search"
           value="<?= htmlspecialchars($filters?->search ?? '', ENT_QUOTES) ?>"
           placeholder="Rechercher par nom ou code…"
           class="form-input flex-1 min-w-48">

    <select name="actif" class="form-input w-40">
        <option value="">Tous les statuts</option>
        <option value="1"       <?= ($filters?->actif ?? '') === '1'       ? 'selected' : '' ?>>Actifs</option>
        <option value="0"       <?= ($filters?->actif ?? '') === '0'       ? 'selected' : '' ?>>Inactifs</option>
        <option value="archive" <?= ($filters?->actif ?? '') === 'archive' ? 'selected' : '' ?>>Archivés</option>
    </select>

    <select name="systeme" class="form-input w-36">
        <option value="">Tous types</option>
        <option value="1" <?= ($filters?->systeme ?? '') === '1' ? 'selected' : '' ?>>Système V1</option>
    </select>

    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/academique/types-evaluations" class="btn btn-secondary">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
</form>

<!-- Tableau -->
<?php if (empty($pagination['data'])): ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm p-12 text-center text-slate-400">
    <i data-lucide="layers" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucun type d'évaluation trouvé.</p>
    <?php if ($policy && $policy->canCreate($user)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/types-evaluations/create" class="btn btn-primary mt-4 inline-flex">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer le premier type
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Code</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Nom</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Coef. défaut</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Note max</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Usage V1</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pagination['data'] as $t): ?>
                <?php
                    $isArchive  = (int)$t->est_archive;
                    $isActif    = (int)$t->actif;
                    $isSysteme  = (int)$t->est_systeme;
                    if ($isArchive) {
                        $badgeColor = 'slate'; $badgeLabel = 'Archivé';
                    } elseif ($isActif) {
                        $badgeColor = 'emerald'; $badgeLabel = 'Actif';
                    } else {
                        $badgeColor = 'amber'; $badgeLabel = 'Inactif';
                    }
                    $couleur = $t->couleur ?: '#94a3b8';
                ?>
                <tr class="hover:bg-slate-50 transition-colors <?= $isArchive ? 'opacity-60' : '' ?>">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full shrink-0"
                                  style="background:<?= htmlspecialchars($couleur, ENT_QUOTES) ?>"></span>
                            <?php if ($t->icone): ?>
                            <i data-lucide="<?= htmlspecialchars($t->icone, ENT_QUOTES) ?>"
                               class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                            <?php endif; ?>
                            <code class="text-xs font-mono bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
                                <?= htmlspecialchars($t->code, ENT_QUOTES) ?>
                            </code>
                            <?php if ($isSysteme): ?>
                            <span class="text-xs text-violet-600 bg-violet-50 px-1.5 py-0.5 rounded font-medium">V1</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <a href="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $t->id ?>"
                           class="font-medium text-slate-900 hover:text-violet-700 transition">
                            <?= htmlspecialchars($t->nom, ENT_QUOTES) ?>
                        </a>
                        <?php if ((int)$t->est_eliminatoire): ?>
                        <span class="ml-1 text-xs text-red-600 bg-red-50 px-1.5 py-0.5 rounded">éliminatoire</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center font-mono text-slate-700">
                        ×<?= number_format((float)$t->coefficient_defaut, 2) ?>
                    </td>
                    <td class="px-4 py-3 text-center text-slate-600">
                        /<?= number_format((float)$t->note_max_defaut, 0) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php $nbV1 = (int)($t->nb_controles_v1 ?? 0); ?>
                        <?php if ($nbV1 > 0): ?>
                        <span class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full">
                            <i data-lucide="database" class="w-3 h-3"></i><?= $nbV1 ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center gap-1 text-xs font-medium
                              px-2.5 py-1 rounded-full
                              bg-<?= $badgeColor ?>-100 text-<?= $badgeColor ?>-700">
                            <?= $badgeLabel ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $t->id ?>"
                               class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition"
                               title="Voir">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <?php if ($policy && $policy->canUpdate($user, $t)): ?>
                            <a href="<?= BASE_URL ?>/v2/academique/types-evaluations/<?= $t->id ?>/edit"
                               class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition"
                               title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($policy && !$isArchive && $isActif && $policy->canDeactivate($user, $t)): ?>
                            <button type="button"
                                    onclick="openModal(<?= $t->id ?>, '<?= htmlspecialchars($t->nom, ENT_JS) ?>', 'desactiver')"
                                    class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition"
                                    title="Désactiver">
                                <i data-lucide="toggle-left" class="w-4 h-4"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($policy && !$isArchive && !$isActif && $policy->canActivate($user, $t)): ?>
                            <button type="button"
                                    onclick="openModal(<?= $t->id ?>, '<?= htmlspecialchars($t->nom, ENT_JS) ?>', 'activer')"
                                    class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition"
                                    title="Activer">
                                <i data-lucide="toggle-right" class="w-4 h-4"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($policy && $policy->canArchiver($user, $t)): ?>
                            <button type="button"
                                    onclick="openModal(<?= $t->id ?>, '<?= htmlspecialchars($t->nom, ENT_JS) ?>', 'archiver')"
                                    class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition"
                                    title="Archiver">
                                <i data-lucide="archive" class="w-4 h-4"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination['last_page'] > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
        <span><?= $pagination['total'] ?> type(s)</span>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
               class="px-3 py-1 rounded <?= $i === $pagination['current_page'] ? 'bg-violet-600 text-white' : 'hover:bg-slate-100' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal confirmation -->
<div id="actionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div id="modalIcon" class="w-10 h-10 rounded-xl flex items-center justify-center">
                <i id="modalIconName" data-lucide="archive" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 id="modalTitle" class="font-semibold text-slate-900"></h3>
                <p id="modalName" class="text-sm text-slate-500"></p>
            </div>
        </div>
        <p id="modalDesc" class="text-sm text-slate-600 mb-5"></p>
        <form id="actionForm" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <div class="flex gap-3">
                <button type="submit" id="modalBtn" class="btn flex-1">Confirmer</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary flex-1">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
const modalCfg = {
    activer:   { title:'Activer le type',       icon:'toggle-right', iconBg:'bg-emerald-50', iconColor:'text-emerald-500', btnClass:'btn-primary',  desc:"Ce type apparaîtra dans les sélecteurs d'évaluation." },
    desactiver:{ title:'Désactiver le type',    icon:'toggle-left',  iconBg:'bg-amber-50',   iconColor:'text-amber-500',   btnClass:'btn-secondary', desc:"Ce type n'apparaîtra plus dans les listes de création d'évaluations." },
    archiver:  { title:'Archiver le type',      icon:'archive',      iconBg:'bg-red-50',     iconColor:'text-red-500',     btnClass:'btn-danger',    desc:"Action irréversible. Le type ne pourra plus être réactivé." },
};
function openModal(id, nom, action) {
    const cfg = modalCfg[action];
    document.getElementById('modalTitle').textContent   = cfg.title;
    document.getElementById('modalName').textContent    = nom;
    document.getElementById('modalDesc').textContent    = cfg.desc;
    document.getElementById('modalIcon').className      = `w-10 h-10 rounded-xl flex items-center justify-center ${cfg.iconBg}`;
    const iconEl = document.getElementById('modalIconName');
    iconEl.setAttribute('data-lucide', cfg.icon);
    iconEl.className = `w-5 h-5 ${cfg.iconColor}`;
    lucide.createIcons();
    const btn = document.getElementById('modalBtn');
    btn.className = `btn ${cfg.btnClass} flex-1`;
    document.getElementById('actionForm').action = `<?= BASE_URL ?>/v2/academique/types-evaluations/${id}/${action}`;
    document.getElementById('actionModal').classList.remove('hidden');
    document.getElementById('actionModal').classList.add('flex');
}
function closeModal() {
    document.getElementById('actionModal').classList.add('hidden');
    document.getElementById('actionModal').classList.remove('flex');
}
document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
