<?php
$seance        = $seance        ?? null;
$classes       = $classes       ?? [];
$salles        = $salles        ?? [];
$creneaux      = $creneaux      ?? [];
$matieres      = $matieres      ?? [];
$profs         = $profs         ?? [];
$old           = $old           ?? [];
$annee         = $annee         ?? '';
$anneesOptions = $anneesOptions ?? [];
$jours         = $jours         ?? \App\Models\EmploiDuTempsModel::JOURS;
$isEdit        = $seance !== null;
$csrfToken     = \Core\Session::getCsrfToken();
$exceptId      = $isEdit ? $seance->id : null;
$v = fn(string $k, $def = '') => $old[$k] ?? ($isEdit ? ($seance->$k ?? $def) : $def);
?>

<!-- Header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="<?= $isEdit ? 'pencil' : 'plus-circle' ?>" class="w-5 h-5 text-violet-600"></i>
            <?= $isEdit ? 'Modifier la séance' : 'Ajouter une séance' ?>
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">
            <?= $isEdit ? 'Modifiez les informations de la séance existante' : 'Planifiez une nouvelle séance dans l\'emploi du temps' ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/emplois-du-temps?annee=<?= urlencode($annee) ?>" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour au planning
    </a>
</div>

<!-- Alertes conflits -->
<div id="conflictAlerts" class="mb-4"></div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

    <!-- Formulaire principal -->
    <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="calendar-check" class="w-4 h-4 text-violet-600"></i>
            <span class="font-semibold text-slate-700">Informations de la séance</span>
        </div>
        <div class="p-5 p-5">
            <form method="POST"
                  action="<?= $isEdit ? BASE_URL.'/emplois-du-temps/'.$seance->id : BASE_URL.'/emplois-du-temps/store' ?>"
                  id="formEdt" class="space-y-5">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

                <!-- Ligne 1 : Année / Jour / Créneau -->
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Planification</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="mb-4">
                            <label class="form-label">Année scolaire</label>
                            <select name="annee_scolaire" id="selAnnee" class="form-input" onchange="checkConflicts()">
                                <?php foreach ($anneesOptions as $a): ?>
                                <option value="<?= $a ?>" <?= $v('annee_scolaire', $annee) === $a ? 'selected' : '' ?>><?= $a ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Jour <span class="text-red-500">*</span></label>
                            <select name="jour_semaine" id="selJour" class="form-input" required onchange="checkConflicts()">
                                <option value="">— Choisir —</option>
                                <?php foreach ($jours as $num => $nom): ?>
                                <option value="<?= $num ?>" <?= (string)$v('jour_semaine') === (string)$num ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nom, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Créneau <span class="text-red-500">*</span></label>
                            <select name="creneau_id" id="selCreneau" class="form-input" required onchange="checkConflicts()">
                                <option value="">— Choisir —</option>
                                <?php foreach ($creneaux as $cr): ?>
                                <option value="<?= $cr->id ?>" <?= $v('creneau_id') == $cr->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cr->nom, ENT_QUOTES) ?>
                                    (<?= substr($cr->heure_debut,0,5) ?>–<?= substr($cr->heure_fin,0,5) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Séparateur -->
                <hr class="border-slate-100">

                <!-- Ligne 2 : Classe / Matière / Enseignant / Salle -->
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Participants & Lieu</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="form-label">Classe <span class="text-red-500">*</span></label>
                            <select name="classe_id" id="selClasse" class="form-input" required onchange="checkConflicts()">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl->id ?>" <?= $v('classe_id') == $cl->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Matière <span class="text-red-500">*</span></label>
                            <select name="matiere_id" id="selMatiere" class="form-input" required onchange="updatePreview()">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($matieres as $mat): ?>
                                <option value="<?= $mat->id ?>"
                                        data-color="<?= \App\Models\EmploiDuTempsModel::getColor($mat->id) ?>"
                                        <?= $v('matiere_id') == $mat->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mat->nom, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Enseignant <span class="text-red-500">*</span></label>
                            <select name="professeur_id" id="selProf" class="form-input" required onchange="checkConflicts()">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($profs as $p): ?>
                                <option value="<?= $p->id ?>" <?= $v('professeur_id') == $p->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p->prenom . ' ' . $p->nom, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">
                                Salle
                                <span class="text-slate-400 font-normal text-xs ml-1">(optionnelle)</span>
                            </label>
                            <select name="salle_id" id="selSalle" class="form-input" onchange="checkConflicts()">
                                <option value="">— Aucune salle —</option>
                                <?php foreach ($salles as $s): ?>
                                <option value="<?= $s->id ?>" <?= $v('salle_id') == $s->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s->nom, ENT_QUOTES) ?>
                                    <?php if ($s->batiment): ?>(<?= htmlspecialchars($s->batiment, ENT_QUOTES) ?>)<?php endif; ?>
                                    — <?= $s->capacite ?> pl.
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                    <label class="form-label">
                        Notes
                        <span class="text-slate-400 font-normal text-xs ml-1">(optionnel)</span>
                    </label>
                    <input type="text" name="notes" class="form-input"
                           value="<?= htmlspecialchars($v('notes'), ENT_QUOTES) ?>"
                           placeholder="Informations complémentaires…">
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <a href="<?= BASE_URL ?>/emplois-du-temps?annee=<?= urlencode($annee) ?>"
                       class="btn btn-secondary">Annuler</a>
                    <button type="submit" id="btnSubmit" class="btn btn-primary">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <?= $isEdit ? 'Mettre à jour' : 'Enregistrer la séance' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panneau latéral -->
    <div class="lg:col-span-2 space-y-4">

        <!-- Vérification conflits -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                <span class="font-semibold text-slate-700">Vérification des conflits</span>
            </div>
            <div id="conflictPanel" class="p-5 p-4">
                <div class="flex flex-col items-center justify-center py-4 text-center gap-2">
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center">
                        <i data-lucide="info" class="w-5 h-5 text-slate-400"></i>
                    </div>
                    <p class="text-sm text-slate-400">Remplissez les champs pour vérifier automatiquement.</p>
                </div>
            </div>
        </div>

        <!-- Aperçu de la séance -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="eye" class="w-4 h-4 text-violet-500"></i>
                <span class="font-semibold text-slate-700">Aperçu de la séance</span>
            </div>
            <div class="p-5 p-4">
                <div id="preview" style="background:#7c3aed;border-left:4px solid rgba(0,0,0,.15);color:#fff;border-radius:10px;padding:16px;min-height:90px;transition:background .3s">
                    <div style="font-weight:700;font-size:.9rem;margin-bottom:4px" id="prevMat">— Matière —</div>
                    <div style="font-size:.82rem;opacity:.9;margin-bottom:2px" id="prevProf">— Enseignant —</div>
                    <div style="font-size:.75rem;opacity:.8;margin-bottom:2px" id="prevSalle"></div>
                    <div style="font-size:.75rem;opacity:.75" id="prevSlot">— Jour &amp; Créneau —</div>
                </div>
                <p class="text-xs text-slate-400 mt-3 text-center">Cet aperçu reflète votre sélection en temps réel.</p>
            </div>
        </div>

        <!-- Aide rapide -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm bg-violet-50 border-violet-100">
            <div class="p-5 p-4 space-y-2">
                <p class="text-xs font-semibold text-violet-700 flex items-center gap-1.5">
                    <i data-lucide="lightbulb" class="w-3.5 h-3.5"></i>Conseils
                </p>
                <ul class="text-xs text-violet-600 space-y-1.5">
                    <li class="flex items-start gap-1.5"><i data-lucide="check" class="w-3 h-3 mt-0.5 shrink-0"></i>Choisissez d'abord le jour et le créneau pour détecter les conflits.</li>
                    <li class="flex items-start gap-1.5"><i data-lucide="check" class="w-3 h-3 mt-0.5 shrink-0"></i>Le système vérifie automatiquement prof, classe et salle.</li>
                    <li class="flex items-start gap-1.5"><i data-lucide="check" class="w-3 h-3 mt-0.5 shrink-0"></i>La salle est optionnelle mais recommandée.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var BASE    = '<?= BASE_URL ?>';
    var EXCEPT  = '<?= $exceptId ?? '' ?>';
    var selClasse  = document.getElementById('selClasse');
    var selProf    = document.getElementById('selProf');
    var selSalle   = document.getElementById('selSalle');
    var selCreneau = document.getElementById('selCreneau');
    var selJour    = document.getElementById('selJour');
    var selAnnee   = document.getElementById('selAnnee');
    var selMatiere = document.getElementById('selMatiere');
    var btnSubmit  = document.getElementById('btnSubmit');
    var panel      = document.getElementById('conflictPanel');
    var prevMat    = document.getElementById('prevMat');
    var prevProf   = document.getElementById('prevProf');
    var prevSalle  = document.getElementById('prevSalle');
    var prevSlot   = document.getElementById('prevSlot');
    var preview    = document.getElementById('preview');
    var timer      = null;

    window.updatePreview = function() {
        var matOpt    = selMatiere.options[selMatiere.selectedIndex];
        var profOpt   = selProf.options[selProf.selectedIndex];
        var salleOpt  = selSalle.options[selSalle.selectedIndex];
        var crOpt     = selCreneau.options[selCreneau.selectedIndex];
        var jourOpt   = selJour.options[selJour.selectedIndex];
        var color     = (matOpt && matOpt.dataset.color) ? matOpt.dataset.color : '#7c3aed';
        preview.style.background = color;
        prevMat.textContent  = matOpt && matOpt.value  ? matOpt.text  : '— Matière —';
        prevProf.textContent = profOpt && profOpt.value ? profOpt.text : '— Enseignant —';
        prevSalle.textContent= salleOpt && salleOpt.value ? '📍 ' + salleOpt.text : '';
        prevSlot.textContent = (jourOpt && jourOpt.value && crOpt && crOpt.value)
            ? jourOpt.text + ' — ' + crOpt.text : '— Jour & Créneau —';
    };

    window.checkConflicts = function() {
        clearTimeout(timer);
        updatePreview();
        timer = setTimeout(doCheck, 350);
    };

    function doCheck() {
        if (!selClasse.value || !selProf.value || !selCreneau.value || !selJour.value) {
            panel.innerHTML = '<div class="flex flex-col items-center justify-center py-4 text-center gap-2"><div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center"><i data-lucide="info" class="w-5 h-5 text-slate-400"></i></div><p class="text-sm text-slate-400">Remplissez tous les champs requis.</p></div>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            btnSubmit.disabled = false; return;
        }
        panel.innerHTML = '<div class="flex items-center justify-center gap-2 py-4 text-sm text-slate-400"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>Vérification en cours…</div>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        var params = new URLSearchParams({
            classe_id: selClasse.value, prof_id: selProf.value, salle_id: selSalle.value,
            creneau_id: selCreneau.value, jour: selJour.value, annee: selAnnee.value,
            except_id: EXCEPT
        });
        fetch(BASE + '/api/emploi-du-temps/conflits?' + params.toString())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var conflicts = (data.conflicts || []);
                if (!conflicts.length) {
                    panel.innerHTML = '<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-emerald-200 bg-emerald-50 text-emerald-800 py-3 mb-0 text-sm"><i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i><span>Aucun conflit — créneau disponible.</span></div>';
                    btnSubmit.disabled = false;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } else {
                    var html = '<div class="space-y-2">';
                    conflicts.forEach(function(c) {
                        html += '<div class="alert alert-danger py-2 text-sm"><i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i><span>' + c.message + '</span></div>';
                    });
                    html += '</div>';
                    panel.innerHTML = html;
                    btnSubmit.disabled = true;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            })
            .catch(function() {
                panel.innerHTML = '<div class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900 py-2 mb-0 text-sm">Impossible de vérifier les conflits.</div>';
                btnSubmit.disabled = false;
            });
    }

    [selClasse, selProf, selSalle, selCreneau, selJour, selAnnee].forEach(function(el) {
        el.addEventListener('change', checkConflicts);
    });
    selMatiere.addEventListener('change', updatePreview);
    updatePreview();
    if (EXCEPT) { doCheck(); }
})();
</script>
