<?php
/**
 * Finance V2 — Détail décaissement
 * GET /v2/finance/decaissements/{id}
 */
$dec           = $dec           ?? null;
$justificatifs = $justificatifs ?? [];
$statuts       = $statuts       ?? [];
$modes         = $modes         ?? [];
$seuil         = $seuil         ?? 50000.0;

$fmt = fn(float $v): string => number_format($v, 0, ',', ' ') . ' ' . ($dec->devise ?? 'XOF');
$badge = \App\Modules\Finance\Models\DecaissementModel::statutColor($dec->statut);

$depasseSeuil     = (float)$dec->montant > $seuil;
$payableDirect    = $dec->statut === 'valide' && !$depasseSeuil;
$peutPayer        = $canPayer && ($dec->statut === 'approuve' || $payableDirect);
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <a href="<?= BASE_URL ?>/v2/finance/decaissements"
               class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 flex-shrink-0 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </a>
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="arrow-down-circle" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-800 font-mono"><?= htmlspecialchars($dec->numero) ?></h1>
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-full <?= $badge ?>">
                        <?= $statuts[$dec->statut] ?? $dec->statut ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5"><?= htmlspecialchars($dec->libelle) ?></p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
            <?php if ($canValider && $dec->statut === 'soumis'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/valider" class="inline">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                <button type="submit" onclick="return confirm('Valider ce décaissement ?')" class="btn btn-primary">
                    <i data-lucide="check" class="w-4 h-4"></i> Valider
                </button>
            </form>
            <?php endif; ?>

            <?php if ($canApprouver && $dec->statut === 'valide'): ?>
            <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/approuver" class="inline">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                <button type="submit" onclick="return confirm('Approuver ce décaissement ?')" class="btn btn-warning">
                    <i data-lucide="shield-check" class="w-4 h-4"></i> Approuver
                </button>
            </form>
            <?php endif; ?>

            <?php if ($canRejeter && in_array($dec->statut, ['soumis', 'valide'], true)): ?>
            <button onclick="document.getElementById('modal-rejeter').classList.remove('hidden')" class="btn btn-outline-danger">
                <i data-lucide="x" class="w-4 h-4"></i> Rejeter
            </button>
            <?php endif; ?>

            <?php if ($peutPayer): ?>
            <button onclick="document.getElementById('modal-payer').classList.remove('hidden')" class="btn btn-success">
                <i data-lucide="banknote" class="w-4 h-4"></i> Payer
            </button>
            <?php endif; ?>

            <?php if ($canAnnuler && in_array($dec->statut, ['soumis', 'valide', 'approuve'], true)): ?>
            <button onclick="document.getElementById('modal-annuler').classList.remove('hidden')" class="btn btn-secondary">
                <i data-lucide="ban" class="w-4 h-4"></i> Annuler
            </button>
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

    <?php if ($depasseSeuil && in_array($dec->statut, ['soumis', 'valide'], true)): ?>
    <div class="flex items-center gap-3 p-4 bg-amber-50 text-amber-800 rounded-xl border border-amber-200">
        <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0"></i>
        <span>Ce décaissement dépasse le seuil d'approbation (<?= $fmt($seuil) ?>) : l'approbation du directeur
            <?php if ($dec->statut === 'soumis'): ?>sera requise après validation<?php else: ?>est requise avant paiement<?php endif; ?>,
            ainsi qu'au moins un justificatif joint.</span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Colonne principale -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Détails -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3 text-sm">
                <h2 class="font-semibold text-slate-800 mb-2">Détails</h2>
                <div class="flex justify-between">
                    <span class="text-slate-500">Montant</span>
                    <span class="font-mono font-bold text-lg text-slate-900"><?= $fmt((float)$dec->montant) ?></span>
                </div>
                <?php if ($dec->description): ?>
                <div class="pt-2 border-t border-slate-100">
                    <p class="text-xs text-slate-500 mb-1">Description</p>
                    <p class="text-slate-700"><?= nl2br(htmlspecialchars($dec->description)) ?></p>
                </div>
                <?php endif; ?>
                <div class="flex justify-between pt-2 border-t border-slate-100">
                    <span class="text-slate-500">Catégorie</span>
                    <span class="text-slate-800"><?= htmlspecialchars($dec->categorie_nom ?? '—') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Fournisseur</span>
                    <span class="text-slate-800">
                        <?= htmlspecialchars($dec->fournisseur_nom ?? '—') ?>
                        <?php if ($dec->fournisseur_contact): ?>
                        <span class="text-xs text-slate-400 block"><?= htmlspecialchars($dec->fournisseur_contact) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Date de dépense</span>
                    <span class="text-slate-800"><?= $dec->date_depense ?></span>
                </div>
                <?php if ($dec->date_echeance): ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Échéance</span>
                    <span class="text-slate-800"><?= $dec->date_echeance ?></span>
                </div>
                <?php endif; ?>
                <?php if ($dec->reference_externe): ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Référence externe</span>
                    <span class="text-slate-800 font-mono"><?= htmlspecialchars($dec->reference_externe) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($dec->mode_nom): ?>
                <div class="flex justify-between">
                    <span class="text-slate-500">Mode de paiement</span>
                    <span class="text-slate-800"><?= htmlspecialchars($dec->mode_nom) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($dec->note): ?>
                <div class="pt-2 border-t border-slate-100">
                    <p class="text-xs text-slate-500 mb-1">Note</p>
                    <p class="text-slate-700"><?= nl2br(htmlspecialchars($dec->note)) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($dec->statut === 'rejete' && $dec->motif_rejet): ?>
                <div class="pt-2 border-t border-red-100 bg-red-50 rounded-lg p-3 space-y-1 mt-2">
                    <p class="text-xs font-semibold text-red-700">REJETÉ</p>
                    <p class="text-xs text-red-600 italic"><?= htmlspecialchars($dec->motif_rejet) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($dec->statut === 'annule'): ?>
                <div class="pt-2 border-t border-rose-100 bg-rose-50 rounded-lg p-3 space-y-1 mt-2">
                    <p class="text-xs font-semibold text-rose-700">ANNULÉ le <?= $dec->date_annulation ?></p>
                    <?php if ($dec->annule_par_nom): ?>
                    <p class="text-xs text-rose-600">Par <?= htmlspecialchars($dec->annule_par_nom) ?></p>
                    <?php endif; ?>
                    <?php if ($dec->motif_annulation): ?>
                    <p class="text-xs text-rose-600 italic"><?= htmlspecialchars($dec->motif_annulation) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Justificatifs -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800">Pièces justificatives</h2>
                    <?php if (in_array($dec->statut, ['soumis', 'valide', 'approuve'], true)): ?>
                    <button onclick="document.getElementById('modal-justificatif').classList.remove('hidden')"
                            class="text-sm text-violet-600 hover:text-violet-800">
                        <i data-lucide="paperclip" class="inline w-4 h-4"></i> Ajouter
                    </button>
                    <?php endif; ?>
                </div>
                <?php if (empty($justificatifs)): ?>
                <p class="px-5 py-4 text-sm text-slate-400">Aucun justificatif joint.</p>
                <?php else: ?>
                <div class="divide-y divide-slate-50">
                    <?php foreach ($justificatifs as $j): ?>
                    <div class="px-5 py-3 flex items-center justify-between text-sm">
                        <div class="flex items-center gap-3">
                            <i data-lucide="file-text" class="w-4 h-4 text-slate-400"></i>
                            <div>
                                <a href="<?= BASE_URL ?>/<?= htmlspecialchars($j->chemin) ?>" target="_blank"
                                   class="text-slate-800 hover:text-violet-700"><?= htmlspecialchars($j->nom_fichier) ?></a>
                                <div class="text-xs text-slate-400">
                                    <?= number_format($j->taille / 1024, 0) ?> Ko · ajouté par <?= htmlspecialchars($j->uploade_par_nom ?? '—') ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($dec->statut === 'soumis' || $dec->statut === 'valide'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/justificatifs/<?= $j->id ?>/delete">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
                            <button type="submit" onclick="return confirm('Retirer ce justificatif ?')"
                                    class="p-1 text-slate-300 hover:text-red-500">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Colonne latérale — piste d'audit -->
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-3 text-sm">
                <h2 class="font-semibold text-slate-800 mb-3">Piste d'audit</h2>

                <div class="flex gap-3">
                    <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="send" class="w-3.5 h-3.5 text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-slate-800">Soumis par <?= htmlspecialchars($dec->saisi_par_nom ?? '—') ?></p>
                        <p class="text-xs text-slate-400"><?= $dec->created_at ?></p>
                    </div>
                </div>

                <?php if ($dec->date_validation): ?>
                <div class="flex gap-3">
                    <div class="w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-violet-600"></i>
                    </div>
                    <div>
                        <p class="text-slate-800">Validé par <?= htmlspecialchars($dec->valide_par_nom ?? '—') ?></p>
                        <p class="text-xs text-slate-400"><?= $dec->date_validation ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($dec->date_approbation): ?>
                <div class="flex gap-3">
                    <div class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="shield-check" class="w-3.5 h-3.5 text-amber-600"></i>
                    </div>
                    <div>
                        <p class="text-slate-800">Approuvé par <?= htmlspecialchars($dec->approuve_par_nom ?? '—') ?></p>
                        <p class="text-xs text-slate-400"><?= $dec->date_approbation ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($dec->date_paiement): ?>
                <div class="flex gap-3">
                    <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <div>
                        <p class="text-slate-800">Payé par <?= htmlspecialchars($dec->paye_par_nom ?? '—') ?></p>
                        <p class="text-xs text-slate-400"><?= $dec->date_paiement ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal rejet -->
<div id="modal-rejeter" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Rejeter le décaissement</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/rejeter">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div class="mb-4">
                <label class="form-label">Motif de rejet <span class="form-required">*</span></label>
                <textarea name="motif" rows="3" required
                          class="form-textarea"
                          placeholder="Raison du rejet..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-rejeter').classList.add('hidden')"
                        class="btn btn-secondary">Fermer</button>
                <button type="submit" class="btn btn-danger">
                    Confirmer le rejet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal annulation -->
<div id="modal-annuler" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Annuler le décaissement</h3>
        <p class="text-sm text-slate-500 mb-4">Cette action est irréversible et n'est possible que tant que le décaissement n'a pas été payé.</p>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/annuler">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div class="mb-4">
                <label class="form-label">Motif d'annulation <span class="form-required">*</span></label>
                <textarea name="motif" rows="3" required
                          class="form-textarea"
                          placeholder="Raison de l'annulation..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-annuler').classList.add('hidden')"
                        class="btn btn-secondary">Fermer</button>
                <button type="submit" class="btn btn-danger">
                    Confirmer l'annulation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal paiement -->
<div id="modal-payer" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Payer le décaissement</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/payer" class="space-y-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div>
                <label class="form-label">Mode de paiement <span class="form-required">*</span></label>
                <select name="mode_paiement" required class="form-select">
                    <?php foreach ($modes as $m): ?>
                    <option value="<?= htmlspecialchars($m->code) ?>"><?= htmlspecialchars($m->nom) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-1">Un paiement en espèces sera automatiquement imputé à votre session de caisse active.</p>
            </div>
            <div>
                <label class="form-label">Référence de paiement</label>
                <input type="text" name="reference_externe" placeholder="N° chèque, virement..."
                       class="form-input">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-payer').classList.add('hidden')"
                        class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-success">
                    Confirmer le paiement
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal justificatif -->
<div id="modal-justificatif" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Ajouter un justificatif</h3>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/decaissements/<?= $dec->id ?>/justificatifs"
              enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div>
                <label class="form-label">Fichier <span class="form-required">*</span></label>
                <input type="file" name="justificatif" required accept=".jpg,.jpeg,.png,.pdf"
                       class="form-input">
                <p class="text-xs text-slate-400 mt-1">JPEG, PNG ou PDF, 5 Mo maximum.</p>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-justificatif').classList.add('hidden')"
                        class="btn btn-secondary">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    Téléverser
                </button>
            </div>
        </form>
    </div>
</div>
