<?php
/**
 * Finance V2 — Détail d'un paiement
 */
$paiement       = $paiement ?? null;
$remboursements = $remboursements ?? [];
$trop_percu     = $trop_percu ?? null;
$statuts        = $statuts ?? [];
$modesRemboursement = $modesRemboursement ?? [];

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';

$badgeStatut = function(string $s): string {
    return match ($s) {
        'initie'    => 'bg-slate-100 text-slate-600',
        'valide'    => 'bg-purple-100 text-purple-700',
        'complete'  => 'bg-emerald-100 text-emerald-700',
        'annule'    => 'bg-rose-100 text-rose-600',
        'rembourse' => 'bg-amber-100 text-amber-700',
        default     => 'bg-slate-100 text-slate-600',
    };
};
?>
<div class="max-w-3xl mx-auto space-y-6">

    <!-- En-tête -->
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/finance/paiements"
               class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($paiement->numero) ?></h1>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $badgeStatut($paiement->statut) ?>">
                        <?= $statuts[$paiement->statut] ?? $paiement->statut ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5">
                    <?= htmlspecialchars($paiement->eleve_nom) ?> ·
                    <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $paiement->facture_id ?>"
                       class="text-violet-600 hover:underline"><?= htmlspecialchars($paiement->facture_numero) ?></a>
                </p>
            </div>
        </div>
        <!-- Actions -->
        <div class="flex items-center gap-2">
            <?php if ($canPrintRecu && $paiement->recu_id): ?>
            <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/recu/print" target="_blank"
               class="px-3 py-2 text-sm font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100">
                <i data-lucide="printer" class="inline w-4 h-4 mr-1"></i> Imprimer reçu
            </a>
            <?php elseif ($canPrintRecu && $paiement->statut === 'complete'): ?>
            <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/recu"
               class="px-3 py-2 text-sm font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100">
                <i data-lucide="file-text" class="inline w-4 h-4 mr-1"></i> Générer reçu
            </a>
            <?php endif; ?>
            <?php if ($canValider && $paiement->statut === 'initie'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/valider" class="inline">
                <?php echo csrf_field() ?? '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">'; ?>
                <button class="px-3 py-2 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100">
                    <i data-lucide="check-circle" class="inline w-4 h-4 mr-1"></i> Valider
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

    <!-- Infos paiement -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Détails du paiement</div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Montant versé</dt>
                    <dd class="font-semibold text-slate-800"><?= $fmtMontant((float)$paiement->montant) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Montant appliqué</dt>
                    <dd class="font-bold text-emerald-700"><?= $fmtMontant((float)$paiement->montant_applique) ?></dd>
                </div>
                <?php if ($paiement->montant > $paiement->montant_applique): ?>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Trop-perçu</dt>
                    <dd class="font-semibold text-amber-600"><?= $fmtMontant((float)$paiement->montant - (float)$paiement->montant_applique) ?></dd>
                </div>
                <?php endif; ?>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Mode de paiement</dt>
                    <dd class="font-medium text-slate-700">
                        <?= ($paiement->mode_icone ? $paiement->mode_icone . ' ' : '') . htmlspecialchars($paiement->mode_nom ?? $paiement->mode_code ?? '—') ?>
                    </dd>
                </div>
                <?php if ($paiement->reference_externe): ?>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Référence</dt>
                    <dd class="font-mono text-xs text-slate-700"><?= htmlspecialchars($paiement->reference_externe) ?></dd>
                </div>
                <?php endif; ?>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Date paiement</dt>
                    <dd><?= $paiement->date_paiement ? date('d/m/Y', strtotime($paiement->date_paiement)) : '—' ?></dd>
                </div>
                <?php if ($paiement->note): ?>
                <div>
                    <dt class="text-slate-500 mb-1">Note</dt>
                    <dd class="text-slate-700 bg-slate-50 rounded p-2 text-xs"><?= nl2br(htmlspecialchars($paiement->note)) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Facture associée</div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Numéro facture</dt>
                    <dd>
                        <a href="<?= BASE_URL ?>/v2/finance/factures/<?= $paiement->facture_id ?>"
                           class="font-mono text-xs text-violet-700 hover:underline"><?= htmlspecialchars($paiement->facture_numero) ?></a>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Élève</dt>
                    <dd class="font-medium text-slate-800"><?= htmlspecialchars($paiement->eleve_nom) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Matricule</dt>
                    <dd class="font-mono text-xs text-slate-600"><?= htmlspecialchars($paiement->eleve_matricule) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Total facture</dt>
                    <dd class="font-semibold text-slate-800"><?= $fmtMontant((float)$paiement->montant_total) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Total payé (facture)</dt>
                    <dd class="font-semibold text-emerald-700"><?= $fmtMontant((float)$paiement->facture_montant_paye) ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Statut facture</dt>
                    <dd>
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                            <?= match($paiement->facture_statut) {
                                'payee' => 'bg-emerald-100 text-emerald-700',
                                'partiellement_payee' => 'bg-blue-100 text-blue-700',
                                'en_retard' => 'bg-rose-100 text-rose-700',
                                'annulee' => 'bg-slate-200 text-slate-500',
                                default => 'bg-amber-100 text-amber-700'
                            } ?>">
                            <?= htmlspecialchars($paiement->facture_statut) ?>
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Reçu -->
    <?php if ($paiement->recu_numero): ?>
    <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <i data-lucide="file-check" class="w-5 h-5 text-emerald-600"></i>
            <div>
                <div class="text-sm font-semibold text-emerald-800">Reçu généré</div>
                <div class="text-xs text-emerald-600"><?= htmlspecialchars($paiement->recu_numero) ?> · <?= $paiement->recu_date ? date('d/m/Y', strtotime($paiement->recu_date)) : '' ?></div>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/recu"
               class="px-3 py-1.5 text-sm text-emerald-700 bg-white border border-emerald-300 rounded-lg hover:bg-emerald-50">
                <i data-lucide="eye" class="inline w-3.5 h-3.5 mr-1"></i> Voir
            </a>
            <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/recu/print" target="_blank"
               class="px-3 py-1.5 text-sm text-emerald-700 bg-white border border-emerald-300 rounded-lg hover:bg-emerald-50">
                <i data-lucide="printer" class="inline w-3.5 h-3.5 mr-1"></i> Imprimer
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Trop-perçu -->
    <?php if ($trop_percu): ?>
    <div class="bg-amber-50 rounded-xl border border-amber-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600"></i>
                <div class="text-sm font-semibold text-amber-800">Trop-perçu</div>
            </div>
            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                <?= htmlspecialchars($trop_percu->statut) ?>
            </span>
        </div>
        <div class="text-sm text-amber-700 mb-3">
            Montant : <strong><?= $fmtMontant((float)$trop_percu->montant) ?></strong>
        </div>
        <?php if ($canTropPercu && $trop_percu->statut === 'en_attente'): ?>
        <div class="flex gap-2">
            <?php foreach (['restituer' => 'Restituer', 'imputer' => 'Imputer', 'annuler' => 'Annuler'] as $action => $label): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/trop-percu/<?= $trop_percu->id ?>/<?= $action ?>">
                <?php echo csrf_field() ?? '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">'; ?>
                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-amber-700 bg-amber-100 rounded-lg hover:bg-amber-200"
                        onclick="return confirm('Confirmer : <?= $label ?> ce trop-perçu ?')">
                    <?= $label ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Remboursements -->
    <?php if ($remboursements): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-4">Remboursements</div>
        <div class="space-y-3">
            <?php foreach ($remboursements as $r): ?>
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg text-sm">
                <div>
                    <div class="font-medium text-slate-800"><?= $fmtMontant((float)$r->montant) ?></div>
                    <div class="text-xs text-slate-500">
                        <?= htmlspecialchars($r->mode_remboursement ?? '—') ?> ·
                        <?= $r->date_remboursement ? date('d/m/Y', strtotime($r->date_remboursement)) : '—' ?>
                    </div>
                    <?php if ($r->motif): ?>
                    <div class="text-xs text-slate-500 mt-1">Motif : <?= htmlspecialchars($r->motif) ?></div>
                    <?php endif; ?>
                </div>
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                    <?= $r->statut === 'complete' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                    <?= htmlspecialchars($r->statut) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Traçabilité -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Traçabilité</div>
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <?php if ($paiement->caissier_prenom): ?>
            <div>
                <dt class="text-xs text-slate-400">Enregistré par</dt>
                <dd><?= htmlspecialchars($paiement->caissier_prenom . ' ' . $paiement->caissier_nom) ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($paiement->valideur_prenom): ?>
            <div>
                <dt class="text-xs text-slate-400">Validé par</dt>
                <dd><?= htmlspecialchars($paiement->valideur_prenom . ' ' . $paiement->valideur_nom) ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($paiement->annuleur_prenom): ?>
            <div>
                <dt class="text-xs text-slate-400">Annulé par</dt>
                <dd class="text-rose-700"><?= htmlspecialchars($paiement->annuleur_prenom . ' ' . $paiement->annuleur_nom) ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($paiement->motif_annulation): ?>
            <div class="col-span-2">
                <dt class="text-xs text-slate-400">Motif annulation</dt>
                <dd class="text-slate-700"><?= htmlspecialchars($paiement->motif_annulation) ?></dd>
            </div>
            <?php endif; ?>
            <div>
                <dt class="text-xs text-slate-400">Créé le</dt>
                <dd><?= $paiement->created_at ? date('d/m/Y H:i', strtotime($paiement->created_at)) : '—' ?></dd>
            </div>
        </dl>
    </div>

    <!-- Actions dangereuses -->
    <?php if ($canAnnuler && in_array($paiement->statut, ['initie','valide'], true)): ?>
    <div class="bg-white rounded-xl border border-rose-200 p-5">
        <div class="text-sm font-semibold text-rose-700 mb-3">Zone dangereuse</div>
        <button onclick="document.getElementById('modal_annuler').classList.remove('hidden')"
                class="px-4 py-2 text-sm font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100">
            <i data-lucide="x-circle" class="inline w-4 h-4 mr-1"></i> Annuler ce paiement
        </button>
    </div>
    <?php endif; ?>

    <?php if ($canRembourser && $paiement->statut === 'complete'): ?>
    <div class="bg-white rounded-xl border border-amber-200 p-5">
        <div class="text-sm font-semibold text-amber-700 mb-3">Remboursement</div>
        <button onclick="document.getElementById('modal_rembourser').classList.remove('hidden')"
                class="px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
            <i data-lucide="rotate-ccw" class="inline w-4 h-4 mr-1"></i> Initier un remboursement
        </button>
    </div>
    <?php endif; ?>

</div>

<!-- Modal annuler -->
<div id="modal_annuler" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Annuler le paiement</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/annuler">
            <?php echo csrf_field() ?? '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">'; ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Motif <span class="text-red-500">*</span></label>
                <textarea name="motif" rows="3" required
                          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-300"
                          placeholder="Raison de l'annulation…"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-2 text-sm font-semibold text-white bg-rose-600 rounded-lg hover:bg-rose-700">
                    Confirmer l'annulation
                </button>
                <button type="button" onclick="document.getElementById('modal_annuler').classList.add('hidden')"
                        class="flex-1 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                    Fermer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal rembourser -->
<div id="modal_rembourser" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Initier un remboursement</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/paiements/<?= $paiement->id ?>/rembourser">
            <?php echo csrf_field() ?? '<input type="hidden" name="csrf_token" value="' . ($_SESSION['csrf_token'] ?? '') . '">'; ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Montant à rembourser <span class="text-red-500">*</span></label>
                    <input type="number" name="montant" step="0.01" min="0.01"
                           value="<?= htmlspecialchars((string)$paiement->montant_applique) ?>" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mode de remboursement</label>
                    <select name="mode_remboursement" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-300">
                        <?php foreach ($modesRemboursement as $code): ?>
                        <option value="<?= $code ?>"><?= $code ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Motif <span class="text-red-500">*</span></label>
                    <textarea name="motif" rows="2" required
                              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-300"
                              placeholder="Raison du remboursement…"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Référence remboursement</label>
                    <input type="text" name="reference_remboursement"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-300"
                           placeholder="N° virement…">
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="flex-1 py-2 text-sm font-semibold text-white bg-amber-600 rounded-lg hover:bg-amber-700">
                    Confirmer le remboursement
                </button>
                <button type="button" onclick="document.getElementById('modal_rembourser').classList.add('hidden')"
                        class="flex-1 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                    Fermer
                </button>
            </div>
        </form>
    </div>
</div>
