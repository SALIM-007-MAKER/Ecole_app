<?php
$creneaux    = $creneaux ?? [];
$types       = $types    ?? \App\Models\CreneauModel::TYPES;
$old         = $old      ?? [];
$csrfToken   = \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canCreate   = in_array('emploi_du_temps.create', $currentUser['permissions'] ?? [], true);
$canEdit     = in_array('emploi_du_temps.edit',   $currentUser['permissions'] ?? [], true);
$nextOrdre   = count($creneaux) + 1;

function crTypeBadge(string $type, array $types): string {
    $t = $types[$type] ?? ['label'=>$type,'badge'=>'neutral'];
    $cl = match($t['badge'] ?? '') {
        'primary' => 'bg-violet-100 text-violet-700', 'success' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-800', 'info'    => 'bg-sky-100 text-sky-700',
        default   => 'bg-slate-100 text-slate-600',
    };
    return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap ' . $cl . ' text-xs">' . htmlspecialchars($t['label'], ENT_QUOTES) . '</span>';
}
?>

<div class="flex items-center justify-between gap-4 mb-5">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
        <i data-lucide="clock" class="w-5 h-5 text-violet-600"></i>Créneaux horaires
    </h2>
    <a href="<?= BASE_URL ?>/emplois-du-temps" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Emploi du temps
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Timeline créneaux -->
    <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="list" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700"><?= count($creneaux) ?> créneau(x) configuré(s)</span>
        </div>
        <div class="p-5 p-4">
            <?php if (empty($creneaux)): ?>
            <div class="text-center py-10">
                <i data-lucide="clock" class="w-10 h-10 text-slate-200 mx-auto mb-2"></i>
                <p class="text-slate-400">Aucun créneau configuré</p>
            </div>
            <?php else: ?>
            <div class="relative pl-16">
                <div class="absolute left-0 top-0 bottom-0 w-px bg-slate-100 ml-8"></div>
                <?php foreach ($creneaux as $cr):
                    $isCours = $cr->type === 'cours';
                    $durMin  = ($cr->heure_fin && $cr->heure_debut)
                        ? (strtotime($cr->heure_fin) - strtotime($cr->heure_debut)) / 60 : 0;
                    $durStr  = $durMin >= 60
                        ? floor($durMin/60) . 'h' . ($durMin % 60 ? str_pad($durMin % 60, 2, '0', STR_PAD_LEFT) : '')
                        : $durMin . ' min';
                ?>
                <div class="flex items-start gap-4 mb-3 relative">
                    <!-- Heure à gauche -->
                    <div style="position:absolute;left:-64px;width:56px;text-align:right;font-size:.7rem;color:#94a3b8;padding-top:10px">
                        <?= substr($cr->heure_debut, 0, 5) ?>
                    </div>
                    <!-- Point timeline -->
                    <div class="absolute -left-2 top-2.5 w-4 h-4 rounded-full border-2 <?= $isCours ? 'bg-violet-500 border-violet-500' : 'bg-slate-300 border-slate-300' ?>"></div>

                    <!-- Bloc -->
                    <div class="flex-1 border <?= $isCours ? 'border-violet-200 bg-violet-50' : 'border-slate-200 bg-slate-50' ?> rounded-lg p-3 flex items-center gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($cr->nom, ENT_QUOTES) ?></span>
                                <?= crTypeBadge($cr->type, $types) ?>
                                <?php if (!$cr->actif): ?>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600 text-xs">Inactif</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-slate-400">
                                <?= substr($cr->heure_debut,0,5) ?> — <?= substr($cr->heure_fin,0,5) ?>
                                <span class="mx-1">·</span><?= $durStr ?>
                            </div>
                        </div>
                        <?php if ($canEdit): ?>
                        <div class="flex items-center gap-1">
                            <button onclick="openEditCreneau(<?= htmlspecialchars(json_encode([
                                'id'         => $cr->id,
                                'nom'        => $cr->nom,
                                'heure_debut'=> substr($cr->heure_debut,0,5),
                                'heure_fin'  => substr($cr->heure_fin,0,5),
                                'type'       => $cr->type,
                                'ordre'      => $cr->ordre,
                                'actif'      => (bool)$cr->actif,
                            ]), ENT_QUOTES) ?>)"
                                    class="btn btn-ghost btn-icon text-violet-500">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <button onclick="openDelCreneau(<?= $cr->id ?>, '<?= htmlspecialchars(addslashes($cr->nom), ENT_QUOTES) ?>')"
                                    class="btn btn-ghost btn-icon text-red-400">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulaire ajout -->
    <?php if ($canCreate): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-600"></i>
            <span class="font-semibold text-slate-700">Nouveau créneau</span>
        </div>
        <div class="p-5 p-4">
            <form method="POST" action="<?= BASE_URL ?>/creneaux/store" class="space-y-3">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <div>
                    <label class="form-label text-xs">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="nom" class="form-input text-sm"
                           value="<?= htmlspecialchars($old['nom'] ?? '', ENT_QUOTES) ?>"
                           placeholder="ex. Cours 3" required>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="form-label text-xs">Heure début <span class="text-red-500">*</span></label>
                        <input type="time" name="heure_debut" class="form-input text-sm"
                               value="<?= $old['heure_debut'] ?? '' ?>" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Heure fin <span class="text-red-500">*</span></label>
                        <input type="time" name="heure_fin" class="form-input text-sm"
                               value="<?= $old['heure_fin'] ?? '' ?>" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="form-label text-xs">Type</label>
                        <select name="type" class="form-input text-sm">
                            <?php foreach ($types as $k => $t): ?>
                            <option value="<?= $k ?>" <?= ($old['type'] ?? 'cours') === $k ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['label'], ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs">Ordre</label>
                        <input type="number" name="ordre" class="form-input text-sm"
                               value="<?= $old['ordre'] ?? $nextOrdre ?>" min="1">
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-full text-sm">
                    <i data-lucide="clock" class="w-4 h-4"></i>Ajouter
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<!-- Modal modification créneau -->
<div id="modalEditCr" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex hidden">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl max-w-md">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <span class="font-semibold text-slate-800">Modifier le créneau</span>
            <button onclick="document.getElementById('modalEditCr').classList.remove('active')"
                    class="btn btn-ghost btn-icon text-slate-400">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="formEditCr" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
            <div class="px-5 py-5 space-y-3">
                <div>
                    <label class="form-label text-xs">Nom</label>
                    <input type="text" name="nom" id="editCrNom" class="form-input" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label text-xs">Heure début</label>
                        <input type="time" name="heure_debut" id="editCrDebut" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Heure fin</label>
                        <input type="time" name="heure_fin" id="editCrFin" class="form-input" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label text-xs">Type</label>
                        <select name="type" id="editCrType" class="form-input">
                            <?php foreach ($types as $k => $t): ?>
                            <option value="<?= $k ?>"><?= htmlspecialchars($t['label'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label text-xs">Ordre</label>
                        <input type="number" name="ordre" id="editCrOrdre" class="form-input" min="1">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="actif" id="editCrActif" value="1"
                           class="w-4 h-4 accent-violet-600">
                    <label for="editCrActif" class="text-sm text-slate-700">Actif</label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalEditCr').classList.remove('active')"
                        class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i>Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal suppression créneau -->
<div id="modalDelCr" class="modal-overlay fixed inset-0 z-[9000] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm [&.active]:flex hidden">
    <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-2xl max-w-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <span class="font-semibold text-slate-800">Supprimer le créneau</span>
            <button onclick="document.getElementById('modalDelCr').classList.remove('active')"
                    class="btn btn-ghost btn-icon text-slate-400">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="px-5 py-5">
            <p class="text-sm text-slate-600">
                Supprimer le créneau <strong id="delCrNom" class="text-slate-900"></strong> ?
            </p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 justify-end gap-2">
            <button onclick="document.getElementById('modalDelCr').classList.remove('active')"
                    class="btn btn-secondary">Annuler</button>
            <form id="formDelCr" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <button class="btn btn-danger"><i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer</button>
            </form>
        </div>
    </div>
</div>

<script>
var BASE = '<?= BASE_URL ?>';
function openEditCreneau(data) {
    document.getElementById('editCrNom').value   = data.nom;
    document.getElementById('editCrDebut').value = data.heure_debut;
    document.getElementById('editCrFin').value   = data.heure_fin;
    document.getElementById('editCrType').value  = data.type;
    document.getElementById('editCrOrdre').value = data.ordre;
    document.getElementById('editCrActif').checked = data.actif;
    document.getElementById('formEditCr').action = BASE + '/creneaux/' + data.id;
    document.getElementById('modalEditCr').classList.add('active');
}
function openDelCreneau(id, nom) {
    document.getElementById('delCrNom').textContent = nom;
    document.getElementById('formDelCr').action = BASE + '/creneaux/' + id + '/delete';
    document.getElementById('modalDelCr').classList.add('active');
}
</script>
<?php endif; ?>
