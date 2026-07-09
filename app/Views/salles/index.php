<?php
$salles      = $salles ?? [];
$types       = $types  ?? \App\Models\SalleModel::TYPES;
$annee       = $annee  ?? '';
$old         = $old    ?? [];
$csrfToken   = \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canCreate   = in_array('emploi_du_temps.create', $currentUser['permissions'] ?? [], true);
$canEdit     = in_array('emploi_du_temps.edit',   $currentUser['permissions'] ?? [], true);
?>

<!-- Header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="building" class="w-5 h-5 text-violet-600"></i>Salles
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">Gestion des salles et espaces disponibles</p>
    </div>
    <a href="<?= BASE_URL ?>/emplois-du-temps" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Emploi du temps
    </a>
</div>

<!-- KPI rapides -->
<div class="grid grid-cols-3 gap-3 mb-5">
    <?php
    $actives   = count(array_filter($salles, fn($s) => $s->actif));
    $inactives = count($salles) - $actives;
    $totalCap  = array_sum(array_map(fn($s) => (int)$s->capacite, $salles));
    ?>
    <div class="stat-card-v text-center">
        <div class="stat-icon bg-violet-100 text-violet-600 mx-auto mb-2">
            <i data-lucide="building-2" class="w-4 h-4"></i>
        </div>
        <p class="text-xl font-bold text-slate-900"><?= count($salles) ?></p>
        <p class="text-xs text-slate-400">Total salles</p>
    </div>
    <div class="stat-card-v text-center">
        <div class="stat-icon bg-emerald-100 text-emerald-600 mx-auto mb-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
        </div>
        <p class="text-xl font-bold text-slate-900"><?= $actives ?></p>
        <p class="text-xs text-slate-400">Actives</p>
    </div>
    <div class="stat-card-v text-center">
        <div class="stat-icon bg-blue-100 text-blue-600 mx-auto mb-2">
            <i data-lucide="users" class="w-4 h-4"></i>
        </div>
        <p class="text-xl font-bold text-slate-900"><?= $totalCap ?></p>
        <p class="text-xs text-slate-400">Places totales</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Liste des salles -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900 justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>
                <span class="font-semibold text-slate-700">Liste des salles</span>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= count($salles) ?> salle(s)</span>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
                <thead>
                    <tr>
                        <th>Salle</th>
                        <th>Type</th>
                        <th>Bâtiment</th>
                        <th class="text-center">Capacité</th>
                        <th class="text-center">Séances/sem.</th>
                        <th class="text-center">Statut</th>
                        <?php if ($canEdit): ?><th class="text-right col-actions">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($salles)): ?>
                <tr>
                    <td colspan="7" class="py-12">
                        <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500">
                            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
                                <i data-lucide="building" class="w-7 h-7 text-slate-300"></i>
                            </div>
                            <p class="font-semibold text-slate-400">Aucune salle configurée</p>
                            <p class="text-sm text-slate-300 mt-1">Ajoutez votre première salle ci-contre.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($salles as $s):
                    $typeInfo = $types[$s->type] ?? ['label'=>$s->type,'icon'=>'building'];
                ?>
                <tr class="<?= !$s->actif ? 'opacity-50' : '' ?>">
                    <td>
                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($s->nom, ENT_QUOTES) ?></p>
                        <?php if (!empty($s->description)): ?>
                        <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($s->description, ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="text-slate-500 text-xs"><?= htmlspecialchars($typeInfo['label'], ENT_QUOTES) ?></span>
                    </td>
                    <td class="text-slate-400 text-xs"><?= htmlspecialchars($s->batiment ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-center">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= $s->capacite ?> pl.</span>
                    </td>
                    <td class="text-center">
                        <?php $nb = (int)($s->nb_seances ?? 0); ?>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $nb > 0 ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600' ?>"><?= $nb ?></span>
                    </td>
                    <td class="text-center">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $s->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                            <i data-lucide="<?= $s->actif ? 'check' : 'minus' ?>" class="w-3 h-3 mr-0.5"></i>
                            <?= $s->actif ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                    <td class="text-right col-actions">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= BASE_URL ?>/salles/<?= $s->id ?>/edit"
                               class="btn btn-ghost btn-icon text-violet-500" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <button onclick="openDelSalle(<?= $s->id ?>, '<?= htmlspecialchars(addslashes($s->nom), ENT_QUOTES) ?>')"
                                    class="btn btn-ghost btn-icon text-red-400"
                                    title="<?= (int)($s->nb_seances ?? 0) > 0 ? 'Salle utilisée dans le planning' : 'Supprimer' ?>"
                                    <?= (int)($s->nb_seances ?? 0) > 0 ? 'disabled' : '' ?>>
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulaire d'ajout rapide -->
    <?php if ($canCreate): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm h-fit">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i>
            <span class="font-semibold text-slate-700">Nouvelle salle</span>
        </div>
        <div class="p-5 p-4">
            <form method="POST" action="<?= BASE_URL ?>/salles/store" class="space-y-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                <div class="mb-4">
                    <label class="form-label text-xs">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" class="form-input text-sm"
                           value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                           placeholder="ex. Salle A01" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-xs">Type</label>
                    <select name="type" class="form-input text-sm">
                        <?php foreach ($types as $k => $t): ?>
                        <option value="<?= $k ?>" <?= ($old['type'] ?? 'salle_cours') === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['label'], ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="mb-4">
                        <label class="form-label text-xs">Capacité</label>
                        <input type="number" name="capacite" class="form-input text-sm"
                               value="<?= $old['capacite'] ?? '30' ?>" min="0">
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-xs">Bâtiment</label>
                        <input type="text" name="batiment" class="form-input text-sm"
                               value="<?= htmlspecialchars($old['batiment'] ?? '', ENT_QUOTES) ?>"
                               placeholder="Bât. A">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-xs">Description</label>
                    <input type="text" name="description" class="form-input text-sm"
                           value="<?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES) ?>"
                           placeholder="Équipements, spécificités…">
                </div>

                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                    <input type="checkbox" name="actif" value="1" id="actifNew"
                           class="w-4 h-4 accent-violet-600"
                           <?= isset($old['actif']) ? ($old['actif'] ? 'checked' : '') : 'checked' ?>>
                    <span class="text-sm text-slate-700">Salle active</span>
                </label>

                <button type="submit" class="btn btn-success w-full">
                    <i data-lucide="building" class="w-4 h-4"></i>Ajouter la salle
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal suppression -->
<?php if ($canEdit): ?>
<div id="modalDelSalle" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex hidden">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl max-w-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <span class="font-semibold text-slate-800 flex items-center gap-2">
                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>Supprimer la salle
            </span>
            <button onclick="document.getElementById('modalDelSalle').classList.remove('active')"
                    class="btn btn-ghost btn-icon text-slate-400">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-sm text-slate-600">
                Voulez-vous supprimer la salle <strong id="delSalleNom" class="text-slate-900"></strong> ?
                Cette action est irréversible.
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 justify-end gap-2">
            <button onclick="document.getElementById('modalDelSalle').classList.remove('active')"
                    class="btn btn-secondary">Annuler</button>
            <form id="formDelSalle" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <button class="btn btn-danger">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
                </button>
            </form>
        </div>
    </div>
</div>
<script>
function openDelSalle(id, nom) {
    document.getElementById('delSalleNom').textContent = nom;
    document.getElementById('formDelSalle').action = '<?= BASE_URL ?>/salles/' + id + '/delete';
    document.getElementById('modalDelSalle').classList.add('active');
}
</script>
<?php endif; ?>
