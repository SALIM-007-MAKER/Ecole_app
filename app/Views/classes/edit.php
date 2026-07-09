<?php
$errors  = $errors  ?? [];
$old     = $old     ?? [];
$niveaux = $niveaux ?? [];

function editC(array $old, string $key, $obj): string {
    $v = $old[$key] ?? ($obj->{$key} ?? '');
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="pencil" class="w-5 h-5 text-amber-500"></i>
            Modifier — <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">Année <?= htmlspecialchars($classe->annee_scolaire, ENT_QUOTES) ?></p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/classes/<?= $classe->id ?>" class="btn btn-secondary">
            <i data-lucide="eye" class="w-4 h-4"></i>Voir le détail
        </a>
        <a href="<?= BASE_URL ?>/classes" class="btn btn-secondary">
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

<form method="POST" action="<?= BASE_URL ?>/classes/<?= $classe->id ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Formulaire principal -->
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="building-2" class="w-4 h-4 text-amber-500"></i>
                    <span class="font-semibold text-slate-700">Informations de la classe</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <div class="sm:col-span-2">
                            <label class="form-label" for="niveau">
                                Niveau <span class="text-red-500">*</span>
                            </label>
                            <select id="niveau" name="niveau"
                                    class="form-input <?= isset($errors['niveau']) ? 'border-red-400' : '' ?>"
                                    required>
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($niveaux as $groupe => $vals): ?>
                                <optgroup label="<?= htmlspecialchars($groupe, ENT_QUOTES) ?>">
                                    <?php foreach ($vals as $v): ?>
                                    <option value="<?= $v ?>"
                                        <?= editC($old, 'niveau', $classe) === $v ? 'selected' : '' ?>>
                                        <?= $v ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['niveau'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['niveau'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label" for="nom">
                                Nom / Lettre <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="nom" name="nom"
                                   class="form-input uppercase font-bold tracking-widest <?= isset($errors['nom']) ? 'border-red-400' : '' ?>"
                                   value="<?= editC($old, 'nom', $classe) ?>"
                                   maxlength="10" required
                                   oninput="this.value=this.value.toUpperCase()">
                            <?php if (isset($errors['nom'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label" for="annee_scolaire">
                                Année scolaire <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="annee_scolaire" name="annee_scolaire"
                                   class="form-input <?= isset($errors['annee_scolaire']) ? 'border-red-400' : '' ?>"
                                   value="<?= editC($old, 'annee_scolaire', $classe) ?>"
                                   pattern="\d{4}-\d{4}" placeholder="2024-2025" required>
                            <?php if (isset($errors['annee_scolaire'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['annee_scolaire'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="form-label" for="max_eleves">Capacité maximale</label>
                            <div class="relative">
                                <input type="number" id="max_eleves" name="max_eleves"
                                       class="form-input pr-14 <?= isset($errors['max_eleves']) ? 'border-red-400' : '' ?>"
                                       value="<?= editC($old, 'max_eleves', $classe) ?>"
                                       min="1" max="100">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none">élèves</span>
                            </div>
                            <?php if (isset($errors['max_eleves'])): ?>
                            <p class="form-error"><?= htmlspecialchars($errors['max_eleves'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="form-label" for="description">
                                Description <span class="text-slate-400 font-normal">(optionnel)</span>
                            </label>
                            <textarea id="description" name="description" class="w-full min-h-24 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20" rows="2"><?= editC($old, 'description', $classe) ?></textarea>
                        </div>
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
                    <i data-lucide="building-2" class="w-8 h-8 text-amber-500"></i>
                </div>
                <h3 class="font-bold text-slate-900 text-base">
                    <?= htmlspecialchars($classe->niveau . ' ' . $classe->nom, ENT_QUOTES) ?>
                </h3>
                <p class="text-sm text-slate-400 mt-0.5">
                    <?= htmlspecialchars($classe->annee_scolaire, ENT_QUOTES) ?>
                </p>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <div class="bg-emerald-50 rounded-xl p-3">
                        <p class="text-xl font-bold text-emerald-600"><?= $classe->nb_eleves ?? 0 ?></p>
                        <p class="text-xs text-emerald-500 mt-0.5">Élèves</p>
                    </div>
                    <div class="bg-sky-50 rounded-xl p-3">
                        <p class="text-xl font-bold text-sky-600"><?= $classe->nb_enseignements ?? 0 ?></p>
                        <p class="text-xs text-sky-500 mt-0.5">Matières</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="flex items-center gap-3 mt-5">
        <button type="submit" class="btn btn-warning">
            <i data-lucide="save" class="w-4 h-4"></i>Enregistrer les modifications
        </button>
        <a href="<?= BASE_URL ?>/classes/<?= $classe->id ?>" class="btn btn-secondary">Annuler</a>
    </div>
</form>
