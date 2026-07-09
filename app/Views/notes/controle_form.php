<?php
$errors   = $errors ?? [];
$old      = $old    ?? [];
$edit     = $edit   ?? false;
$controle = $controle ?? null;
$types    = $types  ?? [];

function cfv(array $old, string $key, $obj): string {
    $v = $old[$key] ?? ($obj?->{$key} ?? '');
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
$action = $edit
    ? BASE_URL . '/notes/controles/' . $controle->id
    : BASE_URL . '/notes/controles/store';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl <?= $edit ? 'bg-amber-100' : 'bg-violet-100' ?> flex items-center justify-center shrink-0">
            <i data-lucide="<?= $edit ? 'pencil' : 'plus-circle' ?>" class="w-5 h-5 <?= $edit ? 'text-amber-600' : 'text-violet-600' ?>"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-900">
                <?= $edit ? 'Modifier le contrôle' : 'Nouveau contrôle' ?>
            </h2>
            <p class="text-sm text-slate-500">
                <?= $edit ? htmlspecialchars($controle->libelle ?? '', ENT_QUOTES) : 'Renseignez les informations du contrôle' ?>
            </p>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/notes/controles" class="btn btn-secondary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
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

<form method="POST" action="<?= $action ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-4">

            <!-- Identification -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="info" class="w-4 h-4 text-violet-600"></i>
                    <span class="font-semibold text-slate-700">Identification</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label" for="libelle">
                                Libellé <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="libelle" name="libelle"
                                   class="form-input <?= isset($errors['libelle']) ? 'border-red-400 ring-1 ring-red-300' : '' ?>"
                                   value="<?= cfv($old, 'libelle', $controle) ?>"
                                   placeholder="Ex : Contrôle 1, Examen trimestriel…"
                                   required autofocus>
                            <?php if (isset($errors['libelle'])): ?>
                            <p class="form-error mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['libelle'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="type">Type</label>
                            <select id="type" name="type" class="form-input">
                                <?php foreach ($types as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= cfv($old, 'type', $controle) === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="date_controle">Date</label>
                            <input type="date" id="date_controle" name="date_controle"
                                   class="form-input"
                                   value="<?= cfv($old, 'date_controle', $controle) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="coefficient">
                                Coefficient <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="coefficient" name="coefficient" step="0.5" min="0.5"
                                   class="form-input <?= isset($errors['coefficient']) ? 'border-red-400 ring-1 ring-red-300' : '' ?>"
                                   value="<?= cfv($old, 'coefficient', $controle) ?: '1' ?>">
                            <?php if (isset($errors['coefficient'])): ?>
                            <p class="form-error mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['coefficient'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="note_max">
                                Note maximum <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="note_max" name="note_max" step="1" min="1"
                                   class="form-input <?= isset($errors['note_max']) ? 'border-red-400 ring-1 ring-red-300' : '' ?>"
                                   value="<?= cfv($old, 'note_max', $controle) ?: '20' ?>">
                            <?php if (isset($errors['note_max'])): ?>
                            <p class="form-error mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['note_max'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contexte pédagogique -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900">
                    <i data-lucide="building-2" class="w-4 h-4 text-sky-500"></i>
                    <span class="font-semibold text-slate-700">Contexte pédagogique</span>
                </div>
                <div class="p-5 p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label" for="classe_id">
                                Classe <span class="text-red-500">*</span>
                            </label>
                            <select id="classe_id" name="classe_id"
                                    class="form-input <?= isset($errors['classe_id']) ? 'border-red-400 ring-1 ring-red-300' : '' ?>">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($classes as $cl): ?>
                                <option value="<?= $cl->id ?>"
                                    <?= cfv($old, 'classe_id', $controle) == $cl->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cl->niveau . ' — ' . $cl->nom, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['classe_id'])): ?>
                            <p class="form-error mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['classe_id'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="matiere_id">
                                Matière <span class="text-red-500">*</span>
                            </label>
                            <select id="matiere_id" name="matiere_id"
                                    class="form-input <?= isset($errors['matiere_id']) ? 'border-red-400 ring-1 ring-red-300' : '' ?>">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($matieres as $m): ?>
                                <option value="<?= $m->id ?>"
                                    <?= cfv($old, 'matiere_id', $controle) == $m->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m->nom, ENT_QUOTES) ?> (coef. <?= $m->coefficient ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['matiere_id'])): ?>
                            <p class="form-error mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['matiere_id'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="periode_id">
                                Période <span class="text-red-500">*</span>
                            </label>
                            <select id="periode_id" name="periode_id"
                                    class="form-input <?= isset($errors['periode_id']) ? 'border-red-400 ring-1 ring-red-300' : '' ?>">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($periodes as $p): ?>
                                <option value="<?= $p->id ?>"
                                    <?= cfv($old, 'periode_id', $controle) == $p->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p->nom . ' — ' . $p->annee_scolaire, ENT_QUOTES) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['periode_id'])): ?>
                            <p class="form-error mt-1 text-xs text-red-500"><?= htmlspecialchars($errors['periode_id'][0], ENT_QUOTES) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Aide système de notation -->
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm" style="background:#f5f3ff;border-color:#e9d5ff">
                <div class="p-5 p-5">
                    <h3 class="font-bold text-violet-800 flex items-center gap-2 mb-3">
                        <i data-lucide="lightbulb" class="w-4 h-4 text-amber-500"></i>Système de notation
                    </h3>
                    <p class="text-sm text-violet-700 mb-3">
                        La <strong>moyenne par matière</strong> est calculée automatiquement
                        à partir de la moyenne pondérée de tous les contrôles.
                    </p>
                    <p class="text-sm text-violet-700 mb-4">
                        La <strong>moyenne générale</strong> est la moyenne pondérée des
                        moyennes de matières par leur coefficient.
                    </p>
                    <hr class="border-violet-200 mb-4">
                    <h4 class="font-semibold text-violet-800 text-sm mb-3 flex items-center gap-1.5">
                        <i data-lucide="award" class="w-4 h-4"></i>Mentions
                    </h4>
                    <div class="space-y-2">
                    <?php foreach (\App\Models\NoteModel::MENTIONS as $m): ?>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-violet-100 text-violet-700 text-xs"><?= $m['label'] ?></span>
                        <span class="text-xs text-violet-600 font-semibold">≥ <?= $m['seuil'] ?>/20</span>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Champs requis -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm" style="background:#f0fdf4;border-color:#bbf7d0">
                <div class="p-5 p-4">
                    <p class="text-xs text-emerald-700 flex items-start gap-2">
                        <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                        Les champs marqués <span class="text-red-500 font-bold mx-1">*</span> sont obligatoires.
                    </p>
                </div>
            </div>
        </div>

    </div>

    <div class="flex items-center gap-3 mt-5 pt-4 border-t border-slate-100">
        <button type="submit" class="btn btn-<?= $edit ? 'warning' : 'primary' ?>">
            <i data-lucide="save" class="w-4 h-4"></i>
            <?= $edit ? 'Enregistrer les modifications' : 'Créer et saisir les notes' ?>
        </button>
        <a href="<?= BASE_URL ?>/notes/controles" class="btn btn-secondary">Annuler</a>
    </div>
</form>
