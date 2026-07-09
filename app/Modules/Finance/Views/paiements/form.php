<?php
/**
 * Finance V2 — Formulaire d'enregistrement d'un paiement
 */
$facture  = $facture  ?? null;
$modes    = $modes    ?? [];
$avoirsDisponibles = $avoirsDisponibles ?? [];
$echeancier = $echeancier ?? null;
$errors   = $errors   ?? [];
$old      = $old      ?? [];

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$old_v      = fn(string $k, $def = '') => htmlspecialchars((string)($old[$k] ?? $def));

if ($facture) {
    $montantRestant = (float)$facture->montant_total - (float)$facture->montant_paye;
    $montantRestant = max(0, $montantRestant);
}
?>
<div class="max-w-2xl mx-auto space-y-6">

    <!-- En-tête -->
    <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?? '' ?>"
           class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Enregistrer un paiement</h1>
            <?php if ($facture): ?>
            <p class="text-sm text-slate-500">Facture <?= htmlspecialchars($facture->numero) ?> — <?= htmlspecialchars($facture->eleve_nom) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Erreur globale -->
    <?php if (!empty($errors['global'])): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($errors['global']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Récap facture -->
    <?php if ($facture): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
        <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Récapitulatif facture</div>
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-xs text-slate-400">Total facture</div>
                <div class="font-bold text-slate-800"><?= $fmtMontant((float)$facture->montant_total) ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-400">Déjà payé</div>
                <div class="font-bold text-emerald-600"><?= $fmtMontant((float)$facture->montant_paye) ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-400">Restant dû</div>
                <div class="font-bold text-rose-600 text-base"><?= $fmtMontant($montantRestant) ?></div>
            </div>
        </div>
        <?php if ($echeancier && !empty($echeancier->echeances)): ?>
        <div class="border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500 mb-2">Échéancier (<?= count($echeancier->echeances) ?> échéances)</div>
            <div class="space-y-1">
                <?php foreach ($echeancier->echeances as $ech): ?>
                <div class="flex items-center justify-between text-xs px-2 py-1 rounded <?= $ech->statut === 'payee' ? 'bg-emerald-50 text-emerald-700' : ($ech->statut === 'en_retard' ? 'bg-rose-50 text-rose-700' : 'bg-slate-50 text-slate-600') ?>">
                    <span>Échéance #<?= $ech->numero_ordre ?> — <?= date('d/m/Y', strtotime($ech->date_echeance)) ?></span>
                    <span class="font-medium"><?= $fmtMontant((float)$ech->montant) ?></span>
                    <span class="capitalize"><?= $ech->statut ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <form method="POST" action="<?= BASE_URL ?>/v2/finance/paiements" class="bg-white rounded-xl border border-slate-200 p-6 space-y-5">
        <?php echo csrf_field() ?? '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">'; ?>
        <input type="hidden" name="facture_id" value="<?= $old_v('facture_id', $facture->id ?? '') ?>">

        <!-- Montant -->
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                Montant versé <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input type="number" name="montant" id="montant"
                       value="<?= $old_v('montant', $montantRestant ?? '') ?>"
                       min="0.01" step="0.01" required
                       class="w-full pl-3 pr-16 py-2.5 border <?= isset($errors['montant']) ? 'border-red-400 bg-red-50' : 'border-slate-300' ?> rounded-lg text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">XOF</span>
            </div>
            <?php if (isset($errors['montant'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($errors['montant']) ?></p>
            <?php elseif (isset($montantRestant)): ?>
            <p class="mt-1 text-xs text-slate-500">Montant restant : <?= $fmtMontant($montantRestant) ?></p>
            <?php endif; ?>
        </div>

        <!-- Mode de paiement -->
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
                Mode de paiement <span class="text-red-500">*</span>
            </label>
            <select name="mode_paiement" id="mode_paiement" required
                    class="w-full px-3 py-2.5 border <?= isset($errors['mode_paiement']) ? 'border-red-400 bg-red-50' : 'border-slate-300' ?> rounded-lg text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400"
                    onchange="toggleModeFields(this.value)">
                <option value="">— Sélectionner —</option>
                <?php foreach ($modes as $m): ?>
                <option value="<?= htmlspecialchars($m->code) ?>" <?= $old_v('mode_paiement') === $m->code ? 'selected' : '' ?>>
                    <?= ($m->icone ? $m->icone . ' ' : '') . htmlspecialchars($m->nom) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['mode_paiement'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($errors['mode_paiement']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Référence externe (CHQ / VIR) -->
        <div id="field_reference" class="hidden">
            <label class="block text-sm font-medium text-slate-700 mb-1">
                Référence / N° chèque-virement <span class="text-red-500" id="ref_required_star">*</span>
            </label>
            <input type="text" name="reference_externe" value="<?= $old_v('reference_externe') ?>"
                   class="w-full px-3 py-2.5 border <?= isset($errors['reference_externe']) ? 'border-red-400 bg-red-50' : 'border-slate-300' ?> rounded-lg text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400"
                   placeholder="Ex : CHQ-00123">
            <?php if (isset($errors['reference_externe'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($errors['reference_externe']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Avoir (mode AVOIR) -->
        <?php if ($avoirsDisponibles): ?>
        <div id="field_avoir" class="hidden">
            <label class="block text-sm font-medium text-slate-700 mb-1">
                Avoir à utiliser <span class="text-red-500">*</span>
            </label>
            <select name="avoir_id" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-300">
                <option value="">— Sélectionner un avoir —</option>
                <?php foreach ($avoirsDisponibles as $av): ?>
                <option value="<?= $av->id ?>" <?= $old_v('avoir_id') == $av->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($av->numero) ?> — <?= $fmtMontant((float)$av->montant) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['avoir_id'])): ?>
            <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($errors['avoir_id']) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Échéance ciblée -->
        <?php if ($echeancier && !empty($echeancier->echeances)): ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Imputer sur une échéance (optionnel)</label>
            <select name="echeance_id" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-300">
                <option value="">— Paiement global —</option>
                <?php foreach ($echeancier->echeances as $ech): if ($ech->statut === 'payee') continue; ?>
                <option value="<?= $ech->id ?>" <?= $old_v('echeance_id') == $ech->id ? 'selected' : '' ?>>
                    Échéance #<?= $ech->numero_ordre ?> — <?= date('d/m/Y', strtotime($ech->date_echeance)) ?> — <?= $fmtMontant((float)$ech->montant) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Date -->
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Date du paiement</label>
            <input type="date" name="date_paiement" value="<?= $old_v('date_paiement', date('Y-m-d')) ?>"
                   class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400">
        </div>

        <!-- Note -->
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Note interne (optionnel)</label>
            <textarea name="note" rows="2"
                      class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-300 focus:border-violet-400"
                      placeholder="Observations…"><?= $old_v('note') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="flex-1 px-6 py-2.5 text-sm font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                <i data-lucide="check" class="inline w-4 h-4 mr-1"></i> Enregistrer le paiement
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?? '' ?>"
               class="px-4 py-2.5 text-sm font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                Annuler
            </a>
        </div>
    </form>
</div>

<script>
function toggleModeFields(mode) {
    const refField   = document.getElementById('field_reference');
    const avoirField = document.getElementById('field_avoir');
    if (refField)   refField.classList.toggle('hidden', !['CHQ','VIR'].includes(mode));
    if (avoirField) avoirField.classList.toggle('hidden', mode !== 'AVOIR');
}
// init on load
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('mode_paiement');
    if (sel) toggleModeFields(sel.value);
});
</script>
