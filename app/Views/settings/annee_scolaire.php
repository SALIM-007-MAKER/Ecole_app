<?php
$errors        = $errors        ?? [];
$anneeScolaire = $anneeScolaire ?? '';
$canEdit       = $canEdit       ?? false;
$old           = \Core\Session::getFlash('old', []);
$val           = fn(string $key, string $default) => htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="calendar-range" class="w-5 h-5 text-violet-600"></i>
            Année scolaire
        </h2>
        <p class="text-sm text-slate-500">Année scolaire active de l'établissement</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/annee-scolaire" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="annee_scolaire_active">Année scolaire active</label>
                <input type="text" id="annee_scolaire_active" name="annee_scolaire_active"
                       value="<?= $val('annee_scolaire_active', $anneeScolaire) ?>"
                       placeholder="2025-2026" pattern="\d{4}-\d{4}" class="form-input">
                <p class="text-xs text-slate-400 mt-1">
                    Format : YYYY-YYYY. Référence unique utilisée par tous les modules ayant besoin
                    de l'année scolaire courante (bulletins, exports, tableaux de bord, inscriptions).
                </p>
            </div>
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
