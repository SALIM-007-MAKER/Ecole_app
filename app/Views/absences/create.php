<?php
$classes  = $classes  ?? [];
$sessions = $sessions ?? [];
$types    = $types    ?? [];
$old      = $old      ?? [];
$csrfToken = \Core\Session::getCsrfToken();
?>

<!-- ── Page header ───────────────────────────────────────────────────────── -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="page-icon"
             style="background:#dcfce7">
            <i data-lucide="plus-circle" class="w-5 h-5" style="color:#059669"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-900" style="letter-spacing:-.03em">
                Ajouter une absence manuellement
            </h2>
            <p class="text-sm text-slate-400">Saisie manuelle d&rsquo;une absence ou d&rsquo;un retard</p>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/absences/liste" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<!-- ── Formulaire ────────────────────────────────────────────────────────── -->
<div class="flex justify-center">
<div class="w-full" style="max-width:42rem">

<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
        <i data-lucide="file-plus" class="w-4 h-4" style="color:#7c3aed"></i>
        <span class="font-semibold text-slate-700">Informations de l&rsquo;absence</span>
    </div>
    <div class="p-5">
        <form method="POST" action="<?= BASE_URL ?>/absences/store"
              id="formCreate" class="space-y-5">
            <input type="hidden" name="_csrf_token"
                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <!-- Ligne 1 : Classe + Élève -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">
                        Classe <span style="color:#ef4444">*</span>
                    </label>
                    <select name="classe_id" class="form-input" id="selClasse" required>
                        <option value="">&mdash; Choisir &mdash;</option>
                        <?php foreach ($classes as $cl): ?>
                        <option value="<?= $cl->id ?>"
                            <?= ($old['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">
                        &#201;l&egrave;ve <span style="color:#ef4444">*</span>
                    </label>
                    <select name="eleve_id" class="form-input" id="selEleve" required>
                        <option value="">&mdash; S&eacute;lectionnez d&rsquo;abord une classe &mdash;</option>
                    </select>
                </div>
            </div>

            <!-- Ligne 2 : Date + Session -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">
                        Date <span style="color:#ef4444">*</span>
                    </label>
                    <input type="date" name="date_absence" class="form-input"
                           value="<?= htmlspecialchars($old['date_absence'] ?? date('Y-m-d'), ENT_QUOTES) ?>"
                           max="<?= date('Y-m-d') ?>" required>
                </div>

                <div>
                    <label class="form-label">
                        Session <span style="color:#ef4444">*</span>
                    </label>
                    <select name="session" class="form-input">
                        <?php foreach ($sessions as $k => $v): ?>
                        <option value="<?= $k ?>"
                            <?= ($old['session'] ?? 'journee') === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Ligne 3 : Type + Durée retard -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Type</label>
                    <select name="type" class="form-input" id="selType">
                        <?php foreach ($types as $k => $v): ?>
                        <option value="<?= $k ?>"
                            <?= ($old['type'] ?? 'absence') === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="dureeGroup"
                     style="display:<?= ($old['type'] ?? 'absence') === 'retard' ? 'block' : 'none' ?>">
                    <label class="form-label">Dur&eacute;e du retard (minutes)</label>
                    <input type="number" name="duree_retard" class="form-input"
                           value="<?= (int)($old['duree_retard'] ?? 15) ?>"
                           min="1" max="240">
                </div>
            </div>

            <!-- Motif -->
            <div>
                <label class="form-label">Motif (optionnel)</label>
                <input type="text" name="motif" class="form-input"
                       value="<?= htmlspecialchars($old['motif'] ?? '', ENT_QUOTES) ?>"
                       placeholder="Raison de l&rsquo;absence ou du retard">
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-2"
                 style="border-top:1px solid #e4e4ec;padding-top:1.25rem;margin-top:.5rem">
                <a href="<?= BASE_URL ?>/absences/liste" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>Annuler
                </a>
                <button type="submit" class="btn btn-success">
                    <i data-lucide="save" class="w-4 h-4"></i>Enregistrer l&rsquo;absence
                </button>
            </div>

        </form>
    </div>
</div>

</div>
</div>

<script>
(function () {
    var selClasse  = document.getElementById('selClasse');
    var selEleve   = document.getElementById('selEleve');
    var selType    = document.getElementById('selType');
    var dureeGrp   = document.getElementById('dureeGroup');
    var oldEleveId = <?= json_encode($old['eleve_id'] ?? 0) ?>;

    selType.addEventListener('change', function() {
        dureeGrp.style.display = this.value === 'retard' ? 'block' : 'none';
    });

    selClasse.addEventListener('change', function() {
        var classeId = this.value;
        selEleve.innerHTML = '<option value="">Chargement&hellip;</option>';
        if (!classeId) {
            selEleve.innerHTML = '<option value="">&mdash; S&eacute;lectionnez d\'abord une classe &mdash;</option>';
            return;
        }
        fetch('<?= BASE_URL ?>/api/eleves?classe_id=' + classeId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                selEleve.innerHTML = '<option value="">&mdash; Choisir un &eacute;l&egrave;ve &mdash;</option>';
                (data.data || data).forEach(function(e) {
                    var opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.prenom + ' ' + e.nom + (e.matricule ? ' (' + e.matricule + ')' : '');
                    if (e.id == oldEleveId) opt.selected = true;
                    selEleve.appendChild(opt);
                });
            })
            .catch(function() {
                selEleve.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    });

    if (selClasse.value) selClasse.dispatchEvent(new Event('change'));
})();
</script>
