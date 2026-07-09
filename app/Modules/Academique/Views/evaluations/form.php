<?php
$evaluation  = $evaluation  ?? null;
$periodes    = $periodes    ?? [];
$types       = $types       ?? [];
$matieres    = $matieres    ?? [];
$classes     = $classes     ?? [];
$enseignants = $enseignants ?? [];
$errors      = $errors      ?? [];
$old         = $old         ?? [];
$isEdit      = $evaluation !== null;

$v    = fn(string $k, mixed $fb = '') =>
    htmlspecialchars((string)($old[$k] ?? ($evaluation?->$k ?? $fb)), ENT_QUOTES);
$vInt = fn(string $k, int $fb = 0): int =>
    (int)($old[$k] ?? ($evaluation?->$k ?? $fb));

// JSON des types pour auto-fill JS
$typesJson = json_encode(array_reduce(
    $types,
    fn($acc, $t) => $acc + [(string)$t->id => [
        'coefficient' => (float)$t->coefficient_defaut,
        'noteMax'     => (float)$t->note_max_defaut,
    ]],
    []
));
?>

<div class="max-w-2xl mx-auto">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/evaluations<?= $isEdit ? '/' . $evaluation->id : '' ?>"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            <?= $isEdit ? 'Modifier — ' . htmlspecialchars($evaluation->libelle, ENT_QUOTES) : 'Nouvelle évaluation' ?>
        </h2>
        <?php if ($isEdit): ?>
        <p class="text-xs text-slate-500 mt-0.5">
            Statut actuel :
            <span class="font-medium"><?= htmlspecialchars($evaluation->statut, ENT_QUOTES) ?></span>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors['global'])): ?>
