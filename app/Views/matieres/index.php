<?php
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function mperm(array $p, string $k): bool { return in_array($k, $p, true); }
$totalCoef   = $totalCoef   ?? 0;
$totalHeures = $totalHeures ?? 0;
$matieres    = $matieres    ?? [];
?>

<!-- Page header -->
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="book" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900">Matières</h2>
            <p class="text-sm text-slate-500 mt-0.5"><?= count($matieres) ?> matière(s) au programme</p>
        </div>
    </div>
    <?php if (mperm($perms, 'matieres.create')): ?>
    <a href="<?= BASE_URL ?>/matieres/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle matière
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="book" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= count($matieres) ?></div>
            <div class="stat-label">Matières</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="percent" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= number_format($totalCoef, 1) ?></div>
            <div class="stat-label">Total coef.</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="clock" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $totalHeures ?>h</div>
            <div class="stat-label">Volume horaire</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706">
            <i data-lucide="layers" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value">
                <?= array_sum(array_column($matieres, 'nb_enseignements')) ?>
            </div>
            <div class="stat-label">Enseignements</div>
        </div>
    </div>
</div>

<?php if (empty($matieres)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 rounded-xl border border-slate-200 bg-white shadow-sm py-16">
    <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="book" class="w-8 h-8 text-emerald-300"></i>
    </div>
    <p class="text-slate-500 font-medium mb-1">Aucune matière créée</p>
    <p class="text-sm text-slate-400 mb-4">Commencez par ajouter votre première matière.</p>
    <?php if (mperm($perms, 'matieres.create')): ?>
    <a href="<?= BASE_URL ?>/matieres/create" class="btn btn-success">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer la première matière
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="list" class="w-4 h-4 text-slate-400"></i>
        <span class="font-semibold text-slate-700">Liste des matières</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 ml-auto"><?= count($matieres) ?></span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
            <thead><tr>
                <th class="w-8">#</th>
                <th>Matière</th>
                <th class="text-center">Coefficient</th>
                <th class="text-center">H/semaine</th>
                <th>Responsable</th>
                <th class="text-center">Enseignements</th>
                <th class="text-right col-actions">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($matieres as $i => $m): ?>
            <tr>
                <td class="text-slate-400 text-sm"><?= $i + 1 ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/matieres/<?= $m->id ?>"
                       class="font-semibold text-slate-800 hover:text-emerald-600 transition-colors flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                            <i data-lucide="book" class="w-3.5 h-3.5 text-emerald-500"></i>
                        </span>
                        <?= htmlspecialchars($m->nom, ENT_QUOTES) ?>
                    </a>
                    <?php if (!empty($m->description)): ?>
                    <p class="text-xs text-slate-400 mt-0.5 ml-9">
                        <?= htmlspecialchars(mb_substr($m->description, 0, 60), ENT_QUOTES) ?><?= mb_strlen($m->description) > 60 ? '…' : '' ?>
                    </p>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= number_format($m->coefficient, 1) ?></span>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700"><?= $m->volume_horaire ?>h</span>
                </td>
                <td class="text-sm text-slate-600">
                    <?php if (!empty($m->responsable_nom) && trim($m->responsable_nom) !== ' '): ?>
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                        <?= htmlspecialchars($m->responsable_nom, ENT_QUOTES) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= $m->nb_enseignements ?></span>
                </td>
                <td class="text-right col-actions">
                    <div class="flex items-center justify-end gap-1">
                        <a href="<?= BASE_URL ?>/matieres/<?= $m->id ?>"
                           class="btn btn-ghost btn-icon text-slate-400 hover:text-slate-700" title="Détail">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        <?php if (mperm($perms, 'matieres.edit')): ?>
                        <a href="<?= BASE_URL ?>/matieres/<?= $m->id ?>/edit"
                           class="btn btn-ghost btn-icon text-slate-400 hover:text-amber-500" title="Modifier">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (mperm($perms, 'matieres.delete')): ?>
                        <button class="btn btn-ghost btn-icon text-slate-400 hover:text-red-500"
                                onclick="openDelMat(<?= $m->id ?>, '<?= htmlspecialchars($m->nom, ENT_QUOTES) ?>', <?= $m->nb_enseignements ?>)"
                                title="Supprimer">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 font-semibold text-slate-700 text-sm">
                    <td class="px-4 py-2.5" colspan="2">Totaux</td>
                    <td class="text-center text-violet-600 px-4 py-2.5"><?= number_format($totalCoef, 1) ?></td>
                    <td class="text-center text-sky-600 px-4 py-2.5"><?= $totalHeures ?>h</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- Modal suppression unique -->
<?php if (mperm($perms, 'matieres.delete')): ?>
<div id="delMatModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:420px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-600"></i>
                </span>
                Supprimer la matière
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" onclick="closeDelMat()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600 text-sm">
                Voulez-vous supprimer <strong class="text-slate-900" id="delMatNom"></strong> ?
            </p>
            <div id="delMatWarn" class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 mt-3 hidden">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                <span id="delMatWarnTxt" class="text-sm"></span>
            </div>
            <div id="delMatDanger" class="alert alert-danger mt-3 hidden">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                <span class="text-sm">Cette action est irréversible. Les notes liées seront supprimées.</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" class="btn btn-secondary" onclick="closeDelMat()">Annuler</button>
            <form id="delMatForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" id="delMatSubmit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function openDelMat(id, nom, nbEns) {
    document.getElementById('delMatNom').textContent = nom;
    document.getElementById('delMatForm').action = '<?= BASE_URL ?>/matieres/' + id + '/delete';
    const warn    = document.getElementById('delMatWarn');
    const danger  = document.getElementById('delMatDanger');
    const submit  = document.getElementById('delMatSubmit');
    if (nbEns > 0) {
        document.getElementById('delMatWarnTxt').textContent =
            'Cette matière est utilisée dans ' + nbEns + ' enseignement(s). Impossible de supprimer.';
        warn.classList.remove('hidden');
        danger.classList.add('hidden');
        submit.disabled = true;
        submit.classList.add('opacity-50');
    } else {
        warn.classList.add('hidden');
        danger.classList.remove('hidden');
        submit.disabled = false;
        submit.classList.remove('opacity-50');
    }
    document.getElementById('delMatModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDelMat() {
    document.getElementById('delMatModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>
<?php endif; ?>
