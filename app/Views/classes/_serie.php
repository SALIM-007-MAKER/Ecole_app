<?php
/**
 * Champ « Série » d'une classe de lycée (partiel de classes/create et classes/edit).
 * @var bool       $serieDispo       colonne classes.serie présente (migration T074)
 * @var array      $seriesParNiveau  ['Seconde' => ['A','C'], …] séries activées par niveau
 * @var string|null $serieActuelle   série enregistrée de la classe (modification)
 * @var array      $old              saisie précédente (après erreur de validation)
 * @var array      $errors
 */
if (empty($serieDispo)) { return; }
$niveauActuel = isset($classe) ? (string)($classe->niveau ?? '') : '';
$serieChoisie = array_key_exists('serie', $old ?? []) ? (string)$old['serie'] : (string)($serieActuelle ?? '');
?>
<div id="bloc-serie" class="sm:col-span-3" style="display:none">
    <label class="form-label" for="serie">
        Série <span class="text-slate-400 font-normal">(optionnelle, lycée uniquement)</span>
    </label>
    <select id="serie" name="serie" class="form-input <?= isset($errors['serie']) ? 'border-red-400' : '' ?>" disabled></select>
    <p id="serie-aide" class="text-xs text-slate-500 mt-1"></p>
    <?php if (isset($errors['serie'])): ?>
    <p class="form-error"><?= htmlspecialchars($errors['serie'][0], ENT_QUOTES) ?></p>
    <?php endif; ?>
</div>
<script>
(function () {
    var par = <?= json_encode($seriesParNiveau ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var lycee = ['Seconde', 'Première', 'Terminale'];
    var actuelle = <?= json_encode($serieActuelle ?? null, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var niveauActuel = <?= json_encode($niveauActuel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var choisie = <?= json_encode($serieChoisie, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var niv = document.getElementById('niveau'), bloc = document.getElementById('bloc-serie'),
        sel = document.getElementById('serie'), aide = document.getElementById('serie-aide');
    function option(valeur, texte, choisi) {
        var o = document.createElement('option'); o.value = valeur; o.textContent = texte; o.selected = !!choisi; return o;
    }
    function sync() {
        var n = niv.value;
        if (lycee.indexOf(n) < 0) { bloc.style.display = 'none'; sel.disabled = true; return; }   // hors lycée : champ non envoyé
        bloc.style.display = '';
        var codes = (par[n] || []).slice();
        // La série actuelle reste proposée tant que le niveau est inchangé, même si elle a été désactivée depuis.
        var desactivee = actuelle && n === niveauActuel && codes.indexOf(actuelle) < 0;
        if (desactivee) { codes.push(actuelle); }
        if (!codes.length) {
            sel.style.display = 'none'; sel.disabled = true;
            aide.textContent = 'Aucune série activée pour ' + n + ' (Paramètres → Organisation académique).';
            return;
        }
        sel.style.display = ''; sel.disabled = false;
        while (sel.firstChild) { sel.removeChild(sel.firstChild); }
        sel.appendChild(option('', 'Aucune série', choisie === ''));
        codes.forEach(function (c) {
            sel.appendChild(option(c, 'Série ' + c + (desactivee && c === actuelle ? ' (désactivée)' : ''), c === choisie));
        });
        aide.textContent = '';
    }
    niv.addEventListener('change', function () { choisie = ''; sync(); });
    sync();
})();
</script>
