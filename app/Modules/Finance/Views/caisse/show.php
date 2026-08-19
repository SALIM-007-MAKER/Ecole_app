<?php
/**
 * Finance V2 — Détail session de caisse
 */
$session    = $session    ?? null;
$mouvements = $mouvements ?? [];
$stats_type = $stats_type ?? [];
$journal    = $journal    ?? null;
$types      = $types      ?? [];

$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$isActive   = in_array($session->statut, ['ouverte','en_activite'], true);

$badgeStatut = match ($session->statut) {
    'ouverte'     => 'bg-blue-100 text-blue-700',
    'en_activite' => 'bg-emerald-100 text-emerald-700',
    'fermee'      => 'bg-slate-100 text-slate-600',
    default       => 'bg-rose-100 text-rose-600',
};
$statuts = \App\Modules\Finance\Models\SessionCaisseModel::STATUTS;
?>
<div class="max-w-4xl mx-auto space-y-6">

    <!-- En-tête -->
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/finance/caisse"
               class="p-2 text-slate-500 hover:text-violet-600 rounded-lg hover:bg-violet-50">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
                <i data-lucide="vault" class="w-5 h-5 text-violet-600"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($session->numero) ?></h1>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $badgeStatut ?>">
                        <?= $statuts[$session->statut] ?? $session->statut ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5">
                    Caissier : <strong><?= htmlspecialchars($session->caissier_nom ?? '—') ?></strong>
                    · Ouvert le <?= date('d/m/Y à H:i', strtotime($session->date_ouverture . ' ' . $session->heure_ouverture)) ?>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>/journal/print" target="_blank"
               class="px-3 py-2 text-sm text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                <i data-lucide="printer" class="inline w-4 h-4 mr-1"></i> Imprimer
            </a>
            <?php if ($isActive && $canFermer): ?>
            <a href="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>/fermer"
               class="px-3 py-2 text-sm font-medium text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100">
                <i data-lucide="lock" class="inline w-4 h-4 mr-1"></i> Fermer la caisse
            </a>
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
    <?php if ($flash = \Core\Session::getFlash('warning')): ?>
    <div class="flex items-center gap-3 p-4 bg-amber-50 text-amber-800 rounded-xl border border-amber-200">
        <i data-lucide="alert-triangle" class="w-5 h-5"></i><span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Récap soldes -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Solde initial</div>
            <div class="text-xl font-bold text-slate-700 mt-1"><?= $fmtMontant((float)$session->solde_initial) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Recettes</div>
            <div class="text-xl font-bold text-emerald-700 mt-1"><?= $fmtMontant((float)$session->total_recettes) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Décaissements</div>
            <div class="text-xl font-bold text-rose-600 mt-1"><?= $fmtMontant((float)$session->total_decaissements) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-xs text-slate-400 uppercase tracking-wide">Solde théorique</div>
            <div class="text-xl font-bold text-violet-700 mt-1"><?= $fmtMontant((float)$session->solde_theorique) ?></div>
        </div>
    </div>

    <!-- Fermeture info -->
    <?php if ($session->statut === 'fermee'): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Fermeture</div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs text-slate-400">Date/heure fermeture</div>
                <div class="font-medium"><?= date('d/m/Y', strtotime($session->date_fermeture)) ?> <?= substr($session->heure_fermeture, 0, 5) ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-400">Solde théorique</div>
                <div class="font-semibold text-slate-800"><?= $fmtMontant((float)$session->solde_theorique) ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-400">Solde réel compté</div>
                <div class="font-semibold text-slate-800"><?= $fmtMontant((float)$session->solde_reel) ?></div>
            </div>
            <div>
                <div class="text-xs text-slate-400">Écart</div>
                <?php $ecart = (float)$session->ecart; ?>
                <div class="font-bold <?= $ecart < 0 ? 'text-rose-600' : ($ecart > 0 ? 'text-amber-600' : 'text-emerald-600') ?>">
                    <?= ($ecart >= 0 ? '+' : '') . $fmtMontant(abs($ecart)) ?>
                </div>
            </div>
        </div>
        <?php if ($session->note_fermeture): ?>
        <div class="mt-3 text-sm text-slate-600 bg-slate-50 rounded p-2">
            <?= nl2br(htmlspecialchars($session->note_fermeture)) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Rapprochement -->
    <?php if ($session->statut === 'fermee' && $journal): ?>
    <div class="flex items-center justify-between p-4 <?= $journal->statut === 'rapproche' ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' ?> rounded-xl border">
        <div class="flex items-center gap-2 text-sm">
            <i data-lucide="<?= $journal->statut === 'rapproche' ? 'check-circle-2' : 'clock' ?>" class="w-4 h-4 <?= $journal->statut === 'rapproche' ? 'text-emerald-600' : 'text-amber-600' ?>"></i>
            <span class="font-medium <?= $journal->statut === 'rapproche' ? 'text-emerald-800' : 'text-amber-800' ?>">
                Journal <?= $journal->statut === 'rapproche' ? 'rapproché et validé' : 'en attente de rapprochement' ?>
            </span>
        </div>
        <?php if ($journal->statut !== 'rapproche' && $canRapproche): ?>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>/rapprocher" class="flex items-center gap-2">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <input type="text" name="note" placeholder="Note de rapprochement…"
                   class="px-3 py-1.5 text-sm border border-amber-300 rounded-lg focus:ring-2 focus:ring-amber-300">
            <button class="px-3 py-1.5 text-sm font-medium text-amber-800 bg-amber-100 rounded-lg hover:bg-amber-200">
                Valider
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire nouveau mouvement -->
    <?php if ($isActive && $canMouvement): ?>
    <div class="bg-white rounded-xl border border-violet-200 p-5">
        <div class="text-sm font-semibold text-slate-700 mb-4">
            <i data-lucide="plus-circle" class="inline w-4 h-4 mr-1 text-violet-600"></i>
            Enregistrer un mouvement
        </div>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>/mouvement"
              class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Type <span class="form-required">*</span></label>
                <select name="type" id="mv_type" required onchange="updateSens(this.value)"
                        class="form-select">
                    <option value="recette">Recette</option>
                    <option value="decaissement">Décaissement</option>
                    <option value="correction">Correction</option>
                </select>
            </div>
            <div id="sens_field" class="hidden">
                <label class="block text-xs text-slate-500 mb-1">Sens</label>
                <select name="sens" class="form-select">
                    <option value="credit">Crédit (entrée)</option>
                    <option value="debit">Débit (sortie)</option>
                </select>
            </div>
            <input type="hidden" name="sens" id="sens_hidden" value="credit">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Montant <span class="form-required">*</span></label>
                <div class="relative">
                    <input type="number" name="montant" min="0.01" step="0.01" required placeholder="0"
                           class="form-input pl-3 pr-10">
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">XOF</span>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs text-slate-500 mb-1">Libellé <span class="form-required">*</span></label>
                <input type="text" name="libelle" required placeholder="Description…"
                       class="form-input">
            </div>
            <div>
                <button type="submit"
                        class="w-full py-2 text-sm font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Liste des mouvements -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <span class="text-sm font-semibold text-slate-700">Mouvements (<?= count($mouvements) ?>)</span>
        </div>
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Heure</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Type</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Réf.</th>
                    <th class="px-4 py-2 text-right font-medium text-slate-500">Entrée (+)</th>
                    <th class="px-4 py-2 text-right font-medium text-slate-500">Sortie (−)</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                    <?php if ($canAnnuler && $isActive): ?><th class="px-4 py-2"></th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (empty($mouvements)): ?>
                <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">Aucun mouvement enregistré</td></tr>
                <?php else: ?>
                <?php foreach ($mouvements as $m): ?>
                <tr class="<?= $m->statut === 'annule' ? 'opacity-50 bg-slate-50' : 'hover:bg-slate-50' ?>">
                    <td class="px-4 py-2 text-slate-500 text-xs"><?= date('H:i', strtotime($m->created_at)) ?></td>
                    <td class="px-4 py-2">
                        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium
                            <?= match($m->type) {
                                'recette'      => 'bg-emerald-50 text-emerald-700',
                                'decaissement' => 'bg-rose-50 text-rose-700',
                                'correction'   => 'bg-amber-50 text-amber-700',
                                'ouverture'    => 'bg-blue-50 text-blue-700',
                                'annulation'   => 'bg-slate-50 text-slate-500',
                                default        => 'bg-slate-50 text-slate-600',
                            } ?>">
                            <?= $types[$m->type] ?? $m->type ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-slate-700 <?= $m->statut === 'annule' ? 'line-through' : '' ?>">
                        <?= htmlspecialchars($m->libelle) ?>
                        <?php if ($m->motif_annulation): ?>
                        <div class="text-xs text-slate-400">Motif : <?= htmlspecialchars($m->motif_annulation) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-slate-500"><?= htmlspecialchars($m->reference ?? '—') ?></td>
                    <td class="px-4 py-2 text-right font-semibold <?= ($m->sens === 'credit' && $m->statut === 'actif') ? 'text-emerald-700' : 'text-slate-400' ?>">
                        <?= $m->sens === 'credit' && $m->statut === 'actif' ? $fmtMontant((float)$m->montant) : '—' ?>
                    </td>
                    <td class="px-4 py-2 text-right font-semibold <?= ($m->sens === 'debit' && $m->statut === 'actif') ? 'text-rose-600' : 'text-slate-400' ?>">
                        <?= $m->sens === 'debit' && $m->statut === 'actif' ? $fmtMontant((float)$m->montant) : '—' ?>
                    </td>
                    <td class="px-4 py-2">
                        <?php if ($m->statut === 'annule'): ?>
                        <span class="text-xs text-slate-400">Annulé</span>
                        <?php else: ?>
                        <span class="text-xs text-emerald-600">Actif</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canAnnuler && $isActive): ?>
                    <td class="px-4 py-2">
                        <?php if ($m->statut === 'actif' && in_array($m->type, ['recette','decaissement','correction'], true)): ?>
                        <button onclick="document.getElementById('modal_annuler_<?= $m->id ?>').classList.remove('hidden')"
                                class="p-1 text-slate-400 hover:text-rose-600 rounded">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($mouvements): ?>
            <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                <tr>
                    <td colspan="4" class="px-4 py-3 text-sm font-semibold text-slate-700">TOTAUX</td>
                    <td class="px-4 py-3 text-right font-bold text-emerald-700"><?= $fmtMontant((float)$session->total_recettes) ?></td>
                    <td class="px-4 py-3 text-right font-bold text-rose-600"><?= $fmtMontant((float)$session->total_decaissements) ?></td>
                    <td colspan="<?= ($canAnnuler && $isActive) ? 2 : 1 ?>"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

