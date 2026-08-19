<?php
$annonce   = $annonce   ?? null;
$audiences = $audiences ?? [];
$classes   = $classes   ?? [];
$users     = $users     ?? [];
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$isEdit    = $annonce !== null;

$csrfToken = \Core\Session::getCsrfToken();
$action    = $isEdit
    ? BASE_URL . '/annonces/' . (int)$annonce->id
    : BASE_URL . '/annonces/store';

$val = fn(string $k, string $fallback = '') =>
    htmlspecialchars($old[$k] ?? ($annonce?->$k ?? $fallback), ENT_QUOTES);

$currentAudience = $old['audience'] ?? ($annonce?->audience ?? 'tous');
$currentClasseId = (int)($old['classe_id'] ?? ($annonce?->classe_id ?? 0));
$currentDestIds  = $old['destinataires_ids']
    ?? (!empty($annonce?->destinataires_ids) ? (json_decode($annonce->destinataires_ids, true) ?: []) : []);
$currentDestIds  = array_map('intval', (array)$currentDestIds);

$roleLabels = [
    'admin' => 'Admin', 'directeur' => 'Directeur', 'secretaire' => 'Secrétaire',
    'comptable' => 'Comptable', 'enseignant' => 'Enseignant', 'parent' => 'Parent', 'eleve' => 'Élève',
];
?>

