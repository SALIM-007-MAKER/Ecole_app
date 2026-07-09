<?php
/**
 * Finance V2 — Aperçu du reçu de paiement
 */
$recu = $recu ?? null;
$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
?>
<div class="max-w-2xl mx-auto space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $recu->paiement_id ?>"
               class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Reçu <?= htmlspecialchars($recu->numero) ?></h1>
                <p class="text-sm text-slate-500">Émis le <?= $recu->date_emission ? date('d/m/Y à H:i', strtotime($recu->date_emission)) : '—' ?></p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/v2/finance/paiements/<?= $recu->paiement_id ?>/recu/print" target="_blank"
           class="px-4 py-2 text-sm font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100">
            <i data-lucide="printer" class="inline w-4 h-4 mr-1"></i> Imprimer
        </a>
    </div>

    <!-- Aperçu reçu -->
    <div class="bg-white rounded-xl border-2 border-slate-200 overflow-hidden">

        <!-- Header reçu -->
        <div class="bg-violet-700 text-white px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl font-bold tracking-wide">REÇU DE PAIEMENT</div>
                    <div class="text-violet-200 text-sm mt-0.5">Numéro : <?= htmlspecialchars($recu->numero) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-violet-200 text-xs">Date d'émission</div>
                    <div class="font-semibold"><?= $recu->date_emission ? date('d/m/Y', strtotime($recu->date_emission)) : '—' ?></div>
                </div>
            </div>
        </div>

        <div class="p-8 space-y-6">

            <!-- Bénéficiaire -->
            <div class="grid grid-cols-2 gap-8">
                <div>
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Bénéficiaire</div>
                    <div class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($recu->eleve_nom) ?></div>
                    <div class="text-sm text-slate-500">Matricule : <?= htmlspecialchars($recu->matricule) ?></div>
                </div>
                <div>
                    <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Facture</div>
                    <div class="font-semibold text-slate-800"><?= htmlspecialchars($recu->facture_numero) ?></div>
                    <div class="text-sm text-slate-500">Année : <?= htmlspecialchars($recu->annee_scolaire) ?></div>
                </div>
            </div>

            <hr class="border-slate-200">

            <!-- Montant -->
            <div class="text-center py-4">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Montant encaissé</div>
                <div class="text-4xl font-black text-violet-700"><?= $fmtMontant((float)$recu->montant) ?></div>
                <div class="text-sm text-slate-500 mt-2">
                    Mode : <strong><?= htmlspecialchars($recu->mode_nom ?? $recu->mode_code ?? '—') ?></strong>
                    · Date paiement : <strong><?= $recu->date_paiement ? date('d/m/Y', strtotime($recu->date_paiement)) : '—' ?></strong>
                </div>
            </div>

            <hr class="border-slate-200">

            <!-- Émetteur & tampon -->
            <div class="flex items-end justify-between text-sm">
                <div>
                    <div class="text-xs text-slate-400 mb-1">Encaissé par</div>
                    <div class="font-medium text-slate-700">
                        <?= $recu->emetteur_prenom ? htmlspecialchars($recu->emetteur_prenom . ' ' . $recu->emetteur_nom) : '—' ?>
                    </div>
                </div>
                <div class="text-right">
                    <div class="inline-block border-2 border-dashed border-emerald-300 rounded-xl px-6 py-3 text-emerald-700">
                        <i data-lucide="check-circle" class="w-6 h-6 mx-auto mb-1"></i>
                        <div class="text-xs font-bold uppercase tracking-wide">Paiement reçu</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
