<?php
$salles      = $salles ?? [];
$types       = $types  ?? \App\Modules\VieScolaire\EmploisDuTemps\Controllers\SalleController::TYPES;
$old         = $old    ?? [];
$csrfToken   = \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canCreate   = in_array('timetable.create', $currentUser['permissions'] ?? [], true);
$canEdit     = in_array('timetable.update', $currentUser['permissions'] ?? [], true);
?>

<div class="flex items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="building" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Salles</h2>
            <p class="text-sm text-slate-400 mt-0.5">Référentiel des salles utilisées par l'emploi du temps</p>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Emploi du temps
    </a>
</div>

<?php if ($msg = \Core\Session::getFlash('success')): ?>
<div class="alert alert-success mb-4" role="alert"><i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i><span class="flex-1 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES) ?></span></div>
<?php endif; ?>
<?php if ($msg = \Core\Session::getFlash('error')): ?>
<div class="alert alert-danger mb-4" role="alert"><i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i><span class="flex-1 text-sm"><?= htmlspecialchars($msg, ENT_QUOTES) ?></span></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

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
                        <th>Code</th>
                        <th>Type</th>
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
                <?php foreach ($salles as $s): ?>
                <tr class="<?= !$s->actif ? 'opacity-50' : '' ?>">
                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($s->nom, ENT_QUOTES) ?></td>
                    <td class="mono text-xs text-slate-400"><?= htmlspecialchars($s->code, ENT_QUOTES) ?></td>
                    <td class="text-slate-500 text-xs"><?= htmlspecialchars($types[$s->type] ?? $s->type, ENT_QUOTES) ?></td>
                    <td class="text-center">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= (int)$s->capacite ?> pl.</span>
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
                            <button onclick="openEditSalle(<?= (int)$s->id ?>, '<?= htmlspecialchars(addslashes($s->nom), ENT_QUOTES) ?>', '<?= $s->type ?>', <?= (int)$s->capacite ?>, <?= $s->actif ? 1 : 0 ?>)"
                                    class="btn btn-ghost btn-icon text-violet-500" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/salles/<?= (int)$s->id ?>/toggle" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                <button class="btn btn-ghost btn-icon <?= $s->actif ? 'text-amber-500' : 'text-emerald-500' ?>"
                                        title="<?= $s->actif ? 'Désactiver' : 'Réactiver' ?>">
                                    <i data-lucide="<?= $s->actif ? 'power-off' : 'power' ?>" class="w-4 h-4"></i>
                                </button>
                            </form>
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

    <?php if ($canCreate): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm h-fit">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i>
            <span class="font-semibold text-slate-700" id="formSalleTitre">Nouvelle salle</span>
        </div>
        <div class="p-5">
            <form method="POST" id="formSalle" action="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/salles" class="space-y-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                <div class="mb-4">
                    <label class="form-label text-xs">Nom <span class="form-required">*</span></label>
                    <input type="text" name="nom" id="salleNom" class="form-input text-sm"
                           value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                           placeholder="ex. Salle A01" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-xs">Type</label>
                    <select name="type" id="salleType" class="form-select text-sm">
                        <?php foreach ($types as $k => $label): ?>
                        <option value="<?= $k ?>" <?= ($old['type'] ?? 'cours') === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label text-xs">Capacité</label>
                    <input type="number" name="capacite" id="salleCapacite" class="form-input text-sm"
                           value="<?= htmlspecialchars((string)($old['capacite'] ?? '30'), ENT_QUOTES) ?>" min="0">
                </div>

                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors">
                    <input type="checkbox" name="actif" value="1" id="salleActif"
                           class="w-4 h-4 accent-violet-600"
                           <?= isset($old['actif']) ? ($old['actif'] ? 'checked' : '') : 'checked' ?>>
                    <span class="text-sm text-slate-700">Salle active</span>
                </label>

                <button type="submit" class="btn btn-success w-full" id="btnSalleSubmit">
                    <i data-lucide="building" class="w-4 h-4"></i>Ajouter la salle
                </button>
                <button type="button" class="btn btn-secondary w-full hidden" id="btnSalleCancel" onclick="resetFormSalle()">
                    Annuler la modification
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function openEditSalle(id, nom, type, capacite, actif) {
    const form = document.getElementById('formSalle');
    form.action = '<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/salles/' + id;
    document.getElementById('salleNom').value = nom;
    document.getElementById('salleType').value = type;
    document.getElementById('salleCapacite').value = capacite;
    document.getElementById('salleActif').checked = !!actif;
    document.getElementById('formSalleTitre').textContent = 'Modifier — ' + nom;
    document.getElementById('btnSalleSubmit').innerHTML = '<i data-lucide="save" class="w-4 h-4"></i>Enregistrer';
    document.getElementById('btnSalleCancel').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function resetFormSalle() {
    const form = document.getElementById('formSalle');
    form.action = '<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/salles';
    form.reset();
    document.getElementById('formSalleTitre').textContent = 'Nouvelle salle';
    document.getElementById('btnSalleSubmit').innerHTML = '<i data-lucide="building" class="w-4 h-4"></i>Ajouter la salle';
    document.getElementById('btnSalleCancel').classList.add('hidden');
    if (window.lucide) lucide.createIcons();
}
</script>
