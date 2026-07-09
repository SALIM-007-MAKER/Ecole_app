<?php
$type   = $type   ?? null;
$icones = $icones ?? [];
$errors = $errors ?? [];
$old    = $old    ?? [];
$isEdit = $type !== null;

$v = fn(string $k, mixed $fallback = '') =>
    htmlspecialchars((string)($old[$k] ?? ($type ? ($type->$k ?? $fallback) : $fallback)), ENT_QUOTES);
$vBool = fn(string $k) =>
    !empty($old) ? !empty($old[$k]) : (isset($type) ? (bool)($type->$k ?? false) : false);
?>

<div class="max-w-2xl mx-auto">

<div class="flex items-center gap-3 mb-6">
    <a href="<?= BASE_URL ?>/v2/academique/types-evaluations<?= $isEdit ? '/' . $type->id : '' ?>"
       class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h2 class="text-xl font-bold text-slate-900">
            <?= $isEdit ? "Modifier — {$type->nom}" : "Nouveau type d'évaluation" ?>
        </h2>
        <?php if ($isEdit && (int)($type->est_systeme ?? 0)): ?>
        <p class="text-xs text-violet-600 mt-0.5 flex items-center gap-1">
            <i data-lucide="shield" class="w-3 h-3"></i>Type système V1 — modification réservée aux administrateurs
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
      action="<?= BASE_URL ?>/v2/academique/types-evaluations<?= $isEdit ? '/' . $type->id : '' ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">

        <!-- Code (create only) -->
        <div>
            <label for="code" class="block text-sm font-medium text-slate-700 mb-1">
                Code identifiant <?= !$isEdit ? '<span class="text-red-500">*</span>' : '' ?>
            </label>
            <?php if ($isEdit): ?>
            <div class="flex items-center gap-2">
                <code class="text-sm font-mono bg-slate-100 text-slate-700 px-3 py-2 rounded-lg border border-slate-200">
                    <?= htmlspecialchars($type->code, ENT_QUOTES) ?>
                </code>
                <span class="text-xs text-slate-400 flex items-center gap-1">
                    <i data-lucide="lock" class="w-3 h-3"></i>Immuable après création
                </span>
            </div>
            <input type="hidden" name="code" value="<?= htmlspecialchars($type->code, ENT_QUOTES) ?>">
            <?php else: ?>
            <input type="text" id="code" name="code"
                   value="<?= $v('code') ?>"
                   placeholder="ex: devoir, examen_final, tp_chimie"
                   pattern="[a-z0-9_\-]{2,30}"
                   class="form-input w-full font-mono <?= !empty($errors['code']) ? 'border-red-400' : '' ?>">
            <?php if (!empty($errors['code'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['code'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <p class="text-xs text-slate-400 mt-1">
                2-30 caractères. Minuscules, chiffres, tirets et underscores uniquement. Immuable après création.
            </p>
            <?php endif; ?>
        </div>

        <!-- Nom -->
        <div>
            <label for="nom" class="block text-sm font-medium text-slate-700 mb-1">
                Nom <span class="text-red-500">*</span>
            </label>
            <input type="text" id="nom" name="nom"
                   value="<?= $v('nom') ?>"
                   maxlength="80"
                   placeholder="Ex: Devoir surveillé, Examen semestriel…"
                   class="form-input w-full <?= !empty($errors['nom']) ? 'border-red-400' : '' ?>">
            <?php if (!empty($errors['nom'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['nom'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">
                Description
            </label>
            <textarea id="description" name="description" rows="2"
                      maxlength="500"
                      placeholder="Description optionnelle de ce type d'évaluation…"
                      class="form-input w-full resize-none <?= !empty($errors['description']) ? 'border-red-400' : '' ?>"
            ><?= $v('description') ?></textarea>
            <?php if (!empty($errors['description'])): ?>
            <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['description'][0], ENT_QUOTES) ?></p>
            <?php endif; ?>
        </div>

        <!-- Coefficient et Note max -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="coefficient_defaut" class="block text-sm font-medium text-slate-700 mb-1">
                    Coefficient par défaut <span class="text-red-500">*</span>
                </label>
                <input type="number" id="coefficient_defaut" name="coefficient_defaut"
                       value="<?= $v('coefficient_defaut', '1.00') ?>"
                       min="0.25" max="10" step="0.25"
                       class="form-input w-full <?= !empty($errors['coefficient_defaut']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['coefficient_defaut'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['coefficient_defaut'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-1">Entre 0,25 et 10,00</p>
            </div>
            <div>
                <label for="note_max_defaut" class="block text-sm font-medium text-slate-700 mb-1">
                    Note maximale par défaut <span class="text-red-500">*</span>
                </label>
                <input type="number" id="note_max_defaut" name="note_max_defaut"
                       value="<?= $v('note_max_defaut', '20.00') ?>"
                       min="5" max="100" step="0.5"
                       class="form-input w-full <?= !empty($errors['note_max_defaut']) ? 'border-red-400' : '' ?>">
                <?php if (!empty($errors['note_max_defaut'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['note_max_defaut'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <p class="text-xs text-slate-400 mt-1">Entre 5 et 100</p>
            </div>
        </div>

        <!-- Éliminatoire -->
        <div class="rounded-lg border border-slate-200 p-4 space-y-3">
            <div class="flex items-center gap-2">
                <input type="checkbox" id="est_eliminatoire" name="est_eliminatoire"
                       value="1"
                       <?= $vBool('est_eliminatoire') ? 'checked' : '' ?>
                       onchange="toggleSeuil(this.checked)"
                       class="w-4 h-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                <label for="est_eliminatoire" class="text-sm font-medium text-slate-700 cursor-pointer">
                    Type éliminatoire
                </label>
            </div>
            <p class="text-xs text-slate-400 ml-6">
                Si activé, une note en dessous du seuil entraîne un statut d'élimination automatique.
            </p>
            <div id="seuilBlock" class="ml-6 <?= $vBool('est_eliminatoire') ? '' : 'hidden' ?>">
                <label for="seuil_eliminatoire" class="block text-sm font-medium text-slate-700 mb-1">
                    Seuil éliminatoire <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="number" id="seuil_eliminatoire" name="seuil_eliminatoire"
                           value="<?= $v('seuil_eliminatoire', '') ?>"
                           min="0" max="100" step="0.5"
                           class="form-input w-28 <?= !empty($errors['seuil_eliminatoire']) ? 'border-red-400' : '' ?>">
                    <span class="text-sm text-slate-500">/ <span id="noteMaxPreview">
                        <?= $v('note_max_defaut', '20') ?>
                    </span></span>
                </div>
                <?php if (!empty($errors['seuil_eliminatoire'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['seuil_eliminatoire'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Couleur et Icône -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="couleur" class="block text-sm font-medium text-slate-700 mb-1">
                    Couleur (UI)
                </label>
                <div class="flex items-center gap-2">
                    <input type="color" id="couleurPicker"
                           value="<?= $v('couleur', '#6366f1') ?>"
                           oninput="document.getElementById('couleur').value=this.value; updateColorPreview()"
                           class="w-10 h-10 rounded cursor-pointer border border-slate-200">
                    <input type="text" id="couleur" name="couleur"
                           value="<?= $v('couleur', '') ?>"
                           placeholder="#6366f1"
                           maxlength="7"
                           oninput="syncColorPicker()"
                           class="form-input flex-1 font-mono text-sm <?= !empty($errors['couleur']) ? 'border-red-400' : '' ?>">
                </div>
                <?php if (!empty($errors['couleur'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['couleur'][0], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="icone" class="block text-sm font-medium text-slate-700 mb-1">
                    Icône Lucide
                </label>
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                        <i id="iconePreview"
                           data-lucide="<?= $v('icone', 'file-text') ?>"
                           class="w-5 h-5 text-slate-500"></i>
                    </div>
                    <input type="text" id="icone" name="icone"
                           value="<?= $v('icone', '') ?>"
                           placeholder="file-text, pencil…"
                           maxlength="50"
                           oninput="updateIconPreview()"
                           list="iconeSuggestions"
                           class="form-input flex-1 <?= !empty($errors['icone']) ? 'border-red-400' : '' ?>">
                </div>
                <datalist id="iconeSuggestions">
                    <?php foreach ($icones as $ico): ?>
                    <option value="<?= htmlspecialchars($ico, ENT_QUOTES) ?>">
                    <?php endforeach; ?>
                </datalist>
                <?php if (!empty($errors['icone'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= htmlspecialchars($errors['icone'][0], ENT_QUOTES) ?></p>
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
            <p class="text-xs text-slate-400 mt-1">0-99. 0 = affiché en premier.</p>
        </div>

    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="<?= $isEdit ? 'save' : 'plus' ?>" class="w-4 h-4"></i>
            <?= $isEdit ? 'Enregistrer les modifications' : "Créer le type d'évaluation" ?>
        </button>
        <a href="<?= BASE_URL ?>/v2/academique/types-evaluations<?= $isEdit ? '/' . $type->id : '' ?>"
           class="btn btn-secondary">Annuler</a>
    </div>
</form>

</div>

<script>
function toggleSeuil(show) {
    document.getElementById('seuilBlock').classList.toggle('hidden', !show);
    if (!show) document.getElementById('seuil_eliminatoire').value = '';
}

document.getElementById('note_max_defaut')?.addEventListener('input', function() {
    document.getElementById('noteMaxPreview').textContent = this.value || '20';
});

function syncColorPicker() {
    const val = document.getElementById('couleur').value;
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        document.getElementById('couleurPicker').value = val;
    }
}

function updateColorPreview() {}

function updateIconPreview() {
    const icon = document.getElementById('icone').value.trim();
    const el = document.getElementById('iconePreview');
    if (icon) {
        el.setAttribute('data-lucide', icon);
        lucide.createIcons();
    }
}
</script>
