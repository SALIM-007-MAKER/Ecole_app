<?php
$perms   = $perms   ?? [];
$stats   = $stats   ?? [];
$filters = $filters ?? [];
$classes = $classes ?? [];
$pagination = $pagination ?? ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1];

function ep2(array $p, string $k): bool { return in_array($k, $p, true); }
function photoUrlV2(string $photo): string {
    if (str_starts_with($photo, 'storage/')) {
        $parts    = explode('/', $photo);
        $filename = array_pop($parts);
        $typeDir  = array_pop($parts);
        return BASE_URL . '/uploads/serve/' . $typeDir . '/' . $filename;
    }
    return BASE_URL . '/' . $photo;
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="users" class="w-5 h-5 text-violet-600"></i>
            Élèves
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Gestion des élèves</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (ep2($perms,'eleves.view')): ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/export/pdf?<?= http_build_query($filters) ?>"
           target="_blank" class="btn btn-ghost">
            <i data-lucide="file-text" class="w-4 h-4"></i>PDF
        </a>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/export/excel?<?= http_build_query($filters) ?>"
           class="btn btn-ghost">
            <i data-lucide="table-2" class="w-4 h-4"></i>Excel
        </a>
        <?php endif; ?>
        <?php if (ep2($perms,'eleves.create')): ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/import" class="btn btn-secondary">
            <i data-lucide="upload" class="w-4 h-4"></i>Import CSV
        </a>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/create" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Ajouter un élève
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = \Core\Session::getFlash('success')): ?>
<div class="alert alert-success mb-4" role="alert">
    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
    <span><?= htmlspecialchars($flash, ENT_QUOTES) ?></span>
    <button class="ml-auto" onclick="this.closest('[role=alert]').remove()"><i data-lucide="x" class="w-4 h-4"></i></button>
</div>
<?php endif; ?>

