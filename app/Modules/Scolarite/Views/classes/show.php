<?php
$classe           = $classe           ?? null;
$eleves           = $eleves           ?? [];
$elevesDisponibles = $elevesDisponibles ?? [];
$enseignements    = $enseignements    ?? [];
$profs            = $profs            ?? [];
$matieres         = $matieres         ?? [];
$annee            = $annee            ?? '';

function spermV2(array $p, string $k): bool {
    return in_array($k, $p, true);
}
$perms = \Core\Session::getUser()['permissions'] ?? [];

$actuel = (int)($classe->nb_eleves ?? 0);
$max    = (int)($classe->max_eleves ?? 40);
$pct    = $max > 0 ? min(100, round($actuel / $max * 100)) : 0;
$barColor = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-400' : 'bg-emerald-400');
?>

<!-- Fil d'Ariane -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-4">
    <a href="<?= BASE_URL ?>/v2/scolarite/classes" class="hover:text-violet-600 transition">Classes</a>
    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
    <span class="text-slate-700 font-medium">
        <?= htmlspecialchars(($classe->niveau ?? '') . ' ' . ($classe->nom ?? ''), ENT_QUOTES) ?>
    </span>
</nav>

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

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- Carte identité -->
    <div class="lg:col-span-1">
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-gradient-to-br from-violet-600 to-violet-800 p-5 text-white text-center">
                <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="building-2" class="w-7 h-7"></i>
                </div>
                <h2 class="text-xl font-bold tracking-widest">
                    <?= htmlspecialchars(($classe->niveau ?? '') . ' ' . ($classe->nom ?? ''), ENT_QUOTES) ?>
                </h2>
                <p class="text-violet-200 text-sm mt-1"><?= htmlspecialchars($classe->annee_scolaire ?? '', ENT_QUOTES) ?></p>
            </div>
            <div class="p-4 space-y-3">
                <!-- Capacité -->
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                        <span>Remplissage</span>
                        <span class="font-medium text-slate-700"><?= $actuel ?>/<?= $max ?></span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full <?= $barColor ?> transition-all"
                             style="width: <?= $pct ?>%"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1"><?= $pct ?>% · <?= max(0, $max - $actuel) ?> places libres</p>
                </div>
                <div class="border-t border-slate-100 pt-3 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Niveau</span>
                        <span class="font-medium text-slate-700"><?= htmlspecialchars($classe->niveau ?? '', ENT_QUOTES) ?></span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Matières</span>
                        <span class="font-medium text-slate-700"><?= (int)($classe->nb_enseignements ?? 0) ?></span>
                    </div>
                    <?php if (!empty($classe->description)): ?>
                    <div class="text-xs text-slate-500 pt-1 border-t border-slate-100">
                        <?= htmlspecialchars($classe->description, ENT_QUOTES) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Actions -->
            <?php if (spermV2($perms, 'classes.update') || spermV2($perms, 'classes.delete')): ?>
            <div class="border-t border-slate-100 p-3 flex gap-2">
                <?php if (spermV2($perms, 'classes.update')): ?>
                <a href="<?= BASE_URL ?>/v2/scolarite/classes/<?= $classe->id ?>/edit"
                   class="btn btn-secondary btn-sm flex-1 text-center">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>Modifier
                </a>
                <?php endif; ?>
                <?php if (spermV2($perms, 'classes.delete') && $actuel === 0): ?>
                <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/classes/<?= $classe->id ?>/delete"
                      onsubmit="return confirm('Supprimer cette classe ?')">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Onglets -->
    <div class="lg:col-span-3">
        <div class="rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden">

            <!-- Onglets navigation -->
            <div class="flex border-b border-slate-200 bg-slate-50 px-4 pt-3 gap-1">
                <button onclick="switchTab('eleves')" id="tab-eleves"
                        class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-violet-500 text-violet-600 bg-white -mb-px">
                    <i data-lucide="users" class="w-3.5 h-3.5 inline mr-1"></i>
                    Élèves <span class="ml-1 text-xs bg-violet-100 text-violet-600 rounded-full px-1.5"><?= count($eleves) ?></span>
                </button>
                <button onclick="switchTab('enseignements')" id="tab-enseignements"
                        class="tab-btn px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-transparent text-slate-500 hover:text-slate-700">
                    <i data-lucide="book-open" class="w-3.5 h-3.5 inline mr-1"></i>
                    Enseignements <span class="ml-1 text-xs bg-slate-100 text-slate-500 rounded-full px-1.5"><?= count($enseignements) ?></span>
                </button>
            </div>

            <!-- Onglet Élèves -->
            <div id="panel-eleves" class="p-4">
                <!-- Formulaire d'affectation -->
                <?php if (spermV2($perms, 'classes.update') && !empty($elevesDisponibles)): ?>
                <?php if ($actuel < $max || $max === 0): ?>
                <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-200">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4 text-violet-500"></i>
                        Affecter un élève
                    </h4>
                    <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/classes/<?= $classe->id ?>/affecter-eleve"
                          class="flex gap-2 flex-wrap">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <select name="eleve_id" class="form-input flex-1 min-w-48" required>
                            <option value="">— Sélectionner un élève —</option>
                            <?php foreach ($elevesDisponibles as $e): ?>
                            <option value="<?= $e->id ?>">
                                <?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>
                                <?php if (!empty($e->classe_actuelle)): ?>
                                (actuellement <?= htmlspecialchars($e->niveau_actuel . ' ' . $e->classe_actuelle, ENT_QUOTES) ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus" class="w-4 h-4"></i>Affecter
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-600 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    Classe pleine (<?= $actuel ?>/<?= $max ?>). Augmentez la capacité pour affecter d'autres élèves.
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Tableau élèves -->
                <?php if (empty($eleves)): ?>
                <div class="text-center py-10 text-slate-400">
                    <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                    <p class="text-sm">Aucun élève dans cette classe.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="text-left py-2 px-3 font-semibold text-slate-600">Élève</th>
                                <th class="text-left py-2 px-3 font-semibold text-slate-600">Matricule</th>
                                <th class="text-left py-2 px-3 font-semibold text-slate-600">Sexe</th>
                                <th class="text-left py-2 px-3 font-semibold text-slate-600">Statut</th>
                                <?php if (spermV2($perms, 'classes.update')): ?>
                                <th class="py-2 px-3"></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($eleves as $e): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-2 px-3">
                                    <a href="<?= BASE_URL ?>/v2/scolarite/eleves/<?= $e->id ?>"
                                       class="font-medium text-violet-600 hover:underline">
                                        <?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>
                                    </a>
                                </td>
                                <td class="py-2 px-3 font-mono text-xs text-slate-500">
                                    <?= htmlspecialchars($e->matricule ?? '', ENT_QUOTES) ?>
                                </td>
                                <td class="py-2 px-3 text-slate-500"><?= $e->sexe === 'M' ? 'M' : 'F' ?></td>
                                <td class="py-2 px-3">
                                    <?php if ((int)$e->actif): ?>
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Actif
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 rounded-full px-2 py-0.5">
                                        Archivé
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <?php if (spermV2($perms, 'classes.update')): ?>
                                <td class="py-2 px-3 text-right">
                                    <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/classes/<?= $classe->id ?>/retirer-eleve"
                                          onsubmit="return confirm('Retirer <?= htmlspecialchars($e->nom, ENT_QUOTES) ?> de cette classe ?')">
                                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                        <input type="hidden" name="eleve_id" value="<?= $e->id ?>">
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded transition">
                                            <i data-lucide="user-minus" class="w-3.5 h-3.5 inline"></i> Retirer
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

            <!-- Onglet Enseignements -->
            <div id="panel-enseignements" class="p-4 hidden">
                <!-- Totaux -->
                <?php if (!empty($enseignements)): ?>
                <?php
                    $totalCoef = array_sum(array_map(fn($en) => (float)($en->coefficient ?? 0), $enseignements));
                    $totalVH   = array_sum(array_map(fn($en) => (int)($en->volume_horaire ?? 0), $enseignements));
                ?>
                <div class="flex gap-4 mb-4">
                    <div class="bg-sky-50 rounded-xl px-4 py-2 text-center">
                        <p class="text-lg font-bold text-sky-600"><?= $totalCoef ?></p>
                        <p class="text-xs text-sky-500">Coeff total</p>
                    </div>
                    <div class="bg-violet-50 rounded-xl px-4 py-2 text-center">
                        <p class="text-lg font-bold text-violet-600"><?= $totalVH ?>h</p>
                        <p class="text-xs text-violet-500">Volume horaire</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulaire d'affectation enseignant -->
                <?php if (spermV2($perms, 'classes.update')): ?>
                <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-200">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                        <i data-lucide="user-plus" class="w-4 h-4 text-sky-500"></i>
                        Affecter un enseignant
                    </h4>
                    <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/classes/<?= $classe->id ?>/affecter-enseignant"
                          class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                        <input type="hidden" name="annee_scolaire" value="<?= htmlspecialchars($annee, ENT_QUOTES) ?>">
                        <select name="professeur_id" class="form-input" required>
                            <option value="">— Professeur —</option>
                            <?php foreach ($profs as $p): ?>
                            <option value="<?= $p->id ?>"><?= htmlspecialchars($p->label ?? ($p->prenom . ' ' . $p->nom), ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="matiere_id" class="form-input" required>
                            <option value="">— Matière —</option>
                            <?php foreach ($matieres as $m): ?>
                            <option value="<?= $m->id ?>"><?= htmlspecialchars($m->label ?? $m->nom, ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus" class="w-4 h-4"></i>Affecter
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Tableau enseignements -->
                <?php if (empty($enseignements)): ?>
                <div class="text-center py-10 text-slate-400">
                    <i data-lucide="book-open" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                    <p class="text-sm">Aucun enseignement affecté.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="text-left py-2 px-3 font-semibold text-slate-600">Matière</th>
                                <th class="text-left py-2 px-3 font-semibold text-slate-600">Professeur</th>
                                <th class="text-center py-2 px-3 font-semibold text-slate-600">Coeff</th>
                                <th class="text-center py-2 px-3 font-semibold text-slate-600">V.H.</th>
                                <?php if (spermV2($perms, 'classes.update')): ?>
                                <th class="py-2 px-3"></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($enseignements as $en): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-2 px-3 font-medium text-slate-800">
                                    <?= htmlspecialchars($en->matiere_nom ?? '', ENT_QUOTES) ?>
                                </td>
                                <td class="py-2 px-3 text-slate-600">
                                    <?= htmlspecialchars($en->prof_nom ?? '', ENT_QUOTES) ?>
                                    <?php if (!empty($en->prof_specialite)): ?>
                                    <span class="text-xs text-slate-400">(<?= htmlspecialchars($en->prof_specialite, ENT_QUOTES) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-center text-slate-600"><?= $en->coefficient ?? '-' ?></td>
                                <td class="py-2 px-3 text-center text-slate-600">
                                    <?= $en->volume_horaire ? $en->volume_horaire . 'h' : '-' ?>
                                </td>
                                <?php if (spermV2($perms, 'classes.update')): ?>
                                <td class="py-2 px-3 text-right">
                                    <form method="POST" action="<?= BASE_URL ?>/v2/scolarite/classes/<?= $classe->id ?>/retirer-enseignant"
                                          onsubmit="return confirm('Retirer cet enseignement ?')">
                                        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
                                        <input type="hidden" name="enseignement_id" value="<?= $en->id ?>">
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded transition">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5 inline"></i> Retirer
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

        </div>
    </div>
</div>

<script>
function switchTab(name) {
    ['eleves', 'enseignements'].forEach(function(t) {
        document.getElementById('panel-' + t).classList.toggle('hidden', t !== name);
        const btn = document.getElementById('tab-' + t);
        if (t === name) {
            btn.classList.add('border-violet-500', 'text-violet-600', 'bg-white', '-mb-px');
            btn.classList.remove('border-transparent', 'text-slate-500');
        } else {
            btn.classList.remove('border-violet-500', 'text-violet-600', 'bg-white', '-mb-px');
            btn.classList.add('border-transparent', 'text-slate-500');
        }
    });
}
</script>
