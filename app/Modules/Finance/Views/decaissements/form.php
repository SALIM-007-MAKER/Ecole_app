<?php
/**
 * Finance V2 — Nouveau décaissement
 * GET/POST /v2/finance/decaissements/create
 */
$errors       = $errors       ?? [];
$old          = $old          ?? [];
$categories   = $categories   ?? [];
$fournisseurs = $fournisseurs ?? [];
$val = fn(string $k, string $default = '') => htmlspecialchars($old[$k] ?? $default);
?>
<div class="space-y-6 max-w-3xl">

    <!-- En-tête -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/v2/finance/decaissements" class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Nouveau décaissement</h1>
            <p class="text-sm text-slate-500 mt-1">Le décaissement sera soumis pour validation dès sa création.</p>
        </div>
    </div>

    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">

        <div>
            <label class="form-label">Libellé <span class="form-required">*</span></label>
            <input type="text" name="libelle" required value="<?= $val('libelle') ?>"
                   placeholder="Ex: Achat fournitures scolaires trimestre 1"
                   class="form-input">
            <?php if (!empty($errors['libelle'])): ?><p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['libelle']) ?></p><?php endif; ?>
        </div>

        <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="2"
                      class="form-textarea"><?= $val('description') ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Montant <span class="form-required">*</span></label>
                <div class="relative">
                    <input type="number" name="montant" min="0.01" step="0.01" required value="<?= $val('montant') ?>"
                           class="form-input">
                    <span class="absolute right-3 top-2 text-xs text-slate-400">XOF</span>
                </div>
                <?php if (!empty($errors['montant'])): ?><p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['montant']) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="form-label">Catégorie</label>
                <select name="categorie_id" class="form-select">
                    <option value="">— Aucune —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat->id ?>" <?= ($old['categorie_id'] ?? '') == $cat->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat->nom) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Date de dépense <span class="form-required">*</span></label>
                <input type="date" name="date_depense" required value="<?= $val('date_depense', date('Y-m-d')) ?>"
                       class="form-input">
                <?php if (!empty($errors['date_depense'])): ?><p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['date_depense']) ?></p><?php endif; ?>
            </div>
            <div>
                <label class="form-label">Date d'échéance</label>
                <input type="date" name="date_echeance" value="<?= $val('date_echeance') ?>"
                       class="form-input">
                <?php if (!empty($errors['date_echeance'])): ?><p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['date_echeance']) ?></p><?php endif; ?>
            </div>
        </div>

        <div>
            <label class="form-label">Fournisseur</label>
            <select name="fournisseur_id" class="form-select">
                <option value="">— Aucun —</option>
                <?php foreach ($fournisseurs as $f): ?>
                <option value="<?= $f->id ?>" <?= ($old['fournisseur_id'] ?? '') == $f->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f->nom) ?> (<?= htmlspecialchars($f->code) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-400 mt-1">
                Fournisseur manquant ?
                <a href="<?= BASE_URL ?>/v2/finance/fournisseurs" class="text-violet-600 hover:underline" target="_blank">En créer un</a>
            </p>
        </div>

        <div>
            <label class="form-label">Référence externe</label>
            <input type="text" name="reference_externe" value="<?= $val('reference_externe') ?>"
                   placeholder="N° de facture fournisseur, bon de commande..."
                   class="form-input">
        </div>

        <div>
            <label class="form-label">Note</label>
            <textarea name="note" rows="2"
                      class="form-textarea"><?= $val('note') ?></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <a href="<?= BASE_URL ?>/v2/finance/decaissements" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="send" class="w-4 h-4"></i> Soumettre le décaissement
            </button>
        </div>
    </form>
</div>
