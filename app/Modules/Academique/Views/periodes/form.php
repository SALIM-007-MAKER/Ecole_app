<?php
$periode = $periode ?? null;
$types   = $types   ?? [];
$errors  = $errors  ?? [];
$old     = $old     ?? [];
$isEdit  = $periode !== null;

$v = fn(string $k, mixed $fallback = '') =>
    htmlspecialchars($old[$k] ?? ($periode ? ($periode->$k ?? $fallback) : $fallback), ENT_QUOTES);
?>

<div class="max-w-2xl mx-auto">

<!-- En-tête formulaire -->
<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/periodes<?= $isEdit ? '/' . $periode->id : '' ?>"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            <?= $isEdit ? 'Modifier la période' : 'Nouvelle période scolaire' ?>
        </h2>
        <?php if ($isEdit): ?>
        <p class="text-sm text-slate-500"><?= htmlspecialchars($periode->nom, ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Erreur globale -->
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
      action="<?= BASE_URL ?>/v2/academique/periodes<?= $isEdit ? '/' . $periode->id : '' ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">

        <!-- Année scolaire -->
        <div>
            <label for="annee_scolaire" class="block text-sm font-medium text-slate-700 mb-1">
                Année scolaire <span class="text-red-500">*</span>
            </label>
            <input type="text" id="annee_scolaire" name="annee_scolaire"
                   value="<?= $v('annee_scolaire') ?>"
                   placeholder="2025-2026"
                   pattern="\d{4}-\d{4}"
                   class="form-input w-full <?= !empty($errors['annee_scolaire']) ? 'border-red-400' : '' ?>">
            <?php if (!empty($errors['annee_scolaire'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['annee_scolaire'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mt-1">Format : YYYY-YYYY (ex: 2025-2026)</p>
        </div>

        <!-- Type + Numéro sur la même ligne -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="type_periode" class="block text-sm font-medium text-slate-700 mb-1">
                    Type <span class="text-red-500">*</span>
                </label>
                <select id="type_periode" name="type_periode"
                        class="form-input w-full <?= !empty($errors['type_periode']) ? 'border-red-400' : '' ?>"
                        onchange="updateNumeroOptions()">
                    <?php foreach ($types as $val => $label): ?>
                    <option value="<?= $val ?>"
                            <?= $v('type_periode', 'trimestre') === $val ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['type_periode'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['type_periode'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="numero" class="block text-sm font-medium text-slate-700 mb-1">
                    Numéro <span class="text-red-500">*</span>
                </label>
                <select id="numero" name="numero"
                        class="form-input w-full <?= !empty($errors['numero']) ? 'border-red-400' : '' ?>"
                        onchange="autoNom()">
                    <option value="1" <?= $v('numero', '1') === '1' ? 'selected' : '' ?>>1</option>
                    <option value="2" <?= $v('numero', '1') === '2' ? 'selected' : '' ?>>2</option>
                    <option value="3" <?= $v('numero', '1') === '3' ? 'selected' : '' ?>>3</option>
                </select>
                <?php if (!empty($errors['numero'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['numero'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nom -->
        <div>
            <label for="nom" class="block text-sm font-medium text-slate-700 mb-1">
                Nom <span class="text-red-500">*</span>
            </label>
            <input type="text" id="nom" name="nom"
                   value="<?= $v('nom') ?>"
                   maxlength="80"
                   placeholder="Ex: Trimestre 1 — 2025-2026"
                   class="form-input w-full <?= !empty($errors['nom']) ? 'border-red-400' : '' ?>">
            <?php if (!empty($errors['nom'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="date_debut" class="block text-sm font-medium text-slate-700 mb-1">
                    Date de début
                </label>
                <input type="date" id="date_debut" name="date_debut"
                       value="<?= $v('date_debut') ?>"
                       class="form-input w-full <?= !empty($errors['date_debut']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['date_debut'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['date_debut'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="date_fin" class="block text-sm font-medium text-slate-700 mb-1">
                    Date de fin
                </label>
                <input type="date" id="date_fin" name="date_fin"
                       value="<?= $v('date_fin') ?>"
                       class="form-input w-full <?= !empty($errors['date_fin']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['date_fin'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['date_fin'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ordre -->
        <div class="w-32">
            <label for="ordre" class="block text-sm font-medium text-slate-700 mb-1">
                Ordre d'affichage
            </label>
            <input type="number" id="ordre" name="ordre"
                   value="<?= $v('ordre', '0') ?>"
                   min="0" max="99"
                   class="form-input w-full <?= !empty($errors['ordre']) ? 'border-red-400' : '' ?>">
            <?php if (!empty($errors['ordre'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['ordre'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Boutons -->
    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" class="w-4 h-4"></i>
            <?= $isEdit ? 'Enregistrer les modifications' : 'Créer la période' ?>
        </button>
        <a href="<?= BASE_URL ?>/v2/academique/periodes<?= $isEdit ? '/' . $periode->id : '' ?>"
           class="btn btn-secondary">
            Annuler
        </a>
    </div>
</form>

</div>

<script>
const typeLabels = <?= json_encode($types) ?>;
const currentAnnee = document.getElementById('annee_scolaire');
const currentType  = document.getElementById('type_periode');
const currentNum   = document.getElementById('numero');
const nomField     = document.getElementById('nom');

function updateNumeroOptions() {
    const type    = currentType.value;
    const maxNum  = type === 'semestre' ? 2 : 3;
    const current = parseInt(currentNum.value) || 1;
    currentNum.innerHTML = '';
    for (let i = 1; i <= maxNum; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i;
        if (i === Math.min(current, maxNum)) opt.selected = true;
        currentNum.appendChild(opt);
    }
    autoNom();
}

function autoNom() {
    if (nomField.dataset.userEdited === 'true') return;
    const annee = currentAnnee.value.trim();
    const type  = currentType.value;
    const num   = currentNum.value;
    const label = typeLabels[type] || 'Période';
    if (annee.match(/^\d{4}-\d{4}$/)) {
        nomField.value = `${label} ${num} — ${annee}`;
    }
}

nomField.addEventListener('input', () => {
    nomField.dataset.userEdited = 'true';
});
currentAnnee.addEventListener('input', autoNom);
currentNum.addEventListener('change', autoNom);

// Init
updateNumeroOptions();
<?php if (!$isEdit && empty($old['nom'])): ?>
autoNom();
<?php endif; ?>
</script>
