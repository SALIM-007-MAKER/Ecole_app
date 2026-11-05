<?php
$errors        = $errors        ?? [];
$cyclesTous    = $cyclesTous    ?? [];
$cyclesActifs  = $cyclesActifs  ?? [];
$canEdit       = $canEdit       ?? false;
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="layers" class="w-5 h-5 text-violet-600"></i>
            Organisation académique
        </h2>
        <p class="text-sm text-slate-500">Cycles scolaires proposés par votre établissement</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msg): ?>
        <li class="text-sm"><?= htmlspecialchars((string)$msg, ENT_QUOTES) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-sky-50 border border-sky-200 rounded-xl p-4 mb-6 text-sm text-sky-700">
    <i data-lucide="info" class="w-4 h-4 inline-block align-text-top mr-1"></i>
    La nomenclature des niveaux (Préscolaire, Primaire, Collège, Lycée) suit le système éducatif
    officiel du Niger. Sélectionnez les cycles réellement proposés par votre établissement —
    les formulaires (classes, frais, génération de factures) ne proposeront que ces cycles.
    Les classes et données déjà enregistrées pour un cycle désactivé restent visibles, modifiables et inchangées.
</div>

<form method="POST" action="<?= BASE_URL ?>/parametres/academique" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="graduation-cap" class="w-4 h-4 text-violet-600"></i>Cycles proposés
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($cyclesTous as $cycle => $niveaux): ?>
            <label class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50">
                <input type="checkbox" name="cycles[]" value="<?= htmlspecialchars($cycle, ENT_QUOTES) ?>"
                       <?= in_array($cycle, $cyclesActifs, true) ? 'checked' : '' ?>
                       class="accent-violet-600 mt-0.5">
                <span>
                    <span class="block font-medium text-slate-800"><?= htmlspecialchars($cycle, ENT_QUOTES) ?></span>
                    <span class="block text-xs text-slate-400"><?= htmlspecialchars(implode(' · ', $niveaux), ENT_QUOTES) ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    $serieDispo      = $serieDispo ?? false;
    $seriesParNiveau = $seriesParNiveau ?? [];
    if ($serieDispo && (in_array('Lycée', $cyclesActifs, true) || $seriesParNiveau !== [])):
        $niveauxLycee = \App\Services\SeriesLyceeService::NIVEAUX_LYCEE;
    ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
            <i data-lucide="split" class="w-4 h-4 text-violet-600"></i>Séries du lycée
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" name="series_present" value="1">
            <p class="text-sm text-slate-500">
                Activez, pour chaque niveau, les séries que votre établissement propose. Rien n'est supposé : un niveau sans série
                (par exemple une Seconde en tronc commun) reste sans série. Plusieurs classes parallèles par série sont possibles
                (Terminale D1, D2…). Désactiver une série ne modifie aucune classe existante.
            </p>
            <?php foreach ($niveauxLycee as $niv):
                $actives = $seriesParNiveau[$niv] ?? [];
                $proposees = array_values(array_unique(array_merge(\App\Services\SeriesLyceeService::SERIES_PAR_DEFAUT, $actives)));
            ?>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-center rounded-lg border border-slate-200 p-3">
                <div class="font-medium text-slate-800"><?= htmlspecialchars($niv, ENT_QUOTES) ?></div>
                <div class="sm:col-span-2 flex flex-wrap gap-4">
                    <?php foreach ($proposees as $code): ?>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" class="accent-violet-600" name="series[<?= htmlspecialchars($niv, ENT_QUOTES) ?>][]"
                               value="<?= htmlspecialchars($code, ENT_QUOTES) ?>" <?= in_array($code, $actives, true) ? 'checked' : '' ?>>
                        Série <?= htmlspecialchars($code, ENT_QUOTES) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div>
                    <input type="text" class="form-input" name="series_perso[<?= htmlspecialchars($niv, ENT_QUOTES) ?>]" maxlength="60"
                           placeholder="Autres (ex. B, E)" aria-label="Autres séries pour <?= htmlspecialchars($niv, ENT_QUOTES) ?>">
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($errors['series'])): ?>
            <p class="form-error"><?= htmlspecialchars((string)$errors['series'], ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
        </button>
    </div>
    <?php endif; ?>

    </fieldset>
</form>
