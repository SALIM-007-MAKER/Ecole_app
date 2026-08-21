<?php
$plages      = $plages ?? [];
$old         = $old    ?? [];
$csrfToken   = \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canCreate   = in_array('timetable.create', $currentUser['permissions'] ?? [], true);
$canEdit     = in_array('timetable.update', $currentUser['permissions'] ?? [], true);
?>

<div class="flex items-start justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
            <i data-lucide="clock" class="w-5 h-5 text-violet-600"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">Plages horaires</h2>
            <p class="text-sm text-slate-400 mt-0.5">Créneaux de la journée type, utilisés par l'emploi du temps</p>
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
                <span class="font-semibold text-slate-700">Journée type</span>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600"><?= count($plages) ?> plage(s)</span>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm [&_thead]:bg-slate-50 [&_th]:px-4 [&_th]:py-3 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-slate-500 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle [&_td]:text-slate-700 [&_tbody]:divide-y [&_tbody]:divide-slate-100 [&_tbody_tr:hover]:bg-slate-50 text-sm">
                <thead>
                    <tr>
                        <th class="text-center" style="width:3rem">Ordre</th>
                        <th>Libellé</th>
                        <th class="text-center">Horaire</th>
                        <th class="text-center">Statut</th>
                        <?php if ($canEdit): ?><th class="text-right col-actions">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($plages)): ?>
                <tr>
                    <td colspan="5" class="py-12">
                        <div class="flex flex-col items-center justify-center gap-3 text-center text-slate-500">
                            <div class="w-14 h-14 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
                                <i data-lucide="clock" class="w-7 h-7 text-slate-300"></i>
                            </div>
                            <p class="font-semibold text-slate-400">Aucune plage horaire configurée</p>
                            <p class="text-sm text-slate-300 mt-1">Ajoutez la première ci-contre.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($plages as $p): ?>
                <tr class="<?= !$p->actif ? 'opacity-50' : '' ?>">
                    <td class="text-center text-slate-400 text-xs font-medium"><?= (int)$p->ordre ?></td>
                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($p->libelle, ENT_QUOTES) ?></td>
                    <td class="text-center mono text-xs text-slate-500">
                        <?= substr($p->heure_debut, 0, 5) ?> – <?= substr($p->heure_fin, 0, 5) ?>
                    </td>
                    <td class="text-center">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $p->actif ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                            <i data-lucide="<?= $p->actif ? 'check' : 'minus' ?>" class="w-3 h-3 mr-0.5"></i>
                            <?= $p->actif ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                    <td class="text-right col-actions">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditPlage(<?= (int)$p->id ?>, '<?= htmlspecialchars(addslashes($p->libelle), ENT_QUOTES) ?>', '<?= substr($p->heure_debut,0,5) ?>', '<?= substr($p->heure_fin,0,5) ?>', <?= (int)$p->ordre ?>, <?= $p->actif ? 1 : 0 ?>)"
                                    class="btn btn-ghost btn-icon text-violet-500" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <form method="POST" action="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/plages/<?= (int)$p->id ?>/toggle" class="inline">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                <button class="btn btn-ghost btn-icon <?= $p->actif ? 'text-amber-500' : 'text-emerald-500' ?>"
                                        title="<?= $p->actif ? 'Désactiver' : 'Réactiver' ?>">
                                    <i data-lucide="<?= $p->actif ? 'power-off' : 'power' ?>" class="w-4 h-4"></i>
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
            <span class="font-semibold text-slate-700" id="formPlageTitre">Nouvelle plage</span>
        </div>
        <div class="p-5">
            <form method="POST" id="formPlage" action="<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/plages" class="space-y-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                <div class="mb-4">
                    <label class="form-label text-xs">Libellé <span class="form-required">*</span></label>
                    <input type="text" name="libelle" id="plageLibelle" class="form-input text-sm"
                           value="<?= htmlspecialchars($old['libelle'] ?? '', ENT_QUOTES) ?>"
                           placeholder="ex. Cours 1, Récréation…" required>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="mb-4">
                        <label class="form-label text-xs">Début <span class="form-required">*</span></label>
                        <input type="time" name="heure_debut" id="plageDebut" class="form-input text-sm"
                               value="<?= htmlspecialchars($old['heure_debut'] ?? '', ENT_QUOTES) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label text-xs">Fin <span class="form-required">*</span></label>
                        <input type="time" name="heure_fin" id="plageFin" class="form-input text-sm"
                               value="<?= htmlspecialchars($old['heure_fin'] ?? '', ENT_QUOTES) ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-xs">Ordre d'affichage</label>
                    <input type="number" name="ordre" id="plageOrdre" class="form-input text-sm"
                           value="<?= htmlspecialchars((string)($old['ordre'] ?? ''), ENT_QUOTES) ?>"
                           placeholder="Auto" min="1">
                </div>

                <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-slate-50 transition-colors" id="plageActifWrap">
                    <input type="checkbox" name="actif" value="1" id="plageActif"
                           class="w-4 h-4 accent-violet-600" checked>
                    <span class="text-sm text-slate-700">Plage active</span>
                </label>

                <button type="submit" class="btn btn-success w-full" id="btnPlageSubmit">
                    <i data-lucide="clock" class="w-4 h-4"></i>Ajouter la plage
                </button>
                <button type="button" class="btn btn-secondary w-full hidden" id="btnPlageCancel" onclick="resetFormPlage()">
                    Annuler la modification
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function openEditPlage(id, libelle, debut, fin, ordre, actif) {
    const form = document.getElementById('formPlage');
    form.action = '<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/plages/' + id;
    document.getElementById('plageLibelle').value = libelle;
    document.getElementById('plageDebut').value = debut;
    document.getElementById('plageFin').value = fin;
    document.getElementById('plageOrdre').value = ordre;
    document.getElementById('plageActif').checked = !!actif;
    document.getElementById('formPlageTitre').textContent = 'Modifier — ' + libelle;
    document.getElementById('btnPlageSubmit').innerHTML = '<i data-lucide="save" class="w-4 h-4"></i>Enregistrer';
    document.getElementById('btnPlageCancel').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function resetFormPlage() {
    const form = document.getElementById('formPlage');
    form.action = '<?= BASE_URL ?>/v2/vie-scolaire/emplois-du-temps/plages';
    form.reset();
    document.getElementById('formPlageTitre').textContent = 'Nouvelle plage';
    document.getElementById('btnPlageSubmit').innerHTML = '<i data-lucide="clock" class="w-4 h-4"></i>Ajouter la plage';
    document.getElementById('btnPlageCancel').classList.add('hidden');
    if (window.lucide) lucide.createIcons();
}
</script>
