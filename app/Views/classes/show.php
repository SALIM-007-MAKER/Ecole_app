<?php
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function showcp(array $p, string $k): bool { return in_array($k, $p, true); }
$activeTab   = $activeTab ?? 'eleves';
$pct = $classe->max_eleves > 0
    ? min(100, round($classe->nb_eleves / $classe->max_eleves * 100)) : 0;
$barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#22c55e');
$annee    = date('Y') . '-' . (date('Y') + 1);
?>

<!-- Breadcrumb + actions -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <nav class="flex items-center gap-2 text-sm text-slate-500">
        <a href="<?= BASE_URL ?>/classes" class="hover:text-violet-600 transition-colors">Classes</a>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span class="text-slate-800 font-semibold">
            <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
        </span>
    </nav>
    <div class="flex items-center gap-2">
        <?php if (showcp($perms, 'classes.edit')): ?>
        <a href="<?= BASE_URL ?>/classes/<?= $classe->id ?>/edit" class="btn btn-warning">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/classes" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
        <?php if (showcp($perms, 'classes.delete') && $classe->nb_eleves == 0): ?>
        <button class="btn btn-outline text-red-500 border-red-200 hover:bg-red-50"
                onclick="document.getElementById('delModal').classList.add('active');document.body.style.overflow='hidden'">
            <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Carte identité classe -->
<div class="rounded-xl border border-slate-200 bg-white shadow-sm mb-5">
    <div class="p-5 p-5">
        <div class="flex flex-wrap items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-violet-50 flex items-center justify-center shrink-0">
                <i data-lucide="building-2" class="w-7 h-7 text-violet-600"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-slate-900">
                    <?= htmlspecialchars($classe->niveau, ENT_QUOTES) ?>
                    <span class="text-violet-600"><?= htmlspecialchars($classe->nom, ENT_QUOTES) ?></span>
                </h3>
                <p class="text-sm text-slate-400 mt-0.5 flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    Année scolaire <?= htmlspecialchars($classe->annee_scolaire, ENT_QUOTES) ?>
                </p>
                <?php if (!empty($classe->description)): ?>
                <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($classe->description, ENT_QUOTES) ?></p>
                <?php endif; ?>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">
                        <i data-lucide="users" class="w-3 h-3 mr-1"></i>
                        <?= $classe->nb_eleves ?> élève(s)
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700">
                        <i data-lucide="book-open" class="w-3 h-3 mr-1"></i>
                        <?= $classe->nb_enseignements ?> enseignement(s)
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">max <?= $classe->max_eleves ?></span>
                </div>
            </div>
            <div class="w-44 shrink-0">
                <div class="flex justify-between text-xs mb-1.5">
                    <span class="text-slate-400">Remplissage</span>
                    <span class="font-bold" style="color:<?= $barColor ?>"><?= $pct ?>%</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all"
                         style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                </div>
                <p class="text-xs text-center text-slate-400 mt-1.5">
                    <?= $classe->nb_eleves ?> / <?= $classe->max_eleves ?> places
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="flex items-center gap-1 mb-5 border-b border-slate-100">
    <button id="tab-btn-eleves"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                   <?= $activeTab !== 'enseignants' ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-500 hover:text-slate-700' ?>"
            onclick="switchTab('eleves')">
        <i data-lucide="users" class="w-4 h-4 inline-block mr-1.5"></i>
        Élèves <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700 ml-1"><?= count($eleves) ?></span>
    </button>
    <button id="tab-btn-enseignants"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                   <?= $activeTab === 'enseignants' ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-500 hover:text-slate-700' ?>"
            onclick="switchTab('enseignants')">
        <i data-lucide="book-open" class="w-4 h-4 inline-block mr-1.5"></i>
        Enseignements <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700 ml-1"><?= count($enseignements) ?></span>
    </button>
</div>

