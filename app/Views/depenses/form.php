<?php
$depense    = $depense    ?? null;
$categories = $categories ?? [];
$modes      = $modes      ?? [];
$old        = $old        ?? [];
$csrfToken  = \Core\Session::getCsrfToken();
$isEdit     = $depense !== null;
$action     = $isEdit
    ? BASE_URL . '/depenses/' . $depense->id
    : BASE_URL . '/depenses/store';
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="<?= $isEdit ? 'pencil' : 'plus-circle' ?>" class="w-6 h-6 text-red-500"></i>
        <?= $isEdit ? 'Modifier la dépense' : 'Nouvelle dépense' ?>
    </h2>
    <a href="<?= BASE_URL ?>/depenses" class="btn btn-outline">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<div class="flex justify-center">
<div class="w-full max-w-2xl">
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="p-6">
        <form method="POST" action="<?= $action ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <!-- Libellé -->
            <div>
                <label class="form-label">Libellé <span class="text-red-500">*</span></label>
                <input type="text" name="libelle" class="form-input" required
                       value="<?= htmlspecialchars($old['libelle'] ?? ($depense->libelle ?? ''), ENT_QUOTES) ?>"
                       placeholder="Description de la dépense">
            </div>

            <!-- Catégorie + Montant -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Catégorie <span class="text-red-500">*</span></label>
                    <select name="categorie_id" class="form-select" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat->id ?>"
                            <?= ($old['categorie_id'] ?? ($depense->categorie_id ?? '')) == $cat->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat->nom, ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant" class="form-input"
                           value="<?= number_format((float)($old['montant'] ?? ($depense->montant ?? 0)), 2, '.', '') ?>"
                           min="0.01" step="0.01" required>
                </div>
            </div>

            <!-- Date + Mode -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="date_depense" class="form-input" required
                           value="<?= $old['date_depense'] ?? ($depense->date_depense ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label class="form-label">Mode de paiement <span class="text-red-500">*</span></label>
                    <select name="mode_paiement" class="form-select" required>
                        <?php foreach ($modes as $k => $mode): ?>
                        <option value="<?= $k ?>"
                            <?= ($old['mode_paiement'] ?? ($depense->mode_paiement ?? 'especes')) === $k ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mode['label'], ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Bénéficiaire + N° pièce -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Bénéficiaire / Fournisseur</label>
                    <input type="text" name="beneficiaire" class="form-input"
                           value="<?= htmlspecialchars($old['beneficiaire'] ?? ($depense->beneficiaire ?? ''), ENT_QUOTES) ?>"
                           placeholder="Nom du fournisseur">
                </div>
                <div>
                    <label class="form-label">N° pièce / Bon</label>
                    <input type="text" name="num_piece" class="form-input"
                           value="<?= htmlspecialchars($old['num_piece'] ?? ($depense->num_piece ?? ''), ENT_QUOTES) ?>"
                           placeholder="Référence du bon">
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="form-label">Description / Observations</label>
                <textarea name="description" class="form-input resize-none" rows="3"><?= htmlspecialchars($old['description'] ?? ($depense->description ?? ''), ENT_QUOTES) ?></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="<?= BASE_URL ?>/depenses" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <?= $isEdit ? 'Mettre à jour' : 'Enregistrer' ?>
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>
