<?php
/** @var object $matiere @var array $enseignements @var array $niveauxArray @var bool $canUpdate @var bool $canArchive @var bool $canDelete */
$dotColor = $matiere->couleur ?: '#6366F1';
$annees = [];
foreach ($enseignements as $en) {
    $annees[$en->annee_scolaire] = ($annees[$en->annee_scolaire] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <?php include BASE_PATH . '/app/Views/layouts/head_assets.php'; ?>
</head>
<body class="bg-slate-50 text-slate-800">
<?php include BASE_PATH . '/app/Views/layouts/sidebar.php'; ?>

<main class="ml-64 p-6 min-h-screen">
    <div class="max-w-5xl mx-auto">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-slate-500 mb-4">
            <a href="<?= BASE_URL ?>/v2/scolarite/matieres" class="hover:text-emerald-600">Matières</a>
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
            <span class="text-slate-800 font-medium"><?= htmlspecialchars($matiere->nom) ?></span>
        </nav>

        <!-- Flash -->
        <?php foreach (['success','error'] as $t): $msg = \Core\Session::getFlash($t); ?>
        <?php if ($msg): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
            <?= $t==='success'?'bg-green-50 text-green-800 border border-green-200':'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; endforeach; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Fiche matière -->
            <div class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <!-- Header coloré -->
                    <div class="h-2" style="background:<?= htmlspecialchars($dotColor) ?>"></div>
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                             style="background:<?= htmlspecialchars($dotColor) ?>20">
                            <i data-lucide="book" class="w-8 h-8" style="color:<?= htmlspecialchars($dotColor) ?>"></i>
                        </div>
                        <h1 class="text-xl font-bold text-slate-900 mb-1"><?= htmlspecialchars($matiere->nom) ?></h1>
                        <?php if (!$matiere->actif): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 mb-2">
                            <i data-lucide="archive" class="w-3 h-3"></i> Archivée
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($matiere->description)): ?>
                        <p class="text-sm text-slate-500 bg-slate-50 rounded-xl p-3 text-left mt-3">
                            <?= nl2br(htmlspecialchars($matiere->description)) ?>
                        </p>
                        <?php endif; ?>

                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="rounded-xl p-3" style="background:<?= htmlspecialchars($dotColor) ?>15">
                                <p class="text-2xl font-bold" style="color:<?= htmlspecialchars($dotColor) ?>">
                                    ×<?= number_format((float)$matiere->coefficient, 1) ?>
                                </p>
                                <p class="text-xs text-slate-500 mt-0.5">Coefficient</p>
                            </div>
                            <div class="bg-sky-50 rounded-xl p-3">
                                <p class="text-2xl font-bold text-sky-600"><?= $matiere->volume_horaire ?>h</p>
                                <p class="text-xs text-sky-500 mt-0.5">/ semaine</p>
                            </div>
                        </div>
                    </div>

                    <!-- Métadonnées -->
                    <div class="border-t border-slate-100 px-5 py-4 space-y-3">
                        <?php if ($matiere->filiere): ?>
                        <div class="flex items-center gap-2 text-sm">
                            <i data-lucide="filter" class="w-4 h-4 text-slate-400"></i>
                            <span class="text-slate-500">Filière :</span>
                            <span class="font-medium"><?= htmlspecialchars($matiere->filiere) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($niveauxArray)): ?>
                        <div class="flex items-start gap-2 text-sm">
                            <i data-lucide="layers" class="w-4 h-4 text-slate-400 mt-0.5"></i>
                            <div>
                                <span class="text-slate-500">Niveaux :</span>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <?php foreach ($niveauxArray as $nv): ?>
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600"><?= $nv ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="flex items-center gap-2 text-sm">
                            <i data-lucide="graduation-cap" class="w-4 h-4 text-slate-400"></i>
                            <span class="text-slate-500">Enseignements :</span>
                            <span class="font-medium"><?= (int)$matiere->nb_enseignements ?></span>
                        </div>

                        <?php if (!empty($matiere->responsable_nom) && trim($matiere->responsable_nom) !== ' '): ?>
                        <div class="flex items-center gap-2 text-sm">
                            <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                            <span class="text-slate-500">Responsable :</span>
                            <span class="font-medium"><?= htmlspecialchars($matiere->responsable_nom) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <?php if ($canUpdate || $canDelete): ?>
                    <div class="border-t border-slate-100 p-4 flex flex-col gap-2">
                        <?php if ($canUpdate): ?>
                        <a href="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $matiere->id ?>/edit"
                           class="w-full inline-flex items-center justify-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                            <i data-lucide="pencil" class="w-4 h-4"></i> Modifier
                        </a>
                        <?php if ($matiere->actif && $canArchive): ?>
                        <button type="button" onclick="document.getElementById('archiveModal').classList.remove('hidden')"
                                class="w-full inline-flex items-center justify-center gap-2 bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                            <i data-lucide="archive" class="w-4 h-4"></i> Archiver
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canDelete && !(int)$matiere->nb_enseignements): ?>
                        <button type="button" onclick="document.getElementById('deleteModal').classList.remove('hidden')"
                                class="w-full inline-flex items-center justify-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium px-3 py-2 rounded-lg transition">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> Supprimer
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Colonne principale -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Enseignements -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="font-semibold text-slate-900 flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4 text-sky-500"></i>
                            Enseignements dans les classes
                        </h2>
                        <span class="text-sm text-slate-400"><?= count($enseignements) ?></span>
                    </div>

                    <?php if (empty($enseignements)): ?>
                    <div class="py-12 text-center text-slate-400">
                        <i data-lucide="calendar-x" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                        <p class="text-sm">Cette matière n'est affectée à aucune classe.</p>
                        <a href="<?= BASE_URL ?>/v2/scolarite/classes"
                           class="inline-flex items-center gap-1 text-sm text-emerald-600 hover:underline mt-2">
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i> Gérer les classes
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Classe</th>
                                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Niveau</th>
                                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Professeur</th>
                                    <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden lg:table-cell">Spécialité</th>
                                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Année</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($enseignements as $en): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3">
                                        <a href="<?= BASE_URL ?>/v2/scolarite/classes/<?= $en->classe_id ?>"
                                           class="font-medium text-slate-800 hover:text-emerald-700 flex items-center gap-1.5">
                                            <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400"></i>
                                            <?= htmlspecialchars($en->classe_nom) ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                                            <?= htmlspecialchars($en->classe_niveau) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-slate-700"><?= htmlspecialchars($en->prof_nom) ?></td>
                                    <td class="px-4 py-3 text-slate-400 hidden lg:table-cell"><?= htmlspecialchars($en->specialite ?? '—') ?></td>
                                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($en->annee_scolaire) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Répartition par année -->
                <?php if (!empty($annees)): ?>
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-500"></i>
                        Répartition par année scolaire
                    </h2>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($annees as $annee => $cnt): ?>
                        <div class="bg-slate-50 border border-slate-100 rounded-xl px-5 py-3 text-center">
                            <p class="text-2xl font-bold text-violet-600"><?= $cnt ?></p>
                            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($annee) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<!-- Modal archivage -->
