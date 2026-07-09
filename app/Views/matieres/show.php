<?php
$currentUser = \Core\Session::getUser();
$perms       = $currentUser['permissions'] ?? [];
function showmp(array $p, string $k): bool { return in_array($k, $p, true); }
$enseignements = $enseignements ?? [];
?>

<!-- Breadcrumb + actions -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <nav class="flex items-center gap-2 text-sm text-slate-500">
        <a href="<?= BASE_URL ?>/matieres" class="hover:text-emerald-600 transition-colors">Matières</a>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span class="text-slate-800 font-semibold"><?= htmlspecialchars($matiere->nom, ENT_QUOTES) ?></span>
    </nav>
    <div class="flex items-center gap-2">
        <?php if (showmp($perms, 'matieres.edit')): ?>
        <a href="<?= BASE_URL ?>/matieres/<?= $matiere->id ?>/edit" class="btn btn-warning">
            <i data-lucide="pencil" class="w-4 h-4"></i>Modifier
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/matieres" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
        <?php if (showmp($perms, 'matieres.delete') && $matiere->nb_enseignements == 0): ?>
        <button class="btn btn-outline text-red-500 border-red-200 hover:bg-red-50"
                onclick="document.getElementById('deleteModal').classList.add('active');document.body.style.overflow='hidden'">
            <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Carte matière (sidebar gauche) -->
    <div>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="p-5 p-6 text-center">
                <div class="w-20 h-20 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="book" class="w-10 h-10 text-emerald-500"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">
                    <?= htmlspecialchars($matiere->nom, ENT_QUOTES) ?>
                </h3>
                <?php if (!empty($matiere->description)): ?>
                <p class="text-sm text-slate-500 mb-4 text-left bg-slate-50 rounded-xl p-3">
                    <?= nl2br(htmlspecialchars($matiere->description, ENT_QUOTES)) ?>
                </p>
                <?php endif; ?>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-violet-50 rounded-xl p-3">
                        <p class="text-2xl font-bold text-violet-600"><?= number_format($matiere->coefficient, 1) ?></p>
                        <p class="text-xs text-violet-500 mt-0.5">Coefficient</p>
                    </div>
                    <div class="bg-sky-50 rounded-xl p-3">
                        <p class="text-2xl font-bold text-sky-600"><?= $matiere->volume_horaire ?>h</p>
                        <p class="text-xs text-sky-500 mt-0.5">/ semaine</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">
                    <i data-lucide="graduation-cap" class="w-3 h-3 mr-1"></i>
                    <?= $matiere->nb_enseignements ?> enseignement(s)
                </span>
            </div>

            <?php if (!empty($matiere->responsable_nom) && trim($matiere->responsable_nom) !== ' '): ?>
            <div class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 bg-slate-50 p-4 border-t border-slate-100">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Enseignant responsable</p>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                        <i data-lucide="user" class="w-5 h-5 text-amber-600"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-sm text-slate-800 truncate">
                            <?= htmlspecialchars($matiere->responsable_nom, ENT_QUOTES) ?>
                        </p>
                        <?php if (!empty($matiere->responsable_specialite)): ?>
                        <p class="text-xs text-slate-400 truncate">
                            <?= htmlspecialchars($matiere->responsable_specialite, ENT_QUOTES) ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($matiere->responsable_telephone)): ?>
                        <a href="tel:<?= htmlspecialchars($matiere->responsable_telephone, ENT_QUOTES) ?>"
                           class="text-xs text-slate-500 hover:text-violet-600 flex items-center gap-1 mt-0.5">
                            <i data-lucide="phone" class="w-3 h-3"></i>
                            <?= htmlspecialchars($matiere->responsable_telephone, ENT_QUOTES) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (showmp($perms, 'matieres.edit')): ?>
            <div class="p-3 border-t border-slate-100">
                <a href="<?= BASE_URL ?>/matieres/<?= $matiere->id ?>/edit"
                   class="btn btn-warning w-full px-2.5 py-1.5 text-xs rounded-md">
                    <i data-lucide="pencil" class="w-4 h-4"></i>Modifier la matière
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Contenu principal (2 colonnes) -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Enseignements dans les classes -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="calendar" class="w-4 h-4 text-sky-500"></i>
                <span class="font-semibold text-slate-700">Enseignements dans les classes</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-sky-100 text-sky-700 ml-auto"><?= count($enseignements) ?></span>
            </div>
            <?php if (empty($enseignements)): ?>
            <div class="p-5 flex flex-col items-center justify-center gap-3 text-center text-slate-500 py-10">
                <i data-lucide="calendar-x" class="w-10 h-10 text-slate-300 mx-auto mb-2"></i>
                <p class="text-sm text-slate-400 mb-3">Cette matière n'est affectée à aucune classe.</p>
                <?php if (showmp($perms, 'classes.edit')): ?>
                <a href="<?= BASE_URL ?>/classes" class="btn btn-secondary">
                    <i data-lucide="building-2" class="w-4 h-4"></i>Gérer les classes
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50">
                    <thead><tr>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Professeur</th>
                        <th>Spécialité</th>
                        <th>Année</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($enseignements as $en): ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>/classes/<?= $en->classe_id ?>?tab=enseignants"
                               class="font-semibold text-slate-800 hover:text-violet-600 transition-colors flex items-center gap-1.5">
                                <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                <?= htmlspecialchars($en->classe_nom, ENT_QUOTES) ?>
                            </a>
                        </td>
                        <td><span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= htmlspecialchars($en->classe_niveau, ENT_QUOTES) ?></span></td>
                        <td class="font-semibold text-sm text-slate-700"><?= htmlspecialchars($en->prof_nom, ENT_QUOTES) ?></td>
                        <td class="text-sm text-slate-400"><?= htmlspecialchars($en->specialite ?? '—', ENT_QUOTES) ?></td>
                        <td class="text-sm text-slate-400"><?= htmlspecialchars($en->annee_scolaire, ENT_QUOTES) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Répartition par année scolaire -->
        <?php if (!empty($enseignements)):
            $annees = array_unique(array_column($enseignements, 'annee_scolaire'));
        ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-500"></i>
                <span class="font-semibold text-slate-700">Répartition par année scolaire</span>
            </div>
            <div class="p-5 p-4">
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($annees as $annee):
                        $cnt = count(array_filter($enseignements, fn($e) => $e->annee_scolaire === $annee));
                    ?>
                    <div class="bg-slate-50 border border-slate-100 rounded-xl px-5 py-3 text-center">
                        <p class="text-2xl font-bold text-violet-600"><?= $cnt ?></p>
                        <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($annee, ENT_QUOTES) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal suppression -->
<?php if (showmp($perms, 'matieres.delete') && $matiere->nb_enseignements == 0): ?>
<div id="deleteModal" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl" style="max-width:420px">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-950 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-600"></i>
                </span>
                Supprimer la matière
            </h3>
            <button class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    onclick="document.getElementById('deleteModal').classList.remove('active');document.body.style.overflow=''">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-slate-600 text-sm">
                Supprimer définitivement <strong class="text-slate-900"><?= htmlspecialchars($matiere->nom, ENT_QUOTES) ?></strong> ?
                Cette action est irréversible.
            </p>
            <div class="alert alert-danger mt-3">
                <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                <span class="text-sm">Les notes liées à cette matière seront également supprimées.</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <button class="btn btn-secondary"
                    onclick="document.getElementById('deleteModal').classList.remove('active');document.body.style.overflow=''">
                Annuler
            </button>
            <form method="POST" action="<?= BASE_URL ?>/matieres/<?= $matiere->id ?>/delete">
                <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
