<?php
$filters    = $filters    ?? [];
$pagination = $pagination ?? ['data'=>[],'total'=>0,'last_page'=>1,'current_page'=>1];
$classes    = $classes    ?? [];
$stats      = $stats      ?? [];
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function ep(array $p, string $k): bool { return in_array($k, $p, true); }

function buildQuery(array $base, array $override = []): string {
    $p = array_merge($base, $override);
    $p = array_filter($p, fn($v) => $v !== '');
    return $p ? '?' . http_build_query($p) : '';
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="users" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900">Gestion des élèves</h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= $stats['total'] ?? 0 ?> élève(s) enregistré(s)</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (ep($perms,'eleves.create')): ?>
        <a href="<?= BASE_URL ?>/eleves/create" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Nouvel élève
        </a>
        <a href="<?= BASE_URL ?>/eleves/import" class="btn btn-outline">
            <i data-lucide="upload" class="w-4 h-4"></i>Importer CSV
        </a>
        <?php endif; ?>
        <?php if (ep($perms,'eleves.view')): ?>
        <a href="<?= BASE_URL ?>/eleves/export/excel<?= buildQuery($filters) ?>" class="btn btn-ghost">
            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-600"></i>Excel
        </a>
        <a href="<?= BASE_URL ?>/eleves/export/pdf<?= buildQuery($filters) ?>" class="btn btn-ghost" target="_blank">
            <i data-lucide="file-text" class="w-4 h-4 text-red-500"></i>PDF
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon bg-violet-100">
            <i data-lucide="users" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-label">Total élèves</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon bg-sky-100">
            <i data-lucide="user" class="w-5 h-5 text-sky-600"></i>
        </div>
        <div>
            <div class="stat-value text-sky-600"><?= $stats['garcons'] ?? 0 ?></div>
            <div class="stat-label">Garçons</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon bg-pink-100">
            <i data-lucide="user" class="w-5 h-5 text-pink-500"></i>
        </div>
        <div>
            <div class="stat-value text-pink-500"><?= $stats['filles'] ?? 0 ?></div>
            <div class="stat-label">Filles</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon bg-emerald-100">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
        </div>
        <div>
            <div class="stat-value text-emerald-600"><?= $stats['actifs'] ?? ($stats['total'] ?? 0) ?></div>
            <div class="stat-label">Actifs</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" action="<?= BASE_URL ?>/eleves"
              class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-40">
                <label class="form-label text-xs mb-1">Rechercher</label>
                <div class="relative flex items-stretch">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="search" class="w-4 h-4"></i></span>
                    <input type="text" name="q" class="form-input pl-9 text-sm"
                           value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES) ?>"
                           placeholder="Nom, prénom, matricule…">
                </div>
            </div>
            <div class="w-44">
                <label class="form-label text-xs mb-1">Classe</label>
                <select name="classe_id" class="form-input text-sm">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c->id ?>" <?= ($filters['classe_id'] ?? '') == $c->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c->label, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-32">
                <label class="form-label text-xs mb-1">Sexe</label>
                <select name="sexe" class="form-input text-sm">
                    <option value="">Tous</option>
                    <option value="M" <?= ($filters['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Masculin</option>
                    <option value="F" <?= ($filters['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Féminin</option>
                </select>
            </div>
            <div class="w-32">
                <label class="form-label text-xs mb-1">Statut</label>
                <select name="actif" class="form-input text-sm">
                    <option value="1"   <?= ($filters['actif'] ?? '1') === '1'  ? 'selected' : '' ?>>Actifs</option>
                    <option value="0"   <?= ($filters['actif'] ?? '') === '0'   ? 'selected' : '' ?>>Inactifs</option>
                    <option value="all" <?= ($filters['actif'] ?? '') === 'all' ? 'selected' : '' ?>>Tous</option>
                </select>
            </div>
            <div class="flex items-end gap-1.5">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>Filtrer
                </button>
                <a href="<?= BASE_URL ?>/eleves" class="btn btn-ghost p-2 aspect-square" title="Réinitialiser les filtres">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <div class="flex items-center gap-2">
            <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Liste des élèves</span>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-auto"><?= $pagination['total'] ?? 0 ?> résultat(s)</span>
    </div>

    <?php if (empty($pagination['data'])): ?>
    <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
        <i data-lucide="users" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
        <p class="text-slate-500 font-medium mb-1">Aucun élève trouvé</p>
        <p class="text-sm text-slate-400 mb-4">Essayez de modifier vos filtres ou ajoutez un nouvel élève.</p>
        <?php if (ep($perms,'eleves.create')): ?>
        <a href="<?= BASE_URL ?>/eleves/create" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Ajouter un élève
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead>
                <tr>
                    <th style="width:48px">Photo</th>
                    <th>Matricule</th>
                    <th>Nom &amp; Prénom</th>
                    <th>Sexe</th>
                    <th>Naissance</th>
                    <th>Classe</th>
                    <th>Parent responsable</th>
                    <th>Téléphone</th>
                    <th class="text-center">Statut</th>
                    <th class="text-right col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pagination['data'] as $e): ?>
            <tr>
                <td>
                    <?php if (!empty($e->photo)): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($e->photo, ENT_QUOTES) ?>"
                         class="w-9 h-9 rounded-full object-cover border border-slate-200 shadow-sm" alt="">
                    <?php else: ?>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center border
                                <?= $e->sexe === 'M' ? 'bg-sky-50 border-sky-100' : 'bg-pink-50 border-pink-100' ?>">
                        <i data-lucide="user" class="w-4 h-4 <?= $e->sexe === 'M' ? 'text-sky-500' : 'text-pink-500' ?>"></i>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="font-mono text-xs text-slate-400 tracking-wide">
                        <?= htmlspecialchars($e->matricule, ENT_QUOTES) ?>
                    </span>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/eleves/<?= $e->id ?>"
                       class="font-semibold text-slate-800 hover:text-violet-600 transition-colors">
                        <?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>
                    </a>
                </td>
                <td>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $e->sexe === 'M' ? 'bg-sky-100 text-sky-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $e->sexe === 'M' ? 'M' : 'F' ?>
                    </span>
                </td>
                <td class="text-sm text-slate-500">
                    <?= $e->date_naissance ? date('d/m/Y', strtotime($e->date_naissance)) : '—' ?>
                </td>
                <td>
                    <?php if ($e->classe_nom): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700">
                        <?= htmlspecialchars($e->classe_niveau . ' ' . $e->classe_nom, ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300 text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($e->parent_nom) && trim($e->parent_nom) !== ''): ?>
                    <div class="min-w-40">
                        <div class="font-medium text-slate-700">
                            <?= htmlspecialchars($e->parent_nom, ENT_QUOTES) ?>
                        </div>
                        <?php if (!empty($e->parent_telephone)): ?>
                        <a href="tel:<?= htmlspecialchars($e->parent_telephone, ENT_QUOTES) ?>"
                           class="text-xs text-slate-500 hover:text-violet-600">
                            <?= htmlspecialchars($e->parent_telephone, ENT_QUOTES) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-slate-400 text-xs">Non affecté</span>
                    <?php endif; ?>
                </td>
                <td class="text-sm text-slate-500">
                    <?= htmlspecialchars($e->telephone ?? '—', ENT_QUOTES) ?>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $e->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $e->actif ? 'Actif' : 'Inactif' ?>
                    </span>
                </td>
                <td class="text-right col-actions">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/eleves/<?= $e->id ?>"
                           class="btn btn-ghost btn-icon text-slate-500" title="Voir la fiche">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <?php if (ep($perms,'eleves.edit')): ?>
                        <a href="<?= BASE_URL ?>/eleves/<?= $e->id ?>/edit"
                           class="btn btn-ghost btn-icon text-slate-500" title="Modifier">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (ep($perms,'eleves.delete')): ?>
                        <button class="btn btn-ghost btn-icon text-red-400 hover:text-red-600"
                                data-modal-target="deleteModal"
                                data-delete-id="<?= $e->id ?>"
                                data-delete-nom="<?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>"
                                data-delete-url="<?= BASE_URL ?>/eleves/<?= $e->id ?>/delete"
                                onclick="confirmDelete(this)"
                                title="Supprimer">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
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
    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
        <span class="text-xs text-slate-400">
            Page <?= $pagination['current_page'] ?> / <?= $pagination['last_page'] ?>
            &mdash; <?= $pagination['total'] ?> résultat(s)
        </span>
        <nav class="flex items-center gap-1">
            <?php
            $cur  = $pagination['current_page'];
            $last = $pagination['last_page'];
            $win  = range(max(1, $cur - 2), min($last, $cur + 2));
            ?>
            <a href="<?= BASE_URL ?>/eleves<?= buildQuery($filters, ['page' => $cur - 1]) ?>"
               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $cur <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">‹</a>

            <?php if ($win[0] > 1): ?>
            <a href="<?= BASE_URL ?>/eleves<?= buildQuery($filters, ['page' => 1]) ?>" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40">1</a>
            <?php if ($win[0] > 2): ?><span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 pointer-events-none">…</span><?php endif; ?>
            <?php endif; ?>

            <?php foreach ($win as $pg): ?>
            <a href="<?= BASE_URL ?>/eleves<?= buildQuery($filters, ['page' => $pg]) ?>"
               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $pg === $cur ? 'active' : '' ?>"><?= $pg ?></a>
            <?php endforeach; ?>

            <?php if (end($win) < $last): ?>
            <?php if (end($win) < $last - 1): ?><span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 pointer-events-none">…</span><?php endif; ?>
            <a href="<?= BASE_URL ?>/eleves<?= buildQuery($filters, ['page' => $last]) ?>" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40"><?= $last ?></a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/eleves<?= buildQuery($filters, ['page' => $cur + 1]) ?>"
               class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $cur >= $last ? 'opacity-40 pointer-events-none' : '' ?>">›</a>
        </nav>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Modal suppression -->
<?php if (ep($perms,'eleves.delete')): ?>
<div id="deleteModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:440px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2 text-red-600">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                Confirmer la suppression
            </h3>
            <button class="btn btn-ghost btn-icon text-slate-400" data-modal-close>
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-700">
                Êtes-vous sûr de vouloir supprimer l'élève
                <strong id="deleteNomLabel" class="text-slate-900"></strong> ?
            </p>
            <div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 mt-3" role="alert">
                <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                <span class="text-sm">Cette action supprimera également toutes les notes et absences associées.</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(btn) {
    document.getElementById('deleteNomLabel').textContent = btn.dataset.deleteNom;
    document.getElementById('deleteForm').action = btn.dataset.deleteUrl;
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteModal').classList.remove('active');
        document.body.style.overflow = '';
    });
});
</script>
<?php endif; ?>
