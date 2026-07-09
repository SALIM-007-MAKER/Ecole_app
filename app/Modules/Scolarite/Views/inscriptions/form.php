<?php
$inscription = $inscription ?? null;
$eleves      = $eleves      ?? [];
$classes     = $classes     ?? [];
$annees      = $annees      ?? [];
$errors      = $errors      ?? [];
$old         = $old         ?? [];
$isEdit      = $inscription !== null;

function inscVal(array $old, string $key, ?object $obj): string {
    $v = $old[$key] ?? ($obj->{$key} ?? '');
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$formAction = $isEdit
    ? BASE_URL . '/v2/scolarite/inscriptions/' . $inscription->id
    : BASE_URL . '/v2/scolarite/inscriptions';
?>

<!-- En-tête -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
            <?php if ($isEdit): ?>
            <i data-lucide="pencil" class="w-5 h-5 text-amber-500"></i>
            Modifier l'inscription
            <?php else: ?>
            <i data-lucide="plus-circle" class="w-5 h-5 text-violet-500"></i>
            Nouvelle inscription
            <?php endif; ?>
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
            <?= $isEdit ? 'Modification d\'une inscription en attente' : 'Inscrire un élève pour une année scolaire' ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($isEdit): ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>" class="btn btn-secondary">
            <i data-lucide="eye" class="w-4 h-4"></i>Voir
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Liste
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-5" role="alert">
    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
    <ul class="flex-1 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $msgs): foreach ((array)$msgs as $m): ?>
        <li class="text-sm"><?= htmlspecialchars($m, ENT_QUOTES) ?></li>
        <?php endforeach; endforeach; ?>
    </ul>
    <button onclick="this.closest('[role=alert]').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <i data-lucide="x" class="w-4 h-4"></i>
    </button>
</div>
<?php endif; ?>

<form method="POST" action="<?= $formAction ?>" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Formulaire -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Élève -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <i data-lucide="user" class="w-4 h-4 text-violet-500"></i>
                    <span class="font-semibold text-slate-700 text-sm">Élève</span>
                </div>
                <div class="p-5">
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="eleve_id" value="<?= $inscription->eleve_id ?>">
                    <p class="text-sm font-medium text-slate-800">
                        <?= htmlspecialchars(($inscription->eleve_nom ?? '') . ' ' . ($inscription->eleve_prenom ?? ''), ENT_QUOTES) ?>
                    </p>
                    <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($inscription->eleve_matricule ?? '', ENT_QUOTES) ?></p>
                    <?php else: ?>
                    <label class="form-label" for="eleve_id">
                        Élève <span class="text-red-500">*</span>
                    </label>
                    <select id="eleve_id" name="eleve_id"
                            class="form-input <?= isset($errors['eleve_id']) ? 'border-red-400' : '' ?>"
                            required>
                        <option value="">— Sélectionner un élève —</option>
                        <?php foreach ($eleves as $e): ?>
                        <option value="<?= $e->id ?>"
                            <?= inscVal($old, 'eleve_id', null) === (string)$e->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?>
                            <?php if (!empty($e->matricule)): ?>
                            — <?= htmlspecialchars($e->matricule, ENT_QUOTES) ?>
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['eleve_id'])): ?>
                    <p class="form-error"><?= htmlspecialchars($errors['eleve_id'][0], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Année scolaire et classe -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <i data-lucide="calendar" class="w-4 h-4 text-violet-500"></i>
                    <span class="font-semibold text-slate-700 text-sm">Affectation</span>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="annee_scolaire">
                            Année scolaire <span class="text-red-500">*</span>
                        </label>
                        <?php if (!empty($annees)): ?>
                        <select id="annee_scolaire" name="annee_scolaire"
                                class="form-input <?= isset($errors['annee_scolaire']) ? 'border-red-400' : '' ?>"
                                required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($annees as $a): ?>
                            <option value="<?= $a ?>" <?= inscVal($old, 'annee_scolaire', $inscription) === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" id="annee_scolaire" name="annee_scolaire"
                               class="form-input <?= isset($errors['annee_scolaire']) ? 'border-red-400' : '' ?>"
                               value="<?= inscVal($old, 'annee_scolaire', $inscription) ?>"
                               placeholder="2025-2026" pattern="\d{4}-\d{4}" required>
                        <?php endif; ?>
                        <?php if (isset($errors['annee_scolaire'])): ?>
                        <p class="form-error"><?= htmlspecialchars($errors['annee_scolaire'][0], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="classe_id">
                            Classe <span class="text-slate-400 font-normal">(optionnel)</span>
                        </label>
                        <select id="classe_id" name="classe_id" class="form-input">
                            <option value="">— À définir —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c->id ?>"
                                <?= inscVal($old, 'classe_id', $inscription) === (string)$c->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c->label, ENT_QUOTES) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-400 mt-1">Peut être défini ou changé après validation.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="form-label" for="notes">
                            Observations <span class="text-slate-400 font-normal">(internes)</span>
                        </label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="w-full min-h-16 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                                  placeholder="Remarques pour le secrétariat…"><?= inscVal($old, 'notes', $inscription) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar info -->
        <div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <i data-lucide="info" class="w-4 h-4 text-violet-500"></i>
                    <span class="font-semibold text-slate-700 text-sm">Règles métier</span>
                </div>
                <div class="p-5 space-y-3 text-sm text-slate-600">
                    <p class="flex items-start gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                        Une seule inscription <strong>active</strong> par élève par année scolaire.
                    </p>
                    <p class="flex items-start gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                        La capacité de la classe est vérifiée à la validation.
                    </p>
                    <p class="flex items-start gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                        La classe peut être laissée vide et assignée plus tard.
                    </p>
                    <p class="flex items-start gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
                        Statut initial : <strong>en attente</strong>. La validation est une étape séparée.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-5">
        <button type="submit" class="btn <?= $isEdit ? 'btn-warning' : 'btn-primary' ?>">
            <i data-lucide="save" class="w-4 h-4"></i>
            <?= $isEdit ? 'Enregistrer' : 'Créer l\'inscription' ?>
        </button>
        <?php if ($isEdit): ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions/<?= $inscription->id ?>" class="btn btn-secondary">Annuler</a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/v2/scolarite/inscriptions" class="btn btn-secondary">Annuler</a>
        <?php endif; ?>
    </div>
</form>