<!-- Statistiques -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Total', 'value' => $stats['total'] ?? 0, 'color' => 'violet', 'icon' => 'users'],
        ['label' => 'Actifs', 'value' => $stats['actifs'] ?? 0, 'color' => 'emerald', 'icon' => 'user-check'],
        ['label' => 'Garçons', 'value' => $stats['garcons'] ?? 0, 'color' => 'sky', 'icon' => 'user'],
        ['label' => 'Filles', 'value' => $stats['filles'] ?? 0, 'color' => 'pink', 'icon' => 'user'],
    ];
    foreach ($cards as $card): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-<?= $card['color'] ?>-100 flex items-center justify-center">
                <i data-lucide="<?= $card['icon'] ?>" class="w-5 h-5 text-<?= $card['color'] ?>-600"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= $card['value'] ?></p>
                <p class="text-xs text-slate-500"><?= $card['label'] ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5">
        <form method="GET" action="<?= BASE_URL ?>/v2/scolarite/eleves"
              class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-40">
                <label class="form-label text-xs mb-1">Rechercher</label>
                <div class="relative flex items-stretch">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
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
                <a href="<?= BASE_URL ?>/v2/scolarite/eleves" class="btn btn-ghost p-2 aspect-square" title="Réinitialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
        <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>
        <span class="font-semibold text-slate-700 text-sm">Liste des élèves</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600 ml-auto">
            <?= $pagination['total'] ?? 0 ?> résultat(s)
        </span>
    </div>

    <?php if (empty($pagination['data'])): ?>
    <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-16">
        <i data-lucide="users" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
        <p class="text-slate-500 font-medium mb-1">Aucun élève trouvé</p>
        <p class="text-sm text-slate-400 mb-4">Essayez de modifier vos filtres ou ajoutez un nouvel élève.</p>
        <?php if (ep2($perms,'eleves.create')): ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/create" class="btn btn-primary">
            <i data-lucide="user-plus" class="w-4 h-4"></i>Ajouter un élève
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead>
                <tr>
                    <th style="width:48px">Photo</th>
                    <th>Matricule</th>
                    <th>Nom &amp; Prénom</th>
                    <th>Sexe</th>
                    <th>Naissance</th>
                    <th>Classe</th>
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
                    <img src="<?= photoUrlV2($e->photo) ?>"
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
                    <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $e->id ?>"
                       class="font-semibold text-slate-800 hover:text-violet-600 transition-colors">
                        <?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>
                    </a>
                </td>
                <td>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $e->sexe === 'M' ? 'bg-sky-100 text-sky-700' : 'bg-red-100 text-red-700' ?>">
                        <?= $e->sexe === 'M' ? 'M' : 'F' ?>
                    </span>
                </td>
                <td class="text-xs text-slate-500">
                    <?= $e->date_naissance ? date('d/m/Y', strtotime($e->date_naissance)) : '—' ?>
                </td>
                <td>
                    <?php if ($e->classe_nom): ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-violet-100 text-violet-700">
                        <?= htmlspecialchars($e->classe_niveau . ' ' . $e->classe_nom, ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-xs text-slate-400">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-xs text-slate-500">
                    <?= htmlspecialchars($e->telephone ?? '', ENT_QUOTES) ?: '—' ?>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $e->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $e->actif ? 'Actif' : 'Inactif' ?>
                    </span>
                </td>
                <td class="text-right col-actions">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $e->id ?>"
                           class="btn btn-ghost p-1.5" title="Voir la fiche">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <?php if (ep2($perms,'eleves.update')): ?>
                        <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $e->id ?>/edit"
                           class="btn btn-ghost p-1.5" title="Modifier">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (ep2($perms,'eleves.delete')): ?>
                        <button type="button"
                                onclick="openArchiveModal(<?= $e->id ?>, '<?= htmlspecialchars(addslashes($e->nom . ' ' . $e->prenom), ENT_QUOTES) ?>')"
                                class="btn btn-ghost p-1.5 text-amber-500" title="Archiver">
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
    <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
        <p class="text-sm text-slate-500">
            Page <?= $pagination['current_page'] ?> / <?= $pagination['last_page'] ?>
            — <?= $pagination['total'] ?> élève(s)
        </p>
        <div class="flex items-center gap-1">
            <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
            <?php $isActive = $p === $pagination['current_page']; ?>
            <a href="?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"
               class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm
                      <?= $isActive ? 'bg-violet-600 text-white font-bold' : 'text-slate-600 hover:bg-slate-100' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal archivage -->
<?php if (ep2($perms,'eleves.delete')): ?>
<div id="archiveModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <i data-lucide="archive" class="w-5 h-5 text-amber-500"></i>
            <h3 class="text-base font-semibold text-slate-900" id="archiveModalTitle">Archiver l'élève</h3>
            <button class="ml-auto btn btn-ghost btn-icon text-slate-400"
                    onclick="closeArchiveModal()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="archiveForm" method="POST" action="">
            <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
            <input type="hidden" name="action" value="archive">
            <div class="px-5 py-5">
                <p class="text-slate-700 mb-3">
                    Archiver <strong class="text-slate-900" id="archiveModalName"></strong> ?
                    L'élève restera visible dans le système mais n'apparaîtra plus dans les listes actives.
                </p>
                <div class="mb-4">
                    <label class="form-label" for="archiveMotif">Motif (optionnel)</label>
                    <input type="text" id="archiveMotif" name="motif" class="form-input"
                           placeholder="Ex : Transfert, Départ…">
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <button type="button" class="btn btn-secondary" onclick="closeArchiveModal()">Annuler</button>
                <button type="submit" class="btn btn-warning">
                    <i data-lucide="archive" class="w-4 h-4"></i>Archiver
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openArchiveModal(id, name) {
    document.getElementById('archiveForm').action = '<?= BASE_URL ?>/v2/scolarite/eleves/' + id + '/delete';
    document.getElementById('archiveModalName').textContent = name;
    document.getElementById('archiveModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeArchiveModal() {
    document.getElementById('archiveModal').classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('archiveMotif').value = '';
}
</script>
