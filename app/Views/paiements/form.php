<?php
$eleve       = $eleve       ?? null;
$fraisEleve  = $fraisEleve  ?? [];
$modes       = $modes       ?? [];
$annee       = $annee       ?? '';
$old         = $old         ?? [];
$csrfToken   = \Core\Session::getCsrfToken();

// Générer la liste des années autour de l'année courante
$y = (int)date('m') >= 9 ? (int)date('Y') : (int)date('Y') - 1;
$anneesOptions = [
    ($y - 1) . '-' . $y,
    $y . '-' . ($y + 1),
    ($y + 1) . '-' . ($y + 2),
];
if ($annee && !in_array($annee, $anneesOptions)) {
    array_unshift($anneesOptions, $annee);
}
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="banknote" class="w-6 h-6 text-emerald-600"></i>Nouveau paiement
    </h2>
    <a href="<?= BASE_URL ?>/paiements" class="btn btn-outline">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<div class="flex justify-center">
<div class="w-full max-w-2xl">
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="p-6">
        <form method="POST" action="<?= BASE_URL ?>/paiements/store" id="formPaiement" class="space-y-5">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <!-- Élève + année -->
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                <div class="sm:col-span-3">
                    <label class="form-label">Élève <span class="text-red-500">*</span></label>
                    <?php if ($eleve): ?>
                    <div class="form-input bg-slate-50 flex items-center gap-2 cursor-default">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                        <span class="font-semibold text-slate-800">
                            <?= htmlspecialchars($eleve->nom . ' ' . $eleve->prenom, ENT_QUOTES) ?>
                        </span>
                        <?php if (!empty($eleve->classe_nom)): ?>
                        <span class="text-slate-400 text-xs">— <?= htmlspecialchars($eleve->classe_niveau . ' ' . $eleve->classe_nom, ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="eleve_id" id="selEleve" value="<?= (int)$eleve->id ?>">
                    <?php else: ?>
                    <input type="number" name="eleve_id" id="selEleve" class="form-input"
                           value="<?= (int)($old['eleve_id'] ?? 0) ?>"
                           placeholder="ID élève" required>
                    <?php endif; ?>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Année scolaire</label>
                    <select name="annee_scolaire" id="selAnnee" class="form-select">
                        <?php foreach ($anneesOptions as $a): ?>
                        <option value="<?= $a ?>" <?= ($old['annee_scolaire'] ?? $annee) === $a ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Frais associé -->
            <div>
                <label class="form-label">Frais à régler <span class="text-xs text-slate-400">(optionnel)</span></label>
                <select name="frais_eleve_id" id="selFrais" class="form-select">
                    <option value="">— Paiement libre (sans frais associé) —</option>
                    <?php foreach ($fraisEleve as $fe): ?>
                    <option value="<?= $fe->id ?>"
                            data-reste="<?= $fe->reste ?>"
                            <?= ($old['frais_eleve_id'] ?? '') == $fe->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fe->frais_nom, ENT_QUOTES) ?>
                        — Reste : <?= number_format((float)$fe->reste, 2, ',', ' ') ?> FCFA
                        (<?= htmlspecialchars($fe->statut, ENT_QUOTES) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs mt-1" id="resteInfo"></p>
            </div>

            <!-- Montant + Mode + Date -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant" id="inputMontant" class="form-input"
                           value="<?= $old['montant'] ?? '' ?>" min="0.01" step="0.01" required>
                </div>
                <div>
                    <label class="form-label">Mode de paiement <span class="text-red-500">*</span></label>
                    <select name="mode_paiement" class="form-select" required>
                        <?php foreach ($modes as $k => $m): ?>
                        <option value="<?= $k ?>" <?= ($old['mode_paiement'] ?? 'especes') === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['label'], ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date_paiement" class="form-input"
                           value="<?= $old['date_paiement'] ?? date('Y-m-d') ?>" required>
                </div>
            </div>

            <!-- Référence + Observations -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Référence / N° reçu</label>
                    <input type="text" name="reference" class="form-input"
                           value="<?= htmlspecialchars($old['reference'] ?? '', ENT_QUOTES) ?>"
                           placeholder="Laisser vide pour génération auto">
                </div>
                <div>
                    <label class="form-label">Observations</label>
                    <input type="text" name="observations" class="form-input"
                           value="<?= htmlspecialchars($old['observations'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="<?= BASE_URL ?>/paiements" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<script>
(function() {
    var selEleve  = document.getElementById('selEleve');
    var selAnnee  = document.getElementById('selAnnee');
    var selFrais  = document.getElementById('selFrais');
    var inputMt   = document.getElementById('inputMontant');
    var resteInfo = document.getElementById('resteInfo');

    function loadFrais() {
        var eleveId = selEleve.value;
        var annee   = selAnnee.value;
        if (!eleveId) { selFrais.innerHTML = '<option value="">— Paiement libre —</option>'; return; }
        fetch('<?= BASE_URL ?>/api/frais-eleve?eleve_id=' + eleveId + '&annee=' + encodeURIComponent(annee))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var html = '<option value="">— Paiement libre (sans frais associé) —</option>';
                data.forEach(function(fe) {
                    html += '<option value="' + fe.id + '" data-reste="' + fe.reste + '">'
                          + fe.frais_nom + ' — Reste : ' + parseFloat(fe.reste).toLocaleString('fr-FR') + ' FCFA'
                          + ' (' + fe.statut + ')</option>';
                });
                selFrais.innerHTML = html;
                resteInfo.textContent = '';
            })
            .catch(function() {});
    }

    selEleve.addEventListener('change', loadFrais);
    selAnnee.addEventListener('change', loadFrais);

    selFrais.addEventListener('change', function() {
        var opt = this.options[this.selectedIndex];
        var reste = opt.dataset.reste;
        if (reste && parseFloat(reste) > 0) {
            inputMt.value = parseFloat(reste).toFixed(2);
            resteInfo.textContent = 'Reste dû : ' + parseFloat(reste).toLocaleString('fr-FR', {minimumFractionDigits:2}) + ' FCFA';
            resteInfo.className = 'text-xs mt-1 text-amber-600 font-semibold';
        } else {
            resteInfo.textContent = '';
        }
    });
})();
</script>
