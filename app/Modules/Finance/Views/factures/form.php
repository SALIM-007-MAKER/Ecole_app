<?php
/**
 * Finance V2 — Formulaire facture (création & édition brouillon)
 */
$isEdit  = $isEdit  ?? false;
$facture = $facture ?? null;
$lignes  = $lignes  ?? [];
$errors  = $errors  ?? [];
$old     = $old     ?? [];
?>
<div class="max-w-3xl mx-auto space-y-6">

    <!-- En-tête -->
    <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/v2/finance/factures<?= $isEdit ? '/' . $facture->id : '' ?>"
           class="text-slate-400 hover:text-slate-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($title) ?></h1>
            <?php if ($isEdit): ?>
            <p class="text-sm font-mono text-slate-400 mt-0.5"><?= htmlspecialchars($facture->numero) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-start gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= $isEdit
            ? BASE_URL . '/v2/finance/factures/' . $facture->id
            : BASE_URL . '/v2/finance/factures' ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">

        <div class="space-y-4">

            <!-- Informations générales -->
            <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
                <div class="p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Informations générales</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Élève <span class="text-red-500">*</span>
                                <?php if ($isEdit): ?><span class="font-normal text-slate-400">(immuable)</span><?php endif; ?>
                            </label>
                            <?php if ($isEdit): ?>
                            <input type="text" value="<?= htmlspecialchars($facture->eleve_nom) ?>"
                                   readonly class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">
                            <?php else: ?>
                            <select name="eleve_id" required
                                    class="w-full px-3 py-2 text-sm border <?= isset($errors['eleve_id']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option value="">-- Sélectionner un élève --</option>
                                <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e->id ?>" <?= ((int)($old['eleve_id'] ?? 0) === (int)$e->id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(trim($e->prenom . ' ' . $e->nom)) ?>
                                    <?php if (!empty($e->matricule)): ?>
                                    (<?= htmlspecialchars($e->matricule) ?>)
                                    <?php endif; ?>
                                    <?php if (!empty($e->classe_nom)): ?> — <?= htmlspecialchars($e->classe_nom) ?><?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['eleve_id'])): ?>
                            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['eleve_id']) ?></p>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                Année scolaire <span class="text-red-500">*</span>
                                <?php if ($isEdit): ?><span class="font-normal text-slate-400">(immuable)</span><?php endif; ?>
                            </label>
                            <?php if ($isEdit): ?>
                            <input type="text" value="<?= htmlspecialchars($facture->annee_scolaire) ?>"
                                   readonly class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed">
                            <?php else: ?>
                            <input type="text" name="annee_scolaire"
                                   value="<?= htmlspecialchars($old['annee_scolaire'] ?? '') ?>"
                                   placeholder="ex: 2026-2027" pattern="\d{4}-\d{4}" required
                                   class="w-full px-3 py-2 text-sm border <?= isset($errors['annee_scolaire']) ? 'border-red-400' : 'border-slate-200' ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <?php if (isset($errors['annee_scolaire'])): ?>
                            <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($errors['annee_scolaire']) ?></p>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date d'échéance</label>
                            <input type="date" name="date_echeance"
                                   value="<?= htmlspecialchars($old['date_echeance'] ?? $facture->date_echeance ?? '') ?>"
                                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Note interne</label>
                            <input type="text" name="note"
                                   value="<?= htmlspecialchars($old['note'] ?? $facture->note ?? '') ?>"
                                   placeholder="Remarque optionnelle..."
                                   class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
            <!-- Lignes de facture (création) -->
            <div class="bg-white rounded-xl border border-slate-200" id="lignes-container">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">
                        Lignes de facturation <span class="text-red-500">*</span>
                    </h2>
                    <button type="button" onclick="ajouterLigne()"
                            class="text-xs font-medium text-violet-600 hover:text-violet-800">
                        <i data-lucide="plus" class="inline w-3 h-3"></i> Ajouter une ligne
                    </button>
                </div>

                <div id="lignes-list" class="divide-y divide-slate-50">
                    <!-- Ligne initiale -->
                    <div class="ligne-row p-4 grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-5">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Libellé *</label>
                            <input type="text" name="lignes[0][libelle]" required
                                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
                                   placeholder="Ex: Frais d'inscription">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Qté</label>
                            <input type="number" name="lignes[0][quantite]" value="1" min="0.01" step="0.01"
                                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
                                   oninput="calcTotal(this)">
                        </div>
                        <div class="col-span-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Prix unitaire *</label>
                            <input type="number" name="lignes[0][montant_unitaire]" min="0" step="0.01" required
                                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
                                   oninput="calcTotal(this)">
                        </div>
                        <div class="col-span-1 text-right">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Total</label>
                            <span class="text-sm font-semibold text-slate-700 total-affichage">0</span>
                        </div>
                        <div class="col-span-1 flex justify-end">
                            <button type="button" onclick="supprimerLigne(this)"
                                    class="p-1 text-slate-300 hover:text-red-500 rounded">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-slate-100 flex justify-end">
                    <div class="text-sm font-semibold text-slate-800">
                        Total estimé : <span id="total-global">0</span> XOF
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>/v2/finance/factures<?= $isEdit ? '/' . $facture->id : '' ?>"
                   class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Annuler</a>
                <button type="submit"
                        class="px-6 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    <?= $isEdit ? 'Enregistrer' : 'Créer la facture (brouillon)' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php if (!$isEdit): ?>
<script>
let ligneIdx = 1;

function ajouterLigne() {
    const list = document.getElementById('lignes-list');
    const i    = ligneIdx++;
    const div  = document.createElement('div');
    div.className = 'ligne-row p-4 grid grid-cols-12 gap-2 items-end';
    div.innerHTML = `
        <div class="col-span-5">
            <label class="block text-xs font-medium text-slate-600 mb-1">Libellé *</label>
            <input type="text" name="lignes[${i}][libelle]" required
                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Qté</label>
            <input type="number" name="lignes[${i}][quantite]" value="1" min="0.01" step="0.01"
                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
                   oninput="calcTotal(this)">
        </div>
        <div class="col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Prix unitaire *</label>
            <input type="number" name="lignes[${i}][montant_unitaire]" min="0" step="0.01" required
                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
                   oninput="calcTotal(this)">
        </div>
        <div class="col-span-1 text-right">
            <label class="block text-xs font-medium text-slate-600 mb-1">Total</label>
            <span class="text-sm font-semibold text-slate-700 total-affichage">0</span>
        </div>
        <div class="col-span-1 flex justify-end">
            <button type="button" onclick="supprimerLigne(this)"
                    class="p-1 text-slate-300 hover:text-red-500 rounded">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        </div>
    `;
    list.appendChild(div);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    recalcTotal();
}

function supprimerLigne(btn) {
    const rows = document.querySelectorAll('.ligne-row');
    if (rows.length <= 1) { alert("Une facture doit avoir au moins une ligne."); return; }
    btn.closest('.ligne-row').remove();
    recalcTotal();
}

function calcTotal(input) { recalcTotal(); }

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.ligne-row').forEach(row => {
        const qte     = parseFloat(row.querySelector('[name*="[quantite]"]')?.value)     || 1;
        const prix    = parseFloat(row.querySelector('[name*="[montant_unitaire]"]')?.value) || 0;
        const ligneT  = Math.max(0, qte * prix);
        row.querySelector('.total-affichage').textContent = ligneT.toLocaleString('fr-FR');
        total += ligneT;
    });
    document.getElementById('total-global').textContent = total.toLocaleString('fr-FR');
}
</script>
<?php endif; ?>
