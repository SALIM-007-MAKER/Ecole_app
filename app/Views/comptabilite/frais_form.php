<?php
$frais    = $frais    ?? null;
$periodes = $periodes ?? [];
$csrfToken= \Core\Session::getCsrfToken();
?>

<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <i data-lucide="pencil" class="w-6 h-6 text-violet-600"></i>Modifier le type de frais
    </h2>
    <a href="<?= BASE_URL ?>/comptabilite/frais" class="btn btn-outline">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
    </a>
</div>

<div class="flex justify-center">
<div class="w-full max-w-lg">
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="p-6">
        <form method="POST" action="<?= BASE_URL ?>/comptabilite/frais/<?= $frais->id ?? '' ?>" class="space-y-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <div>
                <label class="form-label">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="nom" class="form-input"
                       value="<?= htmlspecialchars($frais->nom ?? '', ENT_QUOTES) ?>" required>
            </div>

            <div>
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input resize-none" rows="3"><?= htmlspecialchars($frais->description ?? '', ENT_QUOTES) ?></textarea>
            </div>

            <div>
                <label class="form-label">Montant par défaut (FCFA)</label>
                <input type="number" name="montant_defaut" class="form-input"
                       value="<?= number_format((float)($frais->montant_defaut ?? 0), 2, '.', '') ?>"
                       min="0" step="0.01">
            </div>

            <div>
                <label class="form-label">Périodicité</label>
                <select name="periodicite" class="form-select">
                    <?php foreach ($periodes as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($frais->periodicite ?? 'annuel') === $k ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="actif" value="1" id="actif"
                           class="sr-only peer" <?= ($frais->actif ?? 1) ? 'checked' : '' ?>>
                    <div class="relative w-10 h-5 bg-slate-200 rounded-full peer peer-checked:bg-violet-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                    <span class="text-sm font-medium text-slate-700">Frais actif</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="<?= BASE_URL ?>/comptabilite/frais" class="btn btn-outline">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="w-4 h-4"></i>Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</div>
