<?php
$errors            = $errors            ?? [];
$paginationParPage = $paginationParPage ?? 15;
$canEdit           = $canEdit           ?? false;
$old               = \Core\Session::getFlash('old', []);
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="sliders-horizontal" class="w-5 h-5 text-violet-600"></i>
            Paramètres avancés
        </h2>
        <p class="text-sm text-slate-500">Préférences d'affichage générales</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/avance" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="pagination_par_page">Éléments par page (listes)</label>
                <select id="pagination_par_page" name="pagination_par_page" class="form-input">
                    <?php foreach ([10, 15, 20, 30, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= (int)($old['pagination_par_page'] ?? $paginationParPage) === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
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
