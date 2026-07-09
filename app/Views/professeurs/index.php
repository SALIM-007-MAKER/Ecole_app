<?php
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function pperm(array $p, string $k): bool { return in_array($k, $p, true); }

$pagination = $pagination ?? ['data'=>[],'total'=>0,'page'=>1,'totalPages'=>1,'perPage'=>15];
$filters    = $filters ?? [];
$grades     = $grades  ?? [];
$stats      = $stats   ?? ['total'=>0,'actifs'=>0,'certifies'=>0,'nb_classes'=>0];

function buildQ(array $filters, array $extra = []): string {
    $p = array_filter(array_merge($filters, $extra), fn($v) => $v !== '');
    return $p ? '?' . http_build_query($p) : '';
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="user-check" class="w-5 h-5 text-amber-500"></i>Enseignants
        </h2>
        <p class="text-sm text-slate-500 mt-0.5"><?= $pagination['total'] ?> enseignant(s) enregistré(s)</p>
    </div>
    <?php if (pperm($perms, 'enseignants.create')): ?>
    <a href="<?= BASE_URL ?>/professeurs/create" class="btn btn-success">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvel enseignant
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="user-check" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['actifs'] ?></div>
            <div class="stat-label">Actifs</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="award" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['certifies'] ?></div>
            <div class="stat-label">Certifiés</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['nb_classes'] ?></div>
            <div class="stat-label">Classes</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-4">
    <div class="p-5 p-3">
        <form method="GET" action="<?= BASE_URL ?>/professeurs"
              class="flex flex-wrap items-end gap-3">
            <div class="relative flex-1 min-w-48">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500"><i data-lucide="search" class="w-4 h-4"></i></span>
                <input type="text" name="q" class="form-input pl-9"
                       placeholder="Nom, prénom, spécialité, email…"
                       value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES) ?>">
            </div>
            <select name="grade" class="form-input w-auto min-w-40">
                <option value="">— Tous les grades —</option>
                <?php foreach ($grades as $g): ?>
                <option value="<?= htmlspecialchars($g, ENT_QUOTES) ?>"
                    <?= (($filters['grade'] ?? '') === $g) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="actif" class="form-input w-auto">
                <option value="">Statut</option>
                <option value="1" <?= (($filters['actif'] ?? '') === '1') ? 'selected' : '' ?>>Actif</option>
                <option value="0" <?= (($filters['actif'] ?? '') === '0') ? 'selected' : '' ?>>Inactif</option>
            </select>
            <div class="flex gap-2">
                <button class="btn btn-primary">
                    <i data-lucide="search" class="w-4 h-4"></i>Filtrer
                </button>
                <a href="<?= BASE_URL ?>/professeurs" class="btn btn-secondary p-2 aspect-square" title="Réinitialiser">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-14">
        <div class="w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-3">
            <i data-lucide="user-x" class="w-7 h-7 text-amber-300"></i>
        </div>
        <p class="text-slate-500 font-medium mb-1">Aucun enseignant trouvé</p>
        <p class="text-sm text-slate-400 mb-4">Modifiez vos critères de recherche ou ajoutez un enseignant.</p>
        <?php if (pperm($perms, 'enseignants.create')): ?>
        <a href="<?= BASE_URL ?>/professeurs/create" class="btn btn-success">
            <i data-lucide="plus" class="w-4 h-4"></i>Ajouter le premier
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead><tr>
                <th class="w-12"></th>
                <th>Enseignant</th>
                <th>Grade / Spécialité</th>
                <th>Contact</th>
                <th class="text-center">Classes</th>
                <th class="text-center">Matières</th>
                <th class="text-center">Statut</th>
                <th class="text-right col-actions">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($pagination['data'] as $p): ?>
            <tr>
                <td>
                    <?php if (!empty($p->photo)): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($p->photo, ENT_QUOTES) ?>"
                         class="w-10 h-10 rounded-full object-cover border-2 border-slate-100" alt="">
                    <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center border-2 border-slate-100">
                        <i data-lucide="user" class="w-5 h-5 text-amber-600"></i>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/professeurs/<?= $p->id ?>"
                       class="font-semibold text-slate-800 hover:text-violet-600 transition-colors block">
                        <?= htmlspecialchars($p->nom . ' ' . $p->prenom, ENT_QUOTES) ?>
                    </a>
                    <?php if (!empty($p->date_recrutement)): ?>
                    <span class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                        <i data-lucide="calendar" class="w-3 h-3"></i>
                        Depuis <?= date('Y', strtotime($p->date_recrutement)) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($p->grade)): ?>
                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($p->grade, ENT_QUOTES) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                        <i data-lucide="graduation-cap" class="w-3 h-3"></i>
                        <?= htmlspecialchars($p->specialite, ENT_QUOTES) ?>
                    </p>
                </td>
                <td>
                    <?php if (!empty($p->telephone)): ?>
                    <a href="tel:<?= htmlspecialchars($p->telephone, ENT_QUOTES) ?>"
                       class="text-sm text-slate-600 hover:text-violet-600 flex items-center gap-1.5 mb-0.5">
                        <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                        <?= htmlspecialchars($p->telephone, ENT_QUOTES) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($p->email)): ?>
                    <a href="mailto:<?= htmlspecialchars($p->email, ENT_QUOTES) ?>"
                       class="text-xs text-slate-400 hover:text-violet-600 flex items-center gap-1.5">
                        <i data-lucide="mail" class="w-3 h-3"></i>
                        <?= htmlspecialchars($p->email, ENT_QUOTES) ?>
                    </a>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700"><?= $p->nb_classes ?></span>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= $p->nb_matieres ?></span>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $p->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $p->actif ? 'Actif' : 'Inactif' ?>
                    </span>
                </td>
                <td class="text-right col-actions">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/professeurs/<?= $p->id ?>"
                           class="btn btn-ghost btn-icon text-slate-400 hover:text-slate-700" title="Voir">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <?php if (pperm($perms, 'enseignants.edit')): ?>
                        <a href="<?= BASE_URL ?>/professeurs/<?= $p->id ?>/edit"
                           class="btn btn-ghost btn-icon text-slate-400 hover:text-amber-500" title="Modifier">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (pperm($perms, 'enseignants.delete')): ?>
                        <button class="btn btn-ghost btn-icon text-slate-400 hover:text-red-500"
                                onclick="openDelProf(<?= $p->id ?>, '<?= htmlspecialchars($p->prenom . ' ' . $p->nom, ENT_QUOTES) ?>', <?= $p->nb_classes ?>, <?= $p->nb_matieres ?>)"
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
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
        <p class="text-sm text-slate-400">
            Page <?= $pagination['page'] ?> / <?= $pagination['totalPages'] ?>
            &mdash; <?= $pagination['total'] ?> résultat(s)
        </p>
        <nav class="flex items-center gap-1">
            <?php if ($pagination['page'] > 1): ?>
            <a class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40" href="<?= BASE_URL ?>/professeurs<?= buildQ($filters, ['page' => $pagination['page'] - 1]) ?>">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['totalPages'], $pagination['page'] + 2); $i++): ?>
            <a class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40 <?= $i === $pagination['page'] ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/professeurs<?= buildQ($filters, ['page' => $i]) ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            <?php if ($pagination['page'] < $pagination['totalPages']): ?>
            <a class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-sm font-medium text-slate-600 transition hover:border-slate-200 hover:bg-slate-100 [&.active]:border-violet-600 [&.active]:bg-violet-600 [&.active]:text-white [&.disabled]:pointer-events-none [&.disabled]:opacity-40" href="<?= BASE_URL ?>/professeurs<?= buildQ($filters, ['page' => $pagination['page'] + 1]) ?>">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal suppression unique -->