<div id="archiveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Archiver la matière</h3>
        <p class="text-sm text-slate-600 mb-4">
            Archiver <strong><?= htmlspecialchars($matiere->nom) ?></strong> ?
            Elle ne sera plus disponible pour les nouvelles affectations.
        </p>
        <?php if ((int)$matiere->nb_enseignements > 0): ?>
        <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-4">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 inline mr-1"></i>
            Cette matière est utilisée dans <?= (int)$matiere->nb_enseignements ?> enseignement(s).
            L'archivage sera refusé tant qu'elle est en cours.
        </div>
        <?php endif; ?>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('archiveModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $matiere->id ?>/archiver">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg">Archiver</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal suppression -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-lg text-slate-900 mb-2">Supprimer la matière</h3>
        <p class="text-sm text-slate-600 mb-2">
            Supprimer définitivement <strong><?= htmlspecialchars($matiere->nom) ?></strong> ? Action irréversible.
        </p>
        <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2 mb-4">
            Les données liées seront également supprimées.
        </p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('deleteModal').classList.add('hidden')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Annuler</button>
            <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/matieres/<?= $matiere->id ?>/delete">
                <input type="hidden" name="csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                <button type="submit"
                        class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg">Supprimer</button>
            </form>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/app/Views/layouts/footer_assets.php'; ?>
</body>
</html>