<div class="alert alert-danger mb-4" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <div class="flex-1 text-sm">
        <?php foreach ($errors['global'] as $msg): ?>
        <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form method="POST"
      action="<?= BASE_URL ?>/v2/academique/evaluations<?= $isEdit ? '/' . $evaluation->id : '' ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">

        <!-- Intitulé -->
        <div>
            <label for="libelle" class="block text-sm font-medium text-slate-700 mb-1">
                Intitulé <span class="text-red-500">*</span>
            </label>
            <input type="text" id="libelle" name="libelle"
                   value="<?= $v('libelle') ?>"
                   maxlength="120"
                   placeholder="Ex: Contrôle n°1 — Fonctions affines"
                   class="form-input w-full <?= !empty($errors['libelle']) ? 'border-red-400' : '' ?>">
            <?php if (!empty($errors['libelle'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['libelle'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

        <!-- Période + Type côte à côte -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="periode_scolaire_id" class="block text-sm font-medium text-slate-700 mb-1">
                    Période scolaire <span class="text-red-500">*</span>
                </label>
                <select id="periode_scolaire_id" name="periode_scolaire_id"
                        class="form-input w-full <?= !empty($errors['periode_scolaire_id']) ? 'border-red-400' : '' ?>">
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($periodes as $p): ?>
                    <option value="<?= $p->id ?>"
                            data-statut="<?= htmlspecialchars($p->statut, ENT_QUOTES) ?>"
                            <?= $vInt('periode_scolaire_id') === (int)$p->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p->nom, ENT_QUOTES) ?>
                        <?php if ((int)$p->is_active): ?> ★<?php endif; ?>
                        (<?= htmlspecialchars($p->annee_scolaire, ENT_QUOTES) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['periode_scolaire_id'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['periode_scolaire_id'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="type_evaluation_id" class="block text-sm font-medium text-slate-700 mb-1">
                    Type d'évaluation <span class="text-red-500">*</span>
                </label>
                <select id="type_evaluation_id" name="type_evaluation_id"
                        onchange="autoFillFromType()"
                        class="form-input w-full <?= !empty($errors['type_evaluation_id']) ? 'border-red-400' : '' ?>">
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= $t->id ?>"
                            <?= $vInt('type_evaluation_id') === (int)$t->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['type_evaluation_id'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['type_evaluation_id'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Classe + Matière -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="classe_id" class="block text-sm font-medium text-slate-700 mb-1">
                    Classe <span class="text-red-500">*</span>
                </label>
                <select id="classe_id" name="classe_id"
                        class="form-input w-full <?= !empty($errors['classe_id']) ? 'border-red-400' : '' ?>">
                    <option value="">— Sélectionner —</option>
                    <?php
                    $currentNiveau = null;
                    foreach ($classes as $cl):
                        if ($cl->niveau !== $currentNiveau):
                            if ($currentNiveau !== null) echo '</optgroup>';
                            $currentNiveau = $cl->niveau;
                            echo '<optgroup label="' . htmlspecialchars($cl->niveau, ENT_QUOTES) . '">';
                        endif;
                    ?>
                    <option value="<?= $cl->id ?>" <?= $vInt('classe_id') === (int)$cl->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if ($currentNiveau !== null): ?></optgroup><?php endif; ?>
                </select>
                <?php if (!empty($errors['classe_id'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['classe_id'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="matiere_id" class="block text-sm font-medium text-slate-700 mb-1">
                    Matière <span class="text-red-500">*</span>
                </label>
                <select id="matiere_id" name="matiere_id"
                        class="form-input w-full <?= !empty($errors['matiere_id']) ? 'border-red-400' : '' ?>">
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($matieres as $m): ?>
                    <option value="<?= $m->id ?>" <?= $vInt('matiere_id') === (int)$m->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m->nom, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['matiere_id'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['matiere_id'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enseignant -->
        <div>
            <label for="enseignant_id" class="block text-sm font-medium text-slate-700 mb-1">
                Enseignant responsable
                <span class="text-slate-400 font-normal text-xs">(optionnel)</span>
            </label>
            <select id="enseignant_id" name="enseignant_id" class="form-input w-full">
                <option value="">— Aucun / non précisé —</option>
                <?php foreach ($enseignants as $e): ?>
                <option value="<?= $e->id ?>" <?= $vInt('enseignant_id') === (int)$e->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e->label, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date + Coefficient + Note max -->
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="date_evaluation" class="block text-sm font-medium text-slate-700 mb-1">
                    Date d'évaluation
                </label>
                <input type="date" id="date_evaluation" name="date_evaluation"
                       value="<?= $v('date_evaluation') ?>"
                       class="form-input w-full <?= !empty($errors['date_evaluation']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['date_evaluation'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['date_evaluation'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="coefficient" class="block text-sm font-medium text-slate-700 mb-1">
                    Coefficient <span class="text-red-500">*</span>
                </label>
                <input type="number" id="coefficient" name="coefficient"
                       value="<?= $v('coefficient', '1.00') ?>"
                       min="0.25" max="10" step="0.25"
                       class="form-input w-full <?= !empty($errors['coefficient']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['coefficient'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['coefficient'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-1">Auto-rempli selon le type</p>
            </div>
            <div>
                <label for="note_max" class="block text-sm font-medium text-slate-700 mb-1">
                    Note maximale <span class="text-red-500">*</span>
                </label>
                <input type="number" id="note_max" name="note_max"
                       value="<?= $v('note_max', '20.00') ?>"
                       min="5" max="100" step="0.5"
                       class="form-input w-full <?= !empty($errors['note_max']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['note_max'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['note_max'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-1">Barème (ex: 20, 40, 100)</p>
            </div>
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                Description <span class="text-slate-400 font-normal text-xs">(optionnel)</span>
            </label>
            <textarea id="description" name="description" rows="2"
                      maxlength="1000"
                      placeholder="Chapitres concernés, modalités particulières…"
                      class="form-input w-full resize-none <?= !empty($errors['description']) ? 'border-red-400' : '' ?>"
            ><?= $v('description') ?></textarea>
        </div>

    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" class="w-4 h-4"></i>
            <?= $isEdit ? 'Enregistrer' : 'Créer en brouillon' ?>
        </button>
        <a href="<?= BASE_URL ?>/v2/academique/evaluations<?= $isEdit ? '/' . $evaluation->id : '' ?>"
           class="btn btn-secondary">Annuler</a>
    </div>
</form>

</div>

<script>
const TYPES_DATA = <?= $typesJson ?>;
let userOverrideCoef    = false;
let userOverrideNoteMax = false;

document.getElementById('coefficient')?.addEventListener('input', () => { userOverrideCoef = true; });
document.getElementById('note_max')?.addEventListener('input',    () => { userOverrideNoteMax = true; });

function autoFillFromType() {
    const sel  = document.getElementById('type_evaluation_id');
    const data = TYPES_DATA[sel.value];
    if (!data) return;
    if (!userOverrideCoef) {
        document.getElementById('coefficient').value = data.coefficient.toFixed(2);
    }
    if (!userOverrideNoteMax) {
        document.getElementById('note_max').value = data.noteMax.toFixed(2);
    }
}
</script>
