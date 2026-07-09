<?php
$paiement  = $paiement  ?? null;
$modes     = $modes     ?? [];
$csrfToken = \Core\Session::getCsrfToken();
$currentUser = \Core\Session::getUser();
$canEdit   = in_array('comptabilite.edit', $currentUser['permissions'] ?? [], true);

if (!$paiement) {
    echo '<div class="rounded-xl border border-red-200 bg-red-50 text-red-700 px-5 py-4">Paiement introuvable.</div>';
    return;
}

$m = $modes[$paiement->mode_paiement] ?? ['label' => $paiement->mode_paiement];
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
            <i data-lucide="receipt" class="w-6 h-6 text-emerald-600"></i>Paiement
        </h2>
        <p class="text-sm font-mono text-slate-500 mt-0.5">
            <?= htmlspecialchars($paiement->reference ?? '#' . $paiement->id, ENT_QUOTES) ?>
        </p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/paiements/<?= $paiement->id ?>/recu" target="_blank" class="btn btn-outline">
            <i data-lucide="printer" class="w-4 h-4"></i>Imprimer reçu
        </a>
        <?php if ($canEdit): ?>
        <button class="btn btn-outline text-red-600 border-red-200 hover:bg-red-50"
                onclick="openModal('delModal')">
            <i data-lucide="trash-2" class="w-4 h-4"></i>Supprimer
        </button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/paiements" class="btn btn-outline">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>Retour
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    <!-- Détails paiement -->
    <div class="lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700">
            Détails du paiement
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-y-4 text-sm">
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Référence</p>
                    <p class="font-mono font-semibold text-slate-800">
                        <?= htmlspecialchars($paiement->reference ?? '—', ENT_QUOTES) ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Date</p>
                    <p class="font-semibold text-slate-800"><?= date('d/m/Y', strtotime($paiement->date_paiement)) ?></p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Montant</p>
                    <p class="text-xl font-bold text-emerald-600">
                        <?= number_format((float)$paiement->montant, 2, ',', ' ') ?> FCFA
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Mode</p>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">
                        <?= htmlspecialchars($m['label'], ENT_QUOTES) ?>
                    </span>
                </div>
                <?php if (!empty($paiement->num_cheque)): ?>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">N° chèque</p>
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($paiement->num_cheque, ENT_QUOTES) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($paiement->frais_nom)): ?>
                <div>
                    <p class="text-xs text-slate-400 mb-0.5">Frais</p>
                    <p class="font-semibold text-slate-800"><?= htmlspecialchars($paiement->frais_nom, ENT_QUOTES) ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($paiement->observations)): ?>
                <div class="col-span-2">
                    <p class="text-xs text-slate-400 mb-0.5">Observations</p>
                    <p class="text-slate-700"><?= htmlspecialchars($paiement->observations, ENT_QUOTES) ?></p>
                </div>
                <?php endif; ?>
                <div class="col-span-2">
                    <p class="text-xs text-slate-400 mb-0.5">Saisi par</p>
                    <p class="text-slate-700">
                        <?= htmlspecialchars($paiement->saisi_par_nom ?? 'Système', ENT_QUOTES) ?>
                        <span class="text-slate-400 text-xs ml-1">— <?= date('d/m/Y H:i', strtotime($paiement->created_at)) ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Infos complémentaires -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Élève -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="user" class="w-4 h-4 text-violet-600"></i>Élève
            </div>
            <div class="p-4">
                <?php if (!empty($paiement->eleve_nom)): ?>
                <p class="font-bold text-slate-800"><?= htmlspecialchars($paiement->eleve_nom, ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($paiement->matricule ?? '', ENT_QUOTES) ?></p>
                <p class="text-sm text-slate-500 mt-1">
                    <?= htmlspecialchars(($paiement->classe_niveau ?? '') . ' ' . ($paiement->classe_nom ?? ''), ENT_QUOTES) ?>
                </p>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/eleves/<?= $paiement->eleve_id ?>" class="btn btn-outline py-1.5 px-3 text-xs">
                        <i data-lucide="user" class="w-3.5 h-3.5"></i>Voir le profil
                    </a>
                </div>
                <?php else: ?>
                <p class="text-sm text-slate-400">Paiement sans élève associé</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Frais concerné -->
        <?php if (!empty($paiement->frais_eleve_id)): ?>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-700 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-amber-500"></i>Frais concerné
            </div>
            <div class="p-4">
                <p class="font-bold text-slate-800"><?= htmlspecialchars($paiement->frais_nom ?? '', ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($paiement->annee_scolaire ?? '', ENT_QUOTES) ?></p>
                <?php if (!empty($paiement->frais_montant)): ?>
                <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                    <div class="rounded-lg bg-slate-50 p-2">
                        <p class="text-xs text-slate-400">Total</p>
                        <p class="font-bold text-slate-700 text-sm">
                            <?= number_format((float)$paiement->frais_montant, 2, ',', ' ') ?>
                        </p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-2">
                        <p class="text-xs text-slate-400">Payé</p>
                        <p class="font-bold text-emerald-600 text-sm">
                            <?= number_format((float)$paiement->frais_paye, 2, ',', ' ') ?>
                        </p>
                    </div>
                    <div class="rounded-lg bg-red-50 p-2">
                        <p class="text-xs text-slate-400">Reste</p>
                        <p class="font-bold text-red-600 text-sm">
                            <?= number_format((float)$paiement->frais_reste, 2, ',', ' ') ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canEdit): ?>
<!-- Modal suppression -->
<div class="modal-overlay" id="delModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title text-red-600">Supprimer ce paiement ?</h3>
            <button class="modal-close" onclick="closeModal('delModal')">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-slate-500">
                Cette action est irréversible. Le statut du frais associé sera recalculé.
            </p>
        </div>
        <div class="modal-footer">
            <form method="POST" action="<?= BASE_URL ?>/paiements/<?= $paiement->id ?>/delete">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-outline" onclick="closeModal('delModal')">Annuler</button>
                    <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700 border-red-600">Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
</script>
<?php endif; ?>