<!-- Header -->
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="megaphone" class="w-5 h-5 text-violet-600"></i>
            <?= $isEdit ? 'Modifier l\'annonce' : 'Nouvelle annonce' ?>
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">
            <?= $isEdit ? 'Modifiez le contenu et les options de l\'annonce' : 'Rédigez et publiez une annonce à votre audience' ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/annonces" class="btn btn-outline">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<!-- Erreurs globales -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5">
    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
    <ul class="list-disc list-inside space-y-0.5 text-sm">
        <?php foreach ($errors as $fieldErrors): foreach ((array)$fieldErrors as $msg): ?>
        <li><?= htmlspecialchars($msg, ENT_QUOTES) ?></li>
        <?php endforeach; endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="<?= $action ?>" id="annonceForm">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Colonne principale -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Titre -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-3 rounded-t-xl">
                    <i data-lucide="type" class="w-4 h-4 text-violet-600"></i>
                    <span class="text-sm font-semibold text-slate-700">Titre de l'annonce</span>
                </div>
                <div class="p-5">
                    <label class="form-label" for="titre">Titre <span class="text-red-500">*</span></label>
                    <input type="text" id="titre" name="titre" value="<?= $val('titre') ?>"
                           class="form-input text-base font-medium <?= isset($errors['titre']) ? 'border-red-400' : '' ?>"
                           placeholder="Saisissez un titre accrocheur…" required autofocus
                           oninput="updatePreview()">
                    <?php if (!empty($errors['titre'])): ?>
                    <p class="text-xs text-red-500 mt-1 flex items-center gap-1">
                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                        <?= htmlspecialchars(implode(', ', (array)$errors['titre']), ENT_QUOTES) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contenu -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-5 py-3 rounded-t-xl">
                    <span class="flex items-center gap-2">
                        <i data-lucide="file-text" class="w-4 h-4 text-violet-600"></i>
                        <span class="text-sm font-semibold text-slate-700">Contenu</span>
                    </span>
                    <span class="text-xs text-slate-400" id="charCount">0 caractère(s)</span>
                </div>
                <div class="p-5">
                    <label class="form-label" for="contenu">Contenu <span class="text-red-500">*</span></label>
                    <textarea id="contenu" name="contenu" rows="10"
                              class="form-input text-sm leading-relaxed resize-y <?= isset($errors['contenu']) ? 'border-red-400' : '' ?>"
                              placeholder="Rédigez votre annonce ici…"
                              required
                              oninput="updateCharCount(); updatePreview()"><?= $val('contenu') ?></textarea>
                    <?php if (!empty($errors['contenu'])): ?>
                    <p class="text-xs text-red-500 mt-1 flex items-center gap-1">
                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                        <?= htmlspecialchars(implode(', ', (array)$errors['contenu']), ENT_QUOTES) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Aperçu -->
            <div id="previewCard" style="display:none"
                 class="rounded-xl border border-dashed border-slate-300 bg-slate-50 shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 px-5 py-3 rounded-t-xl">
                    <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                    <span class="text-sm font-semibold text-slate-500">Aperçu</span>
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-slate-900 mb-2" id="previewTitle">—</h3>
                    <p class="text-sm text-slate-600 whitespace-pre-line" id="previewContent">—</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <a href="<?= BASE_URL ?>/annonces" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="<?= $isEdit ? 'save' : 'send' ?>" class="w-4 h-4"></i>
                    <?= $isEdit ? 'Enregistrer les modifications' : 'Publier l\'annonce' ?>
                </button>
            </div>
        </div>

        <!-- Sidebar options -->
        <div class="space-y-4">

            <!-- Options de publication -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-3 rounded-t-xl">
                    <i data-lucide="settings" class="w-4 h-4 text-violet-600"></i>
                    <span class="text-sm font-semibold text-slate-700">Options de publication</span>
                </div>
                <div class="p-4 space-y-4">

                    <div>
                        <label class="form-label" for="audience">
                            Audience <span class="text-red-500">*</span>
                        </label>
                        <select id="audience" name="audience" class="form-select" required onchange="toggleAudienceFields()">
                            <?php foreach ($audiences as $k => $aud): ?>
                            <option value="<?= htmlspecialchars($k, ENT_QUOTES) ?>"
                                    <?= $currentAudience === $k ? 'selected' : '' ?>>
                                <?= htmlspecialchars(is_array($aud) ? ($aud['label'] ?? $k) : $aud, ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Qui peut voir cette annonce</p>
                    </div>

                    <!-- Ciblage : une classe -->
                    <div id="audienceClasseField" style="display:none">
                        <label class="form-label" for="classe_id">
                            Classe <span class="text-red-500">*</span>
                        </label>
                        <select id="classe_id" name="classe_id" class="form-select <?= isset($errors['classe_id']) ? 'border-red-400' : '' ?>">
                            <option value="">— Choisir une classe —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= (int)$c->id ?>" <?= $currentClasseId === (int)$c->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c->label, ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['classe_id'])): ?>
                        <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars(implode(', ', (array)$errors['classe_id']), ENT_QUOTES) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-400 mt-1">Élèves de cette classe et leurs parents</p>
                    </div>

                    <!-- Ciblage : utilisateurs précis -->
                    <div id="audienceUtilisateursField" style="display:none">
                        <label class="form-label">
                            Destinataires <span class="text-red-500">*</span>
                        </label>
                        <div class="rounded-lg border border-slate-200 max-h-64 overflow-y-auto divide-y divide-slate-100 <?= isset($errors['destinataires_ids']) ? 'border-red-400' : '' ?>">
                            <?php foreach ($users as $u): ?>
                            <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="destinataires_ids[]" value="<?= (int)$u->id ?>"
                                       <?= in_array((int)$u->id, $currentDestIds, true) ? 'checked' : '' ?>
                                       class="accent-violet-600">
                                <span class="flex-1 truncate"><?= htmlspecialchars(trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')), ENT_QUOTES) ?></span>
                                <span class="text-xs text-slate-400"><?= htmlspecialchars($roleLabels[$u->role] ?? $u->role, ENT_QUOTES) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($errors['destinataires_ids'])): ?>
                        <p class="text-xs text-red-500 mt-1"><?= htmlspecialchars(implode(', ', (array)$errors['destinataires_ids']), ENT_QUOTES) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-400 mt-1">Sélectionnez un ou plusieurs destinataires</p>
                    </div>

                </div>
            </div>

            <!-- Historique si édition -->
            <?php if ($isEdit && !empty($annonce->created_at)): ?>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-3 rounded-t-xl">
                    <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                    <span class="text-sm font-semibold text-slate-500">Historique</span>
                </div>
                <div class="p-4 space-y-2 text-xs text-slate-400">
                    <div class="flex items-center justify-between">
                        <span>Créée le</span>
                        <span class="font-medium text-slate-600"><?= date('d/m/Y à H:i', strtotime($annonce->created_at)) ?></span>
                    </div>
                    <?php if (!empty($annonce->updated_at) && $annonce->updated_at !== $annonce->created_at): ?>
                    <div class="flex items-center justify-between">
                        <span>Modifiée le</span>
                        <span class="font-medium text-slate-600"><?= date('d/m/Y à H:i', strtotime($annonce->updated_at)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Conseils -->
            <div class="rounded-xl border border-violet-100 bg-violet-50 shadow-sm">
                <div class="p-4">
                    <p class="text-xs font-semibold text-violet-700 flex items-center gap-1.5 mb-2">
                        <i data-lucide="lightbulb" class="w-3.5 h-3.5"></i>Conseils de rédaction
                    </p>
                    <ul class="text-xs text-violet-600 space-y-1.5">
                        <li class="flex items-start gap-1.5">
                            <i data-lucide="check" class="w-3 h-3 mt-0.5 flex-shrink-0"></i>Un titre clair et concis attire l'attention.
                        </li>
                        <li class="flex items-start gap-1.5">
                            <i data-lucide="check" class="w-3 h-3 mt-0.5 flex-shrink-0"></i>Précisez les dates, lieux et contacts si nécessaire.
                        </li>
                        <li class="flex items-start gap-1.5">
                            <i data-lucide="check" class="w-3 h-3 mt-0.5 flex-shrink-0"></i>Réservez "Important" aux communications urgentes.
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
function toggleAudienceFields() {
    var audience = document.getElementById('audience').value;
    document.getElementById('audienceClasseField').style.display       = (audience === 'classe')       ? '' : 'none';
    document.getElementById('audienceUtilisateursField').style.display = (audience === 'utilisateurs') ? '' : 'none';
}
function updateCharCount() {
    var txt = document.getElementById('contenu').value;
    document.getElementById('charCount').textContent = txt.length + ' caractère(s)';
}
function updatePreview() {
    var titre   = document.getElementById('titre').value.trim();
    var contenu = document.getElementById('contenu').value.trim();
    var card    = document.getElementById('previewCard');
    if (titre || contenu) {
        card.style.display = '';
        document.getElementById('previewTitle').textContent   = titre   || '—';
        document.getElementById('previewContent').textContent = contenu || '—';
    } else {
        card.style.display = 'none';
    }
}
updateCharCount();
updatePreview();
toggleAudienceFields();
</script>
