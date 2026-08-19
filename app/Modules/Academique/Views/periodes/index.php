<?php
$pagination = $pagination ?? ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];
$stats      = $stats      ?? [];
$filters    = $filters    ?? null;
$annees     = $annees     ?? [];
$types      = $types      ?? [];
$statuts    = $statuts    ?? [];
$couverture = $couverture ?? null;
$perms      = $perms      ?? [];
$policy     = $policy     ?? null;
$user       = $user       ?? [];

$statutColors = [
    'preparation' => 'sky',
    'ouverte'     => 'emerald',
    'cloturee'    => 'amber',
    'archivee'    => 'slate',
];
$statutIcons = [
    'preparation' => 'circle-dashed',
    'ouverte'     => 'unlock',
    'cloturee'    => 'clock',
    'archivee'    => 'archive',
];
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-range" class="w-5 h-5 text-violet-500"></i>
            Périodes Scolaires — Académique V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= $stats['total'] ?? 0 ?> période(s) · <?= $stats['nb_annees'] ?? 0 ?> année(s) scolaire(s)
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ($policy && $policy->canGererConfig($user)): ?>
        <a href="<?= BASE_URL ?>/v2/academique/periodes/config" class="btn btn-secondary">
            <i data-lucide="settings" class="w-4 h-4"></i>Modèle par défaut
        </a>
        <?php endif; ?>
        <?php if ($policy && $policy->canCreate($user)): ?>
        <a href="<?= BASE_URL ?>/v2/academique/periodes/create" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle période
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Génération automatique (modèle par défaut Niger) -->
<?php if ($policy && $policy->canCreate($user)): ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
    <form method="POST" action="<?= BASE_URL ?>/v2/academique/periodes/generer" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Année scolaire</label>
            <input type="text" name="annee_scolaire" placeholder="2025-2026" pattern="\d{4}-\d{4}"
                   value="<?= htmlspecialchars($filters?->anneeScolaire ?? '', ENT_QUOTES) ?>"
                   class="form-input w-40" required>
        </div>
        <button type="submit" class="btn btn-secondary">
            <i data-lucide="wand-2" class="w-4 h-4"></i>Générer les 2 semestres (modèle par défaut)
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Vérification de couverture -->
<?php if ($couverture !== null): ?>
<div class="rounded-xl border shadow-sm p-4 mb-6 <?= $couverture['couverte'] ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' ?>">
    <div class="flex items-center gap-2 mb-1">
        <i data-lucide="<?= $couverture['couverte'] ? 'check-circle' : 'alert-triangle' ?>"
           class="w-4 h-4 <?= $couverture['couverte'] ? 'text-emerald-600' : 'text-amber-600' ?>"></i>
        <span class="text-sm font-semibold <?= $couverture['couverte'] ? 'text-emerald-700' : 'text-amber-700' ?>">
            <?= $couverture['couverte']
                ? 'L\'année scolaire ' . htmlspecialchars($filters->anneeScolaire, ENT_QUOTES) . ' est entièrement couverte.'
                : 'Couverture incomplète pour ' . htmlspecialchars($filters->anneeScolaire, ENT_QUOTES) . ' :' ?>
        </span>
    </div>
    <?php if (!$couverture['couverte']): ?>
    <ul class="text-xs text-amber-700 mt-2 space-y-0.5 pl-6 list-disc">
        <?php foreach ($couverture['gaps'] as $gap): ?>
        <li>Trou non couvert : <?= date('d/m/Y', strtotime($gap['debut'])) ?> → <?= date('d/m/Y', strtotime($gap['fin'])) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

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
        <p class="text-2xl font-bold text-emerald-600"><?= $stats['ouvertes'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Ouvertes</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-amber-600"><?= $stats['cloturees'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Clôturées <span class="text-red-500">(<?= $stats['verrouillees'] ?? 0 ?> verr.)</span></p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-slate-500"><?= $stats['archivees'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Archivées</p>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/academique/periodes" class="flex flex-wrap gap-3 mb-6">
    <select name="annee_scolaire" class="form-input w-36">
        <option value="">Toutes les années</option>
        <?php foreach ($annees as $a): ?>
        <option value="<?= htmlspecialchars($a, ENT_QUOTES) ?>"
                <?= ($filters?->anneeScolaire ?? '') === $a ? 'selected' : '' ?>>
            <?= htmlspecialchars($a, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="statut" class="form-input w-36">
        <option value="">Tous les statuts</option>
        <?php foreach ($statuts as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($filters?->statut ?? '') === $val ? 'selected' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="type_periode" class="form-input w-44">
        <option value="">Tous les types</option>
        <?php foreach ($types as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($filters?->typePeriode ?? '') === $val ? 'selected' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/academique/periodes" class="btn btn-secondary">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
</form>

<!-- Tableau -->
<?php if (empty($pagination['data'])): ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm p-12 text-center text-slate-400">
    <i data-lucide="calendar-range" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucune période trouvée.</p>
    <?php if ($policy && $policy->canCreate($user)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/periodes/create" class="btn btn-primary mt-4 inline-flex">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer la première période
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Période</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Année</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Type</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Dates</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Saisie</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pagination['data'] as $p): ?>
                <?php
                    $color = $statutColors[$p->statut] ?? 'slate';
                    $icon  = $statutIcons[$p->statut]  ?? 'circle';
                    $statutLabel = $statuts[$p->statut] ?? $p->statut;
                ?>
                <tr class="hover:bg-slate-50 transition-colors <?= (int)$p->is_active ? 'bg-violet-50/30' : '' ?>">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <?php if ((int)$p->is_active): ?>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-violet-700 bg-violet-100 px-2 py-0.5 rounded-full">
                                <i data-lucide="star" class="w-3 h-3"></i>Active
                            </span>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/v2/academique/periodes/<?= $p->id ?>"
                               class="font-semibold text-slate-900 hover:text-violet-700 transition">
                                <?= htmlspecialchars($p->nom, ENT_QUOTES) ?>
                            </a>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600 font-mono text-xs">
                        <?= htmlspecialchars($p->annee_scolaire, ENT_QUOTES) ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs text-slate-500">
                            <?= htmlspecialchars($types[$p->type_periode] ?? $p->type_periode, ENT_QUOTES) ?>
                            <?= $p->numero ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">
                        <?php if ($p->date_debut && $p->date_fin): ?>
                            <?= date('d/m/Y', strtotime($p->date_debut)) ?> →
                            <?= date('d/m/Y', strtotime($p->date_fin)) ?>
                        <?php else: ?>
                            <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center gap-1 text-xs font-medium
                            px-2.5 py-1 rounded-full
                            bg-<?= $color ?>-100 text-<?= $color ?>-700">
                            <i data-lucide="<?= $icon ?>" class="w-3 h-3"></i>
                            <?= $statutLabel ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ((int)$p->notes_saisie_ouverte): ?>
                        <span class="inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                            <i data-lucide="check" class="w-3 h-3"></i>Ouverte
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                            <i data-lucide="x" class="w-3 h-3"></i>Fermée
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= BASE_URL ?>/v2/academique/periodes/<?= $p->id ?>"
                               class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition"
                               title="Voir">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <?php if ($policy && $policy->canUpdate($user, $p)): ?>
                            <a href="<?= BASE_URL ?>/v2/academique/periodes/<?= $p->id ?>/edit"
                               class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition"
                               title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($policy && $policy->canArchiver($user, $p) && $p->statut !== 'archivee'): ?>
                            <button type="button"
                                    onclick="openActionModal(<?= $p->id ?>, '<?= htmlspecialchars($p->nom, ENT_JS) ?>', 'archiver')"
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

    <!-- Pagination -->
    <?php if ($pagination['last_page'] > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-500">
        <span><?= $pagination['total'] ?> période(s)</span>
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

<!-- Modal confirmation action -->
<div id="actionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                <i data-lucide="archive" class="w-5 h-5 text-red-500"></i>
            </div>
            <div>
                <h3 id="actionModalTitle" class="font-semibold text-slate-900">Confirmer</h3>
                <p id="actionModalName" class="text-sm text-slate-500"></p>
            </div>
        </div>
        <p class="text-sm text-slate-600 mb-5">Cette action modifie le statut de la période.</p>
        <form id="actionForm" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <div class="flex gap-3">
                <button type="submit" class="btn btn-danger flex-1">Confirmer</button>
                <button type="button" onclick="closeActionModal()" class="btn btn-secondary flex-1">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(id, nom, action) {
    const titles = { archiver: 'Archiver la période' };
    document.getElementById('actionModalTitle').textContent = titles[action] || 'Confirmer';
    document.getElementById('actionModalName').textContent  = nom;
    document.getElementById('actionForm').action = '<?= BASE_URL ?>/v2/academique/periodes/' + id + '/' + action;
    document.getElementById('actionModal').classList.remove('hidden');
    document.getElementById('actionModal').classList.add('flex');
}
function closeActionModal() {
    document.getElementById('actionModal').classList.add('hidden');
    document.getElementById('actionModal').classList.remove('flex');
}
document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) closeActionModal();
});
</script>
