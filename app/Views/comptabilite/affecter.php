<?php
$fraisTypes    = $fraisTypes    ?? [];
$classes       = $classes       ?? [];
$anneesOptions = $anneesOptions ?? [];
$currentAnnee  = $currentAnnee  ?? '';
$old           = $old           ?? [];
$csrfToken     = \Core\Session::getCsrfToken();
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="user-plus" class="w-6 h-6 text-emerald-600"></i>Affecter des frais aux élèves
    </h2>
    <a href="<?= BASE_URL ?>/comptabilite/frais" class="btn btn-outline">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<div class="flex justify-center">
<div class="w-full max-w-2xl">
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-6 py-4">
        <p class="font-semibold text-slate-800">Attribution des frais scolaires</p>
        <p class="text-sm text-slate-500 mt-0.5">Créer ou mettre à jour les frais pour une classe ou tous les élèves</p>
    </div>
    <div class="p-6">
        <form method="POST" action="<?= BASE_URL ?>/comptabilite/frais/affecter" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Type de frais <span class="text-red-500">*</span></label>
                    <select name="frais_type_id" class="form-select" id="selFraisType" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($fraisTypes as $ft): ?>
                        <option value="<?= $ft->id ?>"
                                data-montant="<?= $ft->montant_defaut ?>"
                                <?= ($old['frais_type_id'] ?? '') == $ft->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ft->nom, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Année scolaire</label>
                    <select name="annee_scolaire" class="form-select">
                        <?php foreach ($anneesOptions as $a): ?>
                        <option value="<?= $a ?>" <?= $a === $currentAnnee ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant" id="inputMontant" class="form-input"
                           value="<?= $old['montant'] ?? '' ?>" min="0" step="0.01" required
                           placeholder="Montant en FCFA">
                </div>

                <div>
                    <label class="form-label">Date d'échéance</label>
                    <input type="date" name="echeance" class="form-input"
                           value="<?= $old['echeance'] ?? '' ?>">
                </div>
            </div>

            <div>
                <label class="form-label mb-2">Appliquer à</label>
                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="cible" id="cClasse" value="classe"
                               class="accent-violet-600"
                               <?= ($old['cible'] ?? 'classe') !== 'tous' ? 'checked' : '' ?>>
                        <span class="text-sm text-slate-700">Une classe</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="cible" id="cTous" value="tous"
                               class="accent-violet-600"
                               <?= ($old['cible'] ?? '') === 'tous' ? 'checked' : '' ?>>
                        <span class="text-sm text-slate-700">Tous les élèves actifs</span>
                    </label>
                </div>
            </div>

            <div id="groupClasse">
                <label class="form-label">Classe</label>
                <select name="classe_id" class="form-select">
                    <option value="">— Choisir une classe —</option>
                    <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl->id ?>"
                        <?= ($old['classe_id'] ?? '') == $cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rounded-lg bg-sky-50 border border-sky-200 text-sky-800 text-sm px-4 py-3 flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                Si le frais existe déjà pour un élève, son montant et son échéance seront mis à jour.
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="<?= BASE_URL ?>/comptabilite/frais" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="users" class="w-4 h-4"></i>Affecter
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<script>
(function() {
    var selFT    = document.getElementById('selFraisType');
    var inputMt  = document.getElementById('inputMontant');
    var grpClass = document.getElementById('groupClasse');
    var radios   = document.querySelectorAll('input[name="cible"]');

    selFT.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        var mt  = opt.dataset.montant;
        if (mt && !inputMt.value) inputMt.value = parseFloat(mt).toFixed(2);
    });

    function toggleClasse() {
        var checked = document.querySelector('input[name="cible"]:checked');
        grpClass.style.display = checked && checked.value === 'tous' ? 'none' : 'block';
    }
    radios.forEach(function(r) { r.addEventListener('change', toggleClasse); });
    toggleClasse();
})();
</script>
