<?php
$errors          = $errors          ?? [];
$baremeMax       = $baremeMax       ?? 20;
$notePassage     = $notePassage     ?? '10';
$seuilRattrapage = $seuilRattrapage ?? '8';
$canEdit         = $canEdit         ?? false;
$old             = \Core\Session::getFlash('old', []);
$val             = fn(string $key, mixed $default) => htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
?>

<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil-ruler" class="w-5 h-5 text-violet-600"></i>
            Système de notation
        </h2>
        <p class="text-sm text-slate-500">Barème, seuil de passage et seuil de rattrapage</p>
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

<form method="POST" action="<?= BASE_URL ?>/parametres/notation" novalidate class="space-y-5">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <fieldset <?= $canEdit ? '' : 'disabled' ?> class="space-y-5">

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="bareme_max">Barème (note maximale)</label>
                <input type="number" id="bareme_max" name="bareme_max" min="5" max="100"
                       value="<?= $val('bareme_max', $baremeMax) ?>" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Ex : 20 (système /20), 100 (système /100)</p>
            </div>
            <div>
                <label class="form-label" for="note_passage">Seuil de passage</label>
                <input type="text" id="note_passage" name="note_passage"
                       value="<?= $val('note_passage', $notePassage) ?>" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Note minimale considérée comme admise</p>
            </div>
            <div>
                <label class="form-label" for="seuil_rattrapage">Seuil de rattrapage</label>
                <input type="text" id="seuil_rattrapage" name="seuil_rattrapage"
                       value="<?= $val('seuil_rattrapage', $seuilRattrapage) ?>" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Note minimale donnant accès au rattrapage</p>
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