</div>

<!-- Modals annuler mouvements -->
<?php foreach ($mouvements as $m): if ($m->statut !== 'actif' || !in_array($m->type, ['recette','decaissement','correction'], true)) continue; ?>
<div id="modal_annuler_<?= $m->id ?>" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Annuler le mouvement</h3>
        <p class="text-sm text-slate-500 mb-4">
            <?= htmlspecialchars($m->libelle) ?> — <?= number_format((float)$m->montant, 0, ',', ' ') ?> XOF
            <br><span class="text-xs text-amber-600">Un mouvement inverse sera créé automatiquement.</span>
        </p>
        <form method="POST" action="<?= BASE_URL ?>/v2/finance/caisse/<?= $session->id ?>/mouvement/<?= $m->id ?>/annuler">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken(), ENT_QUOTES) ?>">
            <div class="mb-4">
                <label class="form-label">Motif <span class="form-required">*</span></label>
                <textarea name="motif" rows="2" required
                          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-300"
                          placeholder="Raison de l'annulation…"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 py-2 text-sm font-semibold text-white bg-rose-600 rounded-lg hover:bg-rose-700">
                    Confirmer l'annulation
                </button>
                <button type="button" onclick="document.getElementById('modal_annuler_<?= $m->id ?>').classList.add('hidden')"
                        class="flex-1 py-2 text-sm text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                    Fermer
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
function updateSens(type) {
    const sensField  = document.getElementById('sens_field');
    const sensHidden = document.getElementById('sens_hidden');
    if (type === 'correction') {
        sensField.classList.remove('hidden');
        sensHidden.disabled = true;
    } else {
        sensField.classList.add('hidden');
        sensHidden.disabled = false;
        sensHidden.value = (type === 'recette') ? 'credit' : 'debit';
    }
}
document.addEventListener('DOMContentLoaded', () => updateSens(document.getElementById('mv_type').value));
</script>
