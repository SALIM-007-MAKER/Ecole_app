<?php
/**
 * Finance V2 — Formulaire type de frais (création & édition)
 */
$isEdit = $isEdit ?? false;
$frais  = $frais ?? null;
$errors = $errors ?? [];
$old    = $old ?? [];

$getNiveauxSelectionnes = function() use ($frais, $old): array {
    if (!empty($old['niveaux_cibles'])) {
        return (array)$old['niveaux_cibles'];
    }
    if ($frais && !empty($frais->niveaux_cibles)) {
        return json_decode($frais->niveaux_cibles, true) ?? [];
    }
    return [];
};
$niveauxSelectionnes = $getNiveauxSelectionnes();

// Nomenclature officielle du système éducatif nigérien — App\Models\ClasseModel::NIVEAUX
$niveauxDisponibles = array_merge(...array_values(\App\Models\ClasseModel::NIVEAUX));
?>
<div class="max-w-2xl mx-auto space-y-6">

    <!-- En-tête -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/v2/finance/frais" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($title) ?></h1>
            <p class="text-sm text-slate-500 mt-1">
                <?= $isEdit ? 'Modifier les informations du type de frais.' : 'Créer un nouveau type de frais.' ?>
            </p>
        </div>
    </div>

    <!-- Erreur globale -->
    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-start gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= $isEdit
            ? BASE_URL . '/v2/finance/frais/' . $frais->id
            : BASE_URL . '/v2/finance/frais' ?>">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">

            <!-- Section identifiant -->
            <div class="p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Identification</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">
                            Code <span class="form-required">*</span>
                            <?php if ($isEdit): ?>
                            <span class="font-normal text-slate-400">(immuable)</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="code"
                               value="<?= htmlspecialchars($old['code'] ?? $frais->code ?? '') ?>"
                               <?= $isEdit ? 'readonly class="bg-slate-50 cursor-not-allowed"' : '' ?>
                               placeholder="ex: INSCRIPTION_2026"
                               class="form-input <?= isset($errors['code']) ? 'is-invalid' : '' ?> font-mono">
                        <?php if (isset($errors['code'])): ?>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['code']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label">
                            Année scolaire
                        </label>
                        <input type="text" name="annee_scolaire"
                               value="<?= htmlspecialchars($old['annee_scolaire'] ?? $frais->annee_scolaire ?? '') ?>"
                               placeholder="ex: 2026-2027"
                               pattern="\d{4}-\d{4}"
                               class="form-input <?= isset($errors['annee_scolaire']) ? 'is-invalid' : '' ?>">
                        <p class="text-xs text-slate-400 mt-1">Laisser vide si applicable à toutes les années.</p>
                        <?php if (isset($errors['annee_scolaire'])): ?>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['annee_scolaire']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        Nom <span class="form-required">*</span>
                    </label>
                    <input type="text" name="nom"
                           value="<?= htmlspecialchars($old['nom'] ?? $frais->nom ?? '') ?>"
                           placeholder="ex: Frais d'inscription"
                           class="form-input <?= isset($errors['nom']) ? 'is-invalid' : '' ?>">
                    <?php if (isset($errors['nom'])): ?>
                    <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['nom']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2"
                              placeholder="Description optionnelle..."
                              class="form-textarea"><?= htmlspecialchars($old['description'] ?? $frais->description ?? '') ?></textarea>
                </div>
            </div>

            <!-- Section catégorie & montant -->
            <div class="p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Montant & catégorie</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Catégorie</label>
                        <select name="categorie_id"
                                class="form-select">
                            <option value="">Aucune catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat->id ?>"
                                <?= ((int)($old['categorie_id'] ?? $frais->categorie_id ?? 0) === (int)$cat->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat->nom) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Périodicité <span class="form-required">*</span></label>
                        <select name="periodicite"
                                class="form-select">
                            <?php foreach ($periodicites as $val => $label): ?>
                            <option value="<?= $val ?>"
                                <?= (($old['periodicite'] ?? $frais->periodicite ?? 'annuel') === $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">
                            Montant par défaut <span class="form-required">*</span>
                        </label>
                        <input type="number" name="montant_defaut" step="0.01" min="0"
                               value="<?= htmlspecialchars($old['montant_defaut'] ?? $frais->montant_defaut ?? '0') ?>"
                               class="form-input <?= isset($errors['montant_defaut']) ? 'is-invalid' : '' ?>">
                        <?php if (isset($errors['montant_defaut'])): ?>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['montant_defaut']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label">Devise</label>
                        <select name="devise"
                                class="form-select">
                            <?php foreach ($devises as $code => $label): ?>
                            <option value="<?= $code ?>"
                                <?= (($old['devise'] ?? $frais->devise ?? 'XOF') === $code) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Date limite de paiement</label>
                    <input type="date" name="date_limite"
                           value="<?= htmlspecialchars($old['date_limite'] ?? $frais->date_limite ?? '') ?>"
                           class="form-input">
                    <p class="text-xs text-slate-400 mt-1">Date par défaut proposée lors de la facturation.</p>
                </div>
            </div>

            <!-- Section niveaux & options -->
            <div class="p-5 space-y-4">
                <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Niveaux & options</h2>

                <div>
                    <label class="form-label">
                        Niveaux cibles
                        <span class="font-normal text-slate-400">(laisser vide = tous niveaux)</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($niveauxDisponibles as $n): ?>
                        <label class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg cursor-pointer hover:bg-violet-50 hover:border-violet-300 transition-colors">
                            <input type="checkbox" name="niveaux_cibles[]" value="<?= htmlspecialchars($n) ?>"
                                   <?= in_array($n, $niveauxSelectionnes, true) ? 'checked' : '' ?>
                                   class="rounded text-violet-600">
                            <span class="text-sm text-slate-700"><?= htmlspecialchars($n) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="est_obligatoire" value="0">
                        <input type="checkbox" name="est_obligatoire" value="1"
                               <?= (bool)($old['est_obligatoire'] ?? $frais->est_obligatoire ?? true) ? 'checked' : '' ?>
                               class="rounded text-amber-500 w-4 h-4">
                        <div>
                            <div class="text-sm font-medium text-slate-700">Frais obligatoire</div>
                            <div class="text-xs text-slate-400">Tous les élèves du niveau concerné y sont soumis.</div>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="peut_avoir_remise" value="0">
                        <input type="checkbox" name="peut_avoir_remise" value="1"
                               <?= (bool)($old['peut_avoir_remise'] ?? $frais->peut_avoir_remise ?? true) ? 'checked' : '' ?>
                               class="rounded text-violet-600 w-4 h-4">
                        <div>
                            <div class="text-sm font-medium text-slate-700">Peut bénéficier d'une remise</div>
                            <div class="text-xs text-slate-400">Des réductions ou exonérations peuvent être accordées.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="p-5 flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>/v2/finance/frais<?= $isEdit ? '/' . $frais->id : '' ?>"
                   class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">
                    Annuler
                </a>
                <button type="submit"
                        class="px-6 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition-colors">
                    <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le frais' ?>
                </button>
            </div>
        </div>
    </form>
</div>
