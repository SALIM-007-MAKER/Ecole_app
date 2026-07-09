<?php
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function cperm(array $p, string $k): bool { return in_array($k, $p, true); }
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="building-2" class="w-5 h-5 text-violet-600"></i>Classes
        </h2>
        <p class="text-sm text-slate-500 mt-0.5"><?= count($classes) ?> classe(s) enregistrée(s)</p>
    </div>
    <?php if (cperm($perms, 'classes.create')): ?>
    <a href="<?= BASE_URL ?>/classes/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Nouvelle classe
    </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">
            <i data-lucide="building-2" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-label">Classes</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#dcfce7;color:#16a34a">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['total_eleves'] ?? 0 ?></div>
            <div class="stat-label">Élèves affectés</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">
            <i data-lucide="book-open" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['total_ens'] ?? 0 ?></div>
            <div class="stat-label">Enseignements</div>
        </div>
    </div>
    <div class="stat-card-v">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706">
            <i data-lucide="layers" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-value"><?= $stats['niveaux'] ?? 0 ?></div>
            <div class="stat-label">Niveaux</div>
        </div>
    </div>
</div>

<?php if (empty($classes)): ?>
<div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500 rounded-xl border border-slate-200 bg-white shadow-sm py-16">
    <div class="w-16 h-16 rounded-2xl bg-violet-50 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="building-2" class="w-8 h-8 text-violet-300"></i>
    </div>
    <p class="text-slate-500 font-medium mb-1">Aucune classe créée</p>
    <p class="text-sm text-slate-400 mb-4">Commencez par créer votre première classe.</p>
    <?php if (cperm($perms, 'classes.create')): ?>
    <a href="<?= BASE_URL ?>/classes/create" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>Créer la première classe
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<?php
$grouped = [];
foreach ($classes as $c) { $grouped[$c->niveau][] = $c; }
ksort($grouped);
?>

<?php foreach ($grouped as $niveau => $group): ?>
<div class="mb-8">
    <div class="flex items-center gap-3 mb-3">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">
            <?= htmlspecialchars($niveau, ENT_QUOTES) ?>
        </span>
        <div class="h-px flex-1 bg-slate-100"></div>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= count($group) ?></span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($group as $classe): ?>
        <?php
        $pct      = $classe->max_eleves > 0
            ? min(100, round($classe->nb_eleves / $classe->max_eleves * 100)) : 0;
        $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#22c55e');
        $barBg    = $pct >= 90 ? '#fee2e2' : ($pct >= 70 ? '#fef3c7' : '#dcfce7');
        ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md flex flex-col group">
            <div class="p-5 p-4 flex-1">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="page-icon"
                             style="background:#ede9fe">
                            <i data-lucide="building-2" class="w-4 h-4" style="color:#7c3aed"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 leading-tight">
                                <?= htmlspecialchars($classe->niveau, ENT_QUOTES) ?>
                                <span class="text-violet-600"><?= htmlspecialchars($classe->nom, ENT_QUOTES) ?></span>
                            </h3>
                            <p class="text-xs text-slate-400">
                                <?= htmlspecialchars($classe->annee_scolaire, ENT_QUOTES) ?>
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 text-xs shrink-0">
                        <?= $classe->nb_eleves ?>/<?= $classe->max_eleves ?>
                    </span>
                </div>

                <!-- Barre de remplissage -->
                <div class="mb-3">
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="text-slate-400"><?= $classe->nb_eleves ?> élève(s)</span>
                        <span class="font-semibold" style="color:<?= $barColor ?>"><?= $pct ?>%</span>
                    </div>
                    <div class="h-1.5 rounded-full overflow-hidden" style="background:<?= $barBg ?>">
                        <div class="h-full rounded-full transition-all duration-300"
                             style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                    </div>
                </div>

                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span class="flex items-center gap-1">
                        <i data-lucide="book-open" class="w-3 h-3"></i>
                        <?= $classe->nb_enseignements ?> matière(s)
                    </span>
                    <span class="flex items-center gap-1">
                        <i data-lucide="users" class="w-3 h-3"></i>
                        max <?= $classe->max_eleves ?>
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 bg-slate-50/80 flex items-center gap-1 py-2 px-3 border-t border-slate-100">
                <a href="<?= BASE_URL ?>/classes/<?= $classe->id ?>"
                   class="btn btn-ghost flex-1 justify-center text-xs text-slate-600 hover:text-violet-600">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>Détail
                </a>
                <?php if (cperm($perms, 'classes.edit')): ?>
                <a href="<?= BASE_URL ?>/classes/<?= $classe->id ?>/edit"
                   class="btn btn-ghost btn-icon text-slate-400 hover:text-amber-500" title="Modifier">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                </a>
                <?php endif; ?>
                <?php if (cperm($perms, 'classes.delete')): ?>
                <button class="btn btn-ghost btn-icon text-slate-400 hover:text-red-500"
                        onclick="openDelModal(<?= $classe->id ?>, '<?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>', <?= $classe->nb_eleves ?>)"
                        title="Supprimer">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- Modal suppression unique -->
<?php if (cperm($perms, 'classes.delete')): ?>
<div id="delModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:420px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-600"></i>
                </span>
                Supprimer la classe
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" onclick="closeDelModal()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600 text-sm">
                Voulez-vous supprimer <strong class="text-slate-900" id="delNom"></strong> ? Cette action est irréversible.
            </p>
            <div id="delWarning" class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 mt-3 hidden">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-amber-600"></i>
                <span id="delWarningText" class="text-sm"></span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button type="button" class="btn btn-secondary" onclick="closeDelModal()">Annuler</button>
            <form id="delForm" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit" id="delSubmit" class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function openDelModal(id, nom, nbEleves) {
    document.getElementById('delNom').textContent = nom;
    document.getElementById('delForm').action = '<?= BASE_URL ?>/classes/' + id + '/delete';
    const warn = document.getElementById('delWarning');
    const sub  = document.getElementById('delSubmit');
    if (nbEleves > 0) {
        document.getElementById('delWarningText').textContent =
            'Cette classe contient ' + nbEleves + ' élève(s). Impossible de supprimer.';
        warn.classList.remove('hidden');
        sub.disabled = true;
        sub.classList.add('opacity-50');
    } else {
        warn.classList.add('hidden');
        sub.disabled = false;
        sub.classList.remove('opacity-50');
    }
    document.getElementById('delModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDelModal() {
    document.getElementById('delModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>
<?php endif; ?>