<!-- Tab: Élèves -->
<div id="tab-eleves" class="<?= $activeTab === 'enseignants' ? 'hidden' : '' ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="users" class="w-4 h-4 text-emerald-500"></i>
                <span class="font-semibold text-slate-700">Élèves de la classe</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700 ml-auto"><?= count($eleves) ?></span>
            </div>
            <?php if (empty($eleves)): ?>
            <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-10">
                <i data-lucide="users" class="w-10 h-10 text-slate-300 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400">Aucun élève affecté à cette classe.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                    <thead><tr>
                        <th class="w-8">#</th>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th class="text-center">Sexe</th>
                        <th class="text-center">Statut</th>
                        <?php if (showcp($perms, 'classes.edit')): ?><th class="text-center w-16">Action</th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($eleves as $i => $e): ?>
                    <tr>
                        <td class="text-slate-400 text-sm"><?= $i + 1 ?></td>
                        <td class="font-mono text-xs text-slate-400"><?= htmlspecialchars($e->matricule, ENT_QUOTES) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/eleves/<?= $e->id ?>"
                               class="font-semibold text-slate-800 hover:text-violet-600 transition-colors">
                                <?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $e->sexe === 'M' ? 'bg-sky-100 text-sky-700' : 'bg-red-100 text-red-700' ?> text-xs">
                                <?= $e->sexe ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $e->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                <?= $e->actif ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <?php if (showcp($perms, 'classes.edit')): ?>
                        <td class="text-center">
                            <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classe->id ?>/retirer-eleve"
                                  onsubmit="return confirm('Retirer <?= htmlspecialchars($e->prenom . ' ' . $e->nom, ENT_QUOTES) ?> de cette classe ?')">
                                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                <input type="hidden" name="eleve_id" value="<?= $e->id ?>">
                                <button class="btn btn-ghost btn-icon text-red-400 hover:text-red-600" type="submit" title="Retirer">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (showcp($perms, 'classes.edit')): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="user-plus" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Affecter un élève</span>
            </div>
            <div class="p-5 p-4">
                <?php if (empty($disponibles)): ?>
                <div class="flex items-start gap-2 text-sm text-slate-400">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0"></i>
                    Tous les élèves disponibles sont déjà affectés.
                </div>
                <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classe->id ?>/affecter-eleve">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <div class="mb-3">
                        <label class="form-label" for="eleve_id">Sélectionner l'élève</label>
                        <select id="eleve_id" name="eleve_id" class="form-input text-sm" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($disponibles as $d): ?>
                            <option value="<?= $d->id ?>">
                                <?= htmlspecialchars($d->nom . ' ' . $d->prenom, ENT_QUOTES) ?>
                                <?php if ($d->classe_actuelle): ?>
                                    (<?= htmlspecialchars($d->niveau_actuel . ' ' . $d->classe_actuelle, ENT_QUOTES) ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-full px-2.5 py-1.5 text-xs rounded-md">
                        <i data-lucide="user-check" class="w-4 h-4"></i>Affecter à cette classe
                    </button>
                    <p class="text-xs text-slate-400 mt-2 text-center">
                        <?= count($eleves) ?> / <?= $classe->max_eleves ?> places occupées
                    </p>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Tab: Enseignements -->
<div id="tab-enseignants" class="<?= $activeTab !== 'enseignants' ? 'hidden' : '' ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="book-open" class="w-4 h-4 text-sky-500"></i>
                <span class="font-semibold text-slate-700">Enseignements affectés</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700 ml-auto"><?= count($enseignements) ?></span>
            </div>
            <?php if (empty($enseignements)): ?>
            <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-10">
                <i data-lucide="book-open" class="w-10 h-10 text-slate-300 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400">Aucun enseignement affecté.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                    <thead><tr>
                        <th>Matière</th>
                        <th class="text-center">Coef.</th>
                        <th class="text-center">H/sem</th>
                        <th>Professeur</th>
                        <th>Année</th>
                        <?php if (showcp($perms, 'classes.edit')): ?><th class="text-center w-16">Action</th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($enseignements as $en): ?>
                    <tr>
                        <td class="font-semibold text-slate-800"><?= htmlspecialchars($en->matiere_nom, ENT_QUOTES) ?></td>
                        <td class="text-center"><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700"><?= number_format($en->coefficient, 1) ?></span></td>
                        <td class="text-center text-sm text-slate-500"><?= $en->volume_horaire ?>h</td>
                        <td class="text-sm text-slate-600"><?= htmlspecialchars($en->prof_nom, ENT_QUOTES) ?></td>
                        <td class="text-sm text-slate-400"><?= htmlspecialchars($en->annee_scolaire, ENT_QUOTES) ?></td>
                        <?php if (showcp($perms, 'classes.edit')): ?>
                        <td class="text-center">
                            <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classe->id ?>/retirer-enseignant"
                                  onsubmit="return confirm('Retirer cet enseignement ?')">
                                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                <input type="hidden" name="enseignement_id" value="<?= $en->id ?>">
                                <button class="btn btn-ghost btn-icon text-red-400 hover:text-red-600" type="submit" title="Retirer">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold text-slate-700 text-sm">
                            <td class="px-4 py-2.5">Total</td>
                            <td class="text-center text-violet-600 px-4 py-2.5">
                                <?= number_format(array_sum(array_column($enseignements, 'coefficient')), 1) ?>
                            </td>
                            <td class="text-center px-4 py-2.5">
                                <?= array_sum(array_column($enseignements, 'volume_horaire')) ?>h
                            </td>
                            <td colspan="<?= showcp($perms, 'classes.edit') ? 3 : 2 ?>"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (showcp($perms, 'classes.edit')): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="plus-circle" class="w-4 h-4 text-sky-600"></i>
                <span class="font-semibold text-slate-700">Ajouter un enseignement</span>
            </div>
            <div class="p-5 p-4 space-y-3">
                <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classe->id ?>/affecter-enseignant">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <div>
                        <label class="form-label text-xs" for="matiere_id">Matière</label>
                        <select id="matiere_id" name="matiere_id" class="form-input text-sm" required>
                            <option value="">— Choisir la matière —</option>
                            <?php foreach ($matieres as $m): ?>
                            <option value="<?= $m->id ?>"><?= htmlspecialchars($m->label, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs" for="professeur_id">Professeur</label>
                        <select id="professeur_id" name="professeur_id" class="form-input text-sm" required>
                            <option value="">— Choisir le professeur —</option>
                            <?php foreach ($professeurs as $p): ?>
                            <option value="<?= $p->id ?>"><?= htmlspecialchars($p->label, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs" for="annee_scolaire">Année scolaire</label>
                        <input type="text" id="annee_scolaire" name="annee_scolaire"
                               class="form-input text-sm"
                               value="<?= htmlspecialchars($annee, ENT_QUOTES) ?>"
                               pattern="\d{4}-\d{4}" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-full px-2.5 py-1.5 text-xs rounded-md mt-1">
                        <i data-lucide="plus" class="w-4 h-4"></i>Affecter l'enseignement
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal suppression -->
<?php if (showcp($perms, 'classes.delete') && $classe->nb_eleves == 0): ?>
<div id="delModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:420px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-600"></i>
                </span>
                Supprimer la classe
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    onclick="document.getElementById('delModal').classList.remove('active');document.body.style.overflow=''">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600 text-sm">
                Supprimer définitivement la classe
                <strong class="text-slate-900"><?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?></strong> ?
                Cette action est irréversible.
            </p>
            <div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 mt-3">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                <span class="text-sm">Les enseignements liés seront également supprimés.</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button class="btn btn-secondary"
                    onclick="document.getElementById('delModal').classList.remove('active');document.body.style.overflow=''">
                Annuler
            </button>
            <form method="POST" action="<?= BASE_URL ?>/classes/<?= $classe->id ?>/delete">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function switchTab(tab) {
    ['eleves','enseignants'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('hidden', t !== tab);
        const btn = document.getElementById('tab-btn-' + t);
        btn.classList.toggle('border-violet-600', t === tab);
        btn.classList.toggle('text-violet-700',   t === tab);
        btn.classList.toggle('border-transparent', t !== tab);
        btn.classList.toggle('text-slate-500',     t !== tab);
    });
}
</script>
