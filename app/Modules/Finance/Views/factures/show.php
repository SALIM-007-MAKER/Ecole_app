<?php
/**
 * Finance V2 — Détail facture
 */
$facture    = $facture    ?? null;
$lignes     = $lignes     ?? [];
$remises    = $remises    ?? [];
$penalites  = $penalites  ?? [];
$echeancier = $echeancier ?? null;
$avoirs     = $avoirs     ?? [];
$statuts    = $statuts    ?? [];

$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$badge = match ($facture->statut) {
    'brouillon'           => 'bg-slate-100 text-slate-600',
    'emise'               => 'bg-blue-100 text-blue-700',
    'partiellement_payee' => 'bg-amber-100 text-amber-700',
    'payee'               => 'bg-emerald-100 text-emerald-700',
    'en_retard'           => 'bg-red-100 text-red-700',
    'annulee'             => 'bg-rose-100 text-rose-600',
    'archive'             => 'bg-slate-100 text-slate-400',
    default               => 'bg-slate-100 text-slate-600',
};
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>/v2/finance/factures" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-800 font-mono"><?= htmlspecialchars($facture->numero) ?></h1>
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full <?= $badge ?>">
                        <?= $statuts[$facture->statut] ?? $facture->statut ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5">
                    <?= htmlspecialchars($facture->eleve_nom) ?>
                    <?php if ($facture->classe_nom): ?>
                    · <span class="text-slate-400"><?= htmlspecialchars($facture->classe_nom) ?></span>
                    <?php endif; ?>
                    · <?= htmlspecialchars($facture->annee_scolaire) ?>
                </p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
            <?php if ($canPrint): ?>
            <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/print" target="_blank"
               class="px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                <i data-lucide="printer" class="inline w-4 h-4 mr-1"></i> Imprimer
            </a>
            <?php endif; ?>

            <?php if ($canEdit && $facture->statut === 'brouillon'): ?>
            <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/edit"
               class="px-3 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                <i data-lucide="pencil" class="inline w-4 h-4 mr-1"></i> Modifier
            </a>
            <?php endif; ?>

            <?php if ($canEmettre && $facture->statut === 'brouillon'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/emettre" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
                <button type="submit"
                        onclick="return confirm('Émettre cette facture ? Elle ne pourra plus être modifiée.')"
                        class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    <i data-lucide="send" class="inline w-4 h-4 mr-1"></i> Émettre
                </button>
            </form>
            <?php endif; ?>

            <?php if ($canAnnuler && in_array($facture->statut, ['emise','partiellement_payee','payee','en_retard'], true)): ?>
            <button onclick="document.getElementById('modal-annuler').classList.remove('hidden')"
                    class="px-3 py-2 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100">
                <i data-lucide="x-circle" class="inline w-4 h-4 mr-1"></i> Annuler
            </button>
            <?php endif; ?>

            <?php if ($canArchiver && in_array($facture->statut, ['payee','annulee'], true)): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/archiver" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
                <button type="submit"
                        onclick="return confirm('Archiver cette facture ?')"
                        class="px-3 py-2 text-sm text-slate-500 bg-slate-100 rounded-lg hover:bg-slate-200">
                    <i data-lucide="archive" class="inline w-4 h-4 mr-1"></i> Archiver
                </button>
            </form>
            <?php endif; ?>

            <?php if ($canSupprimer && $facture->statut === 'brouillon'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/delete" class="inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
                <button type="submit"
                        onclick="return confirm('Supprimer définitivement cette facture brouillon ?')"
                        class="px-3 py-2 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100">
                    <i data-lucide="trash-2" class="inline w-4 h-4 mr-1"></i> Supprimer
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Colonne principale -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Lignes de facture -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800">Lignes de facturation</h2>
                    <?php if ($canEdit && $facture->statut === 'brouillon'): ?>
                    <button onclick="document.getElementById('modal-add-ligne').classList.remove('hidden')"
                            class="text-sm text-violet-600 hover:text-violet-800">
                        <i data-lucide="plus" class="inline w-4 h-4"></i> Ajouter
                    </button>
                    <?php endif; ?>
                </div>
                <table class="min-w-full">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            <th class="px-5 py-3 text-left">Libellé</th>
                            <th class="px-3 py-3 text-right">Qté</th>
                            <th class="px-3 py-3 text-right">P.U.</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <?php if ($canEdit && $facture->statut === 'brouillon'): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($lignes as $l): ?>
                        <tr>
                            <td class="px-5 py-3 text-sm text-slate-800">
                                <?= htmlspecialchars($l->libelle) ?>
                                <?php if ($l->frais_code): ?>
                                <span class="ml-1 text-xs text-slate-400 font-mono">(<?= htmlspecialchars($l->frais_code) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-3 text-sm text-right text-slate-600"><?= $l->quantite ?></td>
                            <td class="px-3 py-3 text-sm text-right text-slate-600"><?= $fmt((float)$l->montant_unitaire) ?></td>
                            <td class="px-3 py-3 text-sm text-right font-semibold text-slate-800"><?= $fmt((float)$l->montant_total) ?></td>
                            <?php if ($canEdit && $facture->statut === 'brouillon'): ?>
                            <td class="px-3 py-3 text-right">
                                <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/ligne/<?= $l->id ?>/delete" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
                                    <button type="submit" onclick="return confirm('Supprimer cette ligne ?')"
                                            class="p-1 text-slate-300 hover:text-red-500">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Récapitulatif montants -->
                <div class="px-5 py-4 border-t border-slate-100 space-y-1">
                    <div class="flex justify-between text-sm text-slate-600">
                        <span>Sous-total HT</span>
                        <span><?= $fmt((float)$facture->montant_ht) ?></span>
                    </div>
                    <?php if ((float)$facture->montant_remise > 0): ?>
                    <div class="flex justify-between text-sm text-emerald-700">
                        <span>Remises accordées</span>
                        <span>- <?= $fmt((float)$facture->montant_remise) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ((float)$facture->montant_penalite > 0): ?>
                    <div class="flex justify-between text-sm text-red-700">
                        <span>Pénalités de retard</span>
                        <span>+ <?= $fmt((float)$facture->montant_penalite) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-slate-200">
                        <span>Total à payer</span>
                        <span><?= $fmt((float)$facture->montant_total) ?></span>
                    </div>
                    <?php if ((float)$facture->montant_paye > 0): ?>
                    <div class="flex justify-between text-sm text-emerald-600">
                        <span>Déjà encaissé</span>
                        <span><?= $fmt((float)$facture->montant_paye) ?></span>
                    </div>
                    <div class="flex justify-between text-sm font-semibold <?= (float)$facture->montant_total > (float)$facture->montant_paye ? 'text-red-700' : 'text-emerald-700' ?>">
                        <span>Reste à payer</span>
                        <span><?= $fmt(max(0, (float)$facture->montant_total - (float)$facture->montant_paye)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Remises -->
            <?php if (!empty($remises) || ($canRemise && in_array($facture->statut, ['brouillon','emise'], true))): ?>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800">Remises</h2>
                    <?php if ($canRemise && in_array($facture->statut, ['brouillon','emise'], true)): ?>
                    <button onclick="document.getElementById('modal-remise').classList.remove('hidden')"
                            class="text-sm text-violet-600 hover:text-violet-800">
                        <i data-lucide="percent" class="inline w-4 h-4"></i> Appliquer une remise
                    </button>
                    <?php endif; ?>
                </div>
                <?php if (empty($remises)): ?>
                <p class="px-5 py-4 text-sm text-slate-400">Aucune remise accordée.</p>
                <?php else: ?>
                <table class="min-w-full">
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($remises as $r): ?>
                        <tr>
                            <td class="px-5 py-3 text-sm text-slate-800"><?= htmlspecialchars($r->libelle) ?></td>
                            <td class="px-3 py-3 text-sm text-slate-500">
                                <?= $r->type_remise === 'pourcentage' ? $r->valeur . ' %' : ($r->type_remise === 'exoneration' ? '100%' : $fmt((float)$r->valeur)) ?>
                            </td>
                            <td class="px-3 py-3 text-sm font-semibold text-emerald-700 text-right">- <?= $fmt((float)$r->montant_calcule) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Échéancier -->
            <?php if ($echeancier): ?>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Échéancier (<?= $echeancier->nb_echeances ?> versements)</h2>
                </div>
                <table class="min-w-full">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            <th class="px-5 py-3 text-left">#</th>
                            <th class="px-3 py-3 text-left">Date</th>
                            <th class="px-3 py-3 text-right">Montant dû</th>
                            <th class="px-3 py-3 text-right">Payé</th>
                            <th class="px-3 py-3 text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($echeancier->echeances as $ech): ?>
                        <tr>
                            <td class="px-5 py-3 text-sm text-slate-500"><?= $ech->numero_ordre ?></td>
                            <td class="px-3 py-3 text-sm text-slate-700"><?= $ech->date_echeance ?></td>
                            <td class="px-3 py-3 text-sm text-right font-medium text-slate-800"><?= $fmt((float)$ech->montant_du) ?></td>
                            <td class="px-3 py-3 text-sm text-right text-emerald-600"><?= $fmt((float)$ech->montant_paye) ?></td>
                            <td class="px-3 py-3 text-center">
                                <span class="text-xs px-2 py-0.5 rounded-full <?= $ech->statut === 'paye' ? 'bg-emerald-100 text-emerald-700' : ($ech->statut === 'en_retard' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') ?>">
                                    <?= $ech->statut ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($canEdit && in_array($facture->statut, ['brouillon','emise'], true)): ?>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-500">Aucun échéancier. Créer un plan de paiement ?</p>
                    <button onclick="document.getElementById('modal-echeancier').classList.remove('hidden')"
                            class="text-sm text-violet-600 hover:text-violet-800">
                        Créer un échéancier
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Avoirs -->
            <?php if (!empty($avoirs)): ?>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-800">Avoir(s) émis</h2>
                </div>
                <table class="min-w-full">
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($avoirs as $av): ?>
                        <tr>
                            <td class="px-5 py-3 font-mono text-sm text-violet-700"><?= htmlspecialchars($av->numero) ?></td>
                            <td class="px-3 py-3 text-sm text-slate-600"><?= $fmt((float)$av->montant) ?></td>
                            <td class="px-3 py-3 text-xs text-slate-500"><?= $av->statut ?></td>
                            <td class="px-3 py-3 text-xs text-slate-400"><?= htmlspecialchars($av->motif) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonne latérale -->
        <div class="space-y-4">
            <!-- Infos -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3 text-sm">
                <h2 class="font-semibold text-slate-800 mb-3">Informations</h2>
                <div class="flex justify-between">
                    <span class="text-slate-500">Date d'émission</span>
                    <span class="font-medium text-slate-800"><?= $facture->date_emission ?></span>
                </div>
                <?php if ($facture->date_echeance): ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Échéance</span>
                    <span class="font-medium <?= $facture->date_echeance < date('Y-m-d') && $facture->statut === 'emise' ? 'text-red-600' : 'text-slate-800' ?>">
                        <?= $facture->date_echeance ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($facture->emetteur_prenom): ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Émis par</span>
                    <span class="text-slate-800"><?= htmlspecialchars($facture->emetteur_prenom . ' ' . $facture->emetteur_nom) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($facture->note): ?>
                <div class="pt-2 border-t border-slate-100">
                    <p class="text-xs text-slate-500 mb-1">Note</p>
                    <p class="text-slate-700"><?= htmlspecialchars($facture->note) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($facture->statut === 'annulee'): ?>
                <div class="pt-2 border-t border-red-100 bg-red-50 rounded-lg p-3 space-y-1">
                    <p class="text-xs font-semibold text-red-700">ANNULÉE le <?= $facture->date_annulation ?></p>
                    <?php if ($facture->annuleur_prenom): ?>
                    <p class="text-xs text-red-600">Par <?= htmlspecialchars($facture->annuleur_prenom . ' ' . $facture->annuleur_nom) ?></p>
                    <?php endif; ?>
                    <?php if ($facture->motif_annulation): ?>
                    <p class="text-xs text-red-600 italic"><?= htmlspecialchars($facture->motif_annulation) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal annulation -->
<div id="modal-annuler" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Annuler la facture</h3>
        <p class="text-sm text-slate-500 mb-4">
            <?php if ((float)$facture->montant_paye > 0): ?>
            Un avoir de <strong><?= $fmt((float)$facture->montant_paye) ?></strong> sera automatiquement émis.
            <?php else: ?>
            Aucun avoir ne sera émis (aucun paiement reçu).
            <?php endif; ?>
        </p>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/annuler">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Motif d'annulation <span class="text-red-500">*</span></label>
                <textarea name="motif" rows="3" required
                          class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                          placeholder="Raison de l'annulation..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-annuler').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600">Fermer</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                    Confirmer l'annulation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ajouter ligne -->
<div id="modal-add-ligne" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Ajouter une ligne</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/ligne" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Libellé *</label>
                <input type="text" name="libelle" required
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantité</label>
                    <input type="number" name="quantite" value="1" min="0.01" step="0.01"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Prix unitaire *</label>
                    <input type="number" name="montant_unitaire" min="0" step="0.01" required
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-add-ligne').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600">Annuler</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal remise -->
<div id="modal-remise" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Appliquer une remise</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/remise" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Libellé *</label>
                <input type="text" name="libelle" required placeholder="Ex: Réduction fratrie"
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select name="type_remise"
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="montant_fixe">Montant fixe</option>
                        <option value="pourcentage">Pourcentage (%)</option>
                        <option value="exoneration">Exonération totale</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Valeur</label>
                    <input type="number" name="valeur" min="0" step="0.01"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Justificatif</label>
                <input type="text" name="justificatif" placeholder="Motif optionnel..."
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-remise').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600">Annuler</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    Appliquer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal échéancier -->
<div id="modal-echeancier" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Créer un échéancier</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/factures/<?= $facture->id ?>/echeancier" id="form-echeancier">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
            <div id="echeances-list" class="space-y-3 mb-4">
                <div class="echeance-row grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Date *</label>
                        <input type="date" name="echeances[0][date]" required
                               class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Montant *</label>
                        <input type="number" name="echeances[0][montant]" min="0" step="0.01" required
                               class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                    </div>
                </div>
            </div>
            <button type="button" onclick="ajouterEcheance()"
                    class="text-sm text-violet-600 hover:text-violet-800 mb-4">
                <i data-lucide="plus" class="inline w-4 h-4"></i> Ajouter une échéance
            </button>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-echeancier').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600">Annuler</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    Créer l'échéancier
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let echIdx = 1;
function ajouterEcheance() {
    const i   = echIdx++;
    const div = document.createElement('div');
    div.className = 'echeance-row grid grid-cols-2 gap-3';
    div.innerHTML = `
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Date *</label>
            <input type="date" name="echeances[${i}][date]" required
                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Montant *</label>
            <input type="number" name="echeances[${i}][montant]" min="0" step="0.01" required
                   class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
    `;
    document.getElementById('echeances-list').appendChild(div);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>
