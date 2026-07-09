<?php
$errors      = $errors      ?? [];
$old         = $old         ?? [];
$professeurs = $professeurs ?? [];

function editM(array $old, string $key, $obj): string {
    $v = $old[$key] ?? ($obj->{$key} ?? '');
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil" class="w-5 h-5 text-amber-500"></i>
            Modifier — <?= htmlspecialchars($matiere->nom, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            Coef. <?= number_format($matiere->coefficient, 1) ?> — <?= $matiere->volume_horaire ?>h/sem
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/matieres/<?= $matiere->id ?>" class="btn btn-secondary">
            <i data-lucide="eye" class="w-4 h-4"></i>Voir le détail
        </a>
        <a href="<?= BASE_URL ?>/matieres" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msgs): foreach ((array)$msgs as $m): ?>
        <li class="text-sm"><?= htmlspecialchars($m, ENT_QUOTES) ?></li>
        <?php endforeach; endforeach; ?>
    </ul>
    <button class="ml-auto inline-flex rounded-md p-1 opacity-60 transition hover:bg-black/5 hover:opacity-100" onclick="this.closest('[role=alert]').remove()">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/matieres/<?= $matiere->id ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Formulaire principal -->
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="book" class="w-4 h-4 text-amber-500"></i>
                <span class="font-semibold text-slate-700">Informations de la matière</span>
            </div>
            <div class="p-5 p-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                    <div class="sm:col-span-3">
                        <label class="form-label" for="nom">
                            Nom de la matière <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="nom" name="nom"
                               class="form-input <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                               value="<?= editM($old, 'nom', $matiere) ?>"
                               maxlength="100" required>
                        <?php if (isset($errors['nom'])): ?>
                        <p class="form-error"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="coefficient">
                            Coefficient <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" id="coefficient" name="coefficient"
                                   class="form-input pr-8 <?= isset($errors['coefficient']) ? 'border-red-400' : '' ?>"
                                   value="<?= editM($old, 'coefficient', $matiere) ?>"
                                   min="0.5" max="20" step="0.5" required>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">×</span>
                        </div>
                        <?php if (isset($errors['coefficient'])): ?>
                        <p class="form-error"><?= htmlspecialchars($errors['coefficient'][0], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="volume_horaire">
                            Volume horaire <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" id="volume_horaire" name="volume_horaire"
                                   class="form-input pr-14 <?= isset($errors['volume_horaire']) ? 'border-red-400' : '' ?>"
                                   value="<?= editM($old, 'volume_horaire', $matiere) ?>"
                                   min="1" max="30" required>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">h/sem</span>
                        </div>
                        <?php if (isset($errors['volume_horaire'])): ?>
                        <p class="form-error"><?= htmlspecialchars($errors['volume_horaire'][0], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="responsable_id">Enseignant responsable</label>
                        <select id="responsable_id" name="responsable_id" class="form-input">
                            <option value="">— Aucun —</option>
                            <?php foreach ($professeurs as $p): ?>
                            <option value="<?= $p->id ?>"
                                <?= (editM($old, 'responsable_id', $matiere) == $p->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p->label, ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sm:col-span-3">
                        <label class="form-label" for="description">
                            Description <span class="text-slate-400 font-normal">(optionnel)</span>
                        </label>
                        <textarea id="description" name="description" class="w-full min-h-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20" rows="3"><?= editM($old, 'description', $matiere) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar : État actuel -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                <i data-lucide="bar-chart-2" class="w-4 h-4 text-violet-500"></i>
                <span class="font-semibold text-slate-700">État actuel</span>
            </div>
            <div class="p-5 p-5 text-center">
                <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="book" class="w-8 h-8 text-amber-500"></i>
                </div>
                <h3 class="font-bold text-slate-900 mb-4">
                    <?= htmlspecialchars($matiere->nom, ENT_QUOTES) ?>
                </h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-violet-50 rounded-xl p-3">
                        <p class="text-xl font-bold text-violet-600"><?= number_format($matiere->coefficient, 1) ?></p>
                        <p class="text-xs text-violet-500 mt-0.5">Coefficient</p>
                    </div>
                    <div class="bg-sky-50 rounded-xl p-3">
                        <p class="text-xl font-bold text-sky-600"><?= $matiere->volume_horaire ?>h</p>
                        <p class="text-xs text-sky-500 mt-0.5">Volume</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="flex items-center gap-3 mt-5">
        <button type="submit" class="btn btn-warning">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les modifications
        </button>
        <a href="<?= BASE_URL ?>/matieres/<?= $matiere->id ?>" class="btn btn-secondary">Annuler</a>
    </div>
</form>
