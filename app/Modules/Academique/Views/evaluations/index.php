<?php
$pagination = $pagination ?? ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];
$stats      = $stats      ?? [];
$filters    = $filters    ?? null;
$periodes   = $periodes   ?? [];
$classes    = $classes    ?? [];
$matieres   = $matieres   ?? [];
$types      = $types      ?? [];
$statuts    = $statuts    ?? [];
$colors     = $colors     ?? [];
$policy     = $policy     ?? null;
$user       = $user       ?? [];

$statutIcons = [
    'brouillon'   => 'file-edit',
    'publiee'     => 'check-circle',
    'verrouillee' => 'lock',
    'archivee'    => 'archive',
];
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="clipboard-list" class="w-5 h-5 text-violet-500"></i>
            Évaluations — Académique V2
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= $stats['total'] ?? 0 ?> évaluation(s) · <?= $stats['saisie_ouverte'] ?? 0 ?> saisie(s) ouverte(s)
        </p>
    </div>
    <?php if ($policy && $policy->canCreate($user)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/evaluations/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle évaluation
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
        <p class="text-2xl font-bold text-emerald-600"><?= $stats['publiees'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Publiées</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $stats['verrouillees'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Verrouillées</p>
    </div>
    <div class="rounded-xl bg-white border border-slate-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-blue-600"><?= $stats['brouillons'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-0.5">Brouillons</p>
    </div>
</div>

<!-- Filtres -->
<form method="GET" action="<?= BASE_URL ?>/v2/academique/evaluations"
      class="flex flex-wrap gap-3 mb-6">
    <input type="text" name="search"
           value="<?= htmlspecialchars($filters?->search ?? '', ENT_QUOTES) ?>"
           placeholder="Rechercher (intitulé, matière, classe)…"
           class="form-input flex-1 min-w-48">

    <select name="statut" class="form-input w-36">
        <option value="">Tous statuts</option>
        <?php foreach ($statuts as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($filters?->statut ?? '') === $val ? 'selected' : '' ?>>
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="periode_scolaire_id" class="form-input w-48">
        <option value="">Toutes périodes</option>
        <?php foreach ($periodes as $p): ?>
        <option value="<?= $p->id ?>"
                <?= ($filters?->periodeScolaireId ?? 0) === (int)$p->id ? 'selected' : '' ?>>
            <?= htmlspecialchars($p->nom, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select name="classe_id" class="form-input w-36">
        <option value="">Toutes classes</option>
        <?php foreach ($classes as $cl): ?>
        <option value="<?= $cl->id ?>"
                <?= ($filters?->classeId ?? 0) === (int)$cl->id ? 'selected' : '' ?>>
            <?= htmlspecialchars($cl->nom, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>Filtrer
    </button>
    <a href="<?= BASE_URL ?>/v2/academique/evaluations" class="btn btn-secondary">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>
</form>

<!-- Tableau -->
<?php if (empty($pagination['data'])): ?>
<div class="rounded-xl bg-white border border-slate-200 shadow-sm p-12 text-center text-slate-400">
    <i data-lucide="clipboard-list" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
    <p class="font-medium">Aucune évaluation trouvée.</p>
    <?php if ($policy && $policy->canCreate($user)): ?>
    <a href="<?= BASE_URL ?>/v2/academique/evaluations/create" class="btn btn-primary mt-4 inline-flex">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer la première évaluation
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Évaluation</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Période</th>
                    <th class="text-left px-4 py-3 font-medium text-slate-600">Classe · Matière</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Date</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Barème</th>
                    <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pagination['data'] as $ev): ?>
                <?php
                    $color       = $colors[$ev->statut]    ?? 'slate';
                    $icon        = $statutIcons[$ev->statut] ?? 'circle';
                    $statutLabel = $statuts[$ev->statut]   ?? $ev->statut;
                    $typeCouleur = $ev->type_couleur ?? '#94a3b8';
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 mb-0.5">
                            <?php if ($ev->type_icone): ?>
                            <span class="w-6 h-6 rounded flex items-center justify-center shrink-0"
                                  style="background:<?= htmlspecialchars($typeCouleur, ENT_QUOTES) ?>20">
                                <i data-lucide="<?= htmlspecialchars($ev->type_icone, ENT_QUOTES) ?>"
                                   class="w-3.5 h-3.5"
                                   style="color:<?= htmlspecialchars($typeCouleur, ENT_QUOTES) ?>"></i>
                            </span>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $ev->id ?>"
                               class="font-medium text-slate-900 hover:text-violet-700 transition">
                                <?= htmlspecialchars($ev->libelle, ENT_QUOTES) ?>
                            </a>
                        </div>
                        <span class="text-xs text-slate-400 ml-8">
                            <?= htmlspecialchars($ev->type_nom, ENT_QUOTES) ?>
                            · ×<?= number_format((float)$ev->coefficient, 2) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-slate-700 text-sm"><?= htmlspecialchars($ev->periode_nom, ENT_QUOTES) ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($ev->annee_scolaire, ENT_QUOTES) ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800"><?= htmlspecialchars($ev->classe_nom, ENT_QUOTES) ?></div>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars($ev->matiere_nom, ENT_QUOTES) ?></div>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-slate-500">
                        <?= $ev->date_evaluation
                            ? date('d/m/Y', strtotime($ev->date_evaluation))
                            : '<span class="text-slate-300">—</span>' ?>
                    </td>
                    <td class="px-4 py-3 text-center text-xs font-mono text-slate-600">
                        /<?= number_format((float)$ev->note_max, 0) ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex flex-col items-center gap-1">
                            <span class="inline-flex items-center gap-1 text-xs font-medium
                                  px-2.5 py-1 rounded-full
                                  bg-<?= $color ?>-100 text-<?= $color ?>-700">
                                <i data-lucide="<?= $icon ?>" class="w-3 h-3"></i>
                                <?= $statutLabel ?>
                            </span>
                            <?php if ((int)$ev->notes_saisie_ouverte): ?>
                            <span class="text-xs text-emerald-600 flex items-center gap-0.5">
                                <i data-lucide="pencil" class="w-3 h-3"></i>saisie ouverte
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $ev->id ?>"
                               class="p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded transition"
                               title="Voir">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <?php if ($policy && $policy->canUpdate($user, $ev)): ?>
                            <a href="<?= BASE_URL ?>/v2/academique/evaluations/<?= $ev->id ?>/edit"
                               class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition"
                               title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($policy && $policy->canPublish($user, $ev)): ?>
                            <button onclick="openActionModal(<?= $ev->id ?>, '<?= htmlspecialchars($ev->libelle, ENT_JS) ?>', 'publier')"
                                    class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded transition"
                                    title="Publier">
                                <i data-lucide="send" class="w-4 h-4"></i>
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
        <span><?= $pagination['total'] ?> évaluation(s)</span>
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

<!-- Modal confirmation publication -->
<div id="actionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                <i data-lucide="send" class="w-5 h-5 text-emerald-500"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900">Publier l'évaluation</h3>
                <p id="actionModalName" class="text-sm text-slate-500"></p>
            </div>
        </div>
        <p class="text-sm text-slate-600 mb-5">La saisie des notes sera ouverte pour cette évaluation.</p>
        <form id="actionForm" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary flex-1">Publier</button>
                <button type="button" onclick="closeActionModal()" class="btn btn-secondary flex-1">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(id, nom, action) {
    document.getElementById('actionModalName').textContent = nom;
    document.getElementById('actionForm').action = `<?= BASE_URL ?>/v2/academique/evaluations/${id}/${action}`;
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