<?php if (pperm($perms, 'enseignants.delete')): ?>
<div id="delProfModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:420px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-600"></i>
                </span>
                Supprimer l'enseignant
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" onclick="closeDelProf()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600 text-sm">
                Supprimer <strong class="text-slate-900" id="delProfNom"></strong> ? Cette action est irréversible.
            </p>
            <div id="delProfWarn" class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 mt-3 hidden">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                <span class="text-sm">Cet enseignant a des enseignements affectés. Impossible de supprimer.</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" class="btn btn-secondary" onclick="closeDelProf()">Annuler</button>
            <form id="delProfForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" id="delProfSubmit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function openDelProf(id, nom, nbClasses, nbMatieres) {
    document.getElementById('delProfNom').textContent = nom;
    document.getElementById('delProfForm').action = '<?= BASE_URL ?>/professeurs/' + id + '/delete';
    const warn   = document.getElementById('delProfWarn');
    const submit = document.getElementById('delProfSubmit');
    if (nbClasses > 0 || nbMatieres > 0) {
        warn.classList.remove('hidden');
        submit.disabled = true;
        submit.classList.add('opacity-50');
    } else {
        warn.classList.add('hidden');
        submit.disabled = false;
        submit.classList.remove('opacity-50');
    }
    document.getElementById('delProfModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDelProf() {
    document.getElementById('delProfModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>
<?php endif; ?>
