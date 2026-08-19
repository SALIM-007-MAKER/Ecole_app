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
    les autres modules (classes, inscriptions, statistiques) n'afficheront que ces cycles.
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

    <?php if ($canEdit): ?>
    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer
        </button>
    </div>
    <?php endif; ?>

    </fieldset>
</form>
