<?php
/** @var array $paiement, $modes */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$devise = (string)$paiement['devise'];
$montant = static fn($v): string => in_array($devise, ['XOF', 'XAF'], true) ? number_format((float)$v, 0, ',', ' ') : number_format((float)$v, 2, ',', ' ');
$cycles = ['monthly' => 'Mensuel', 'yearly' => 'Annuel', 'legacy' => 'Repris de l\'existant', 'trial' => 'Essai'];
$annule = $paiement['statut'] === 'cancelled';
?>
<div class="flex items-center justify-between gap-4 mb-6 print:hidden">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i data-lucide="receipt" class="w-5 h-5 text-violet-600"></i>Paiement <?= $h($paiement['reference']) ?></h2>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$paiement['etablissement_id'] ?>" class="btn btn-secondary">Retour à l'établissement</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Imprimer</button>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6 max-w-2xl space-y-4">
    <?php if ($annule): ?><p class="text-sm font-semibold text-red-700">PAIEMENT ANNULÉ — ne compte pas dans les revenus.</p><?php endif; ?>
    <p class="text-3xl font-bold <?= $annule ? 'text-slate-400 line-through' : 'text-violet-700' ?>"><?= $montant($paiement['montant']) ?> <span class="text-base font-normal text-slate-400"><?= $h($devise) ?></span></p>
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div><p class="text-slate-400 text-xs">Établissement</p><p class="text-slate-800"><?= $h($paiement['etablissement_nom']) ?></p></div>
        <div><p class="text-slate-400 text-xs">Date du paiement</p><p class="text-slate-800"><?= $h($paiement['date_paiement']) ?></p></div>
        <div><p class="text-slate-400 text-xs">Mode</p><p class="text-slate-800"><?= $h($modes[$paiement['mode']] ?? $paiement['mode']) ?></p></div>
        <div><p class="text-slate-400 text-xs">Référence de l'opération</p><p class="text-slate-800"><?= $h($paiement['reference_externe'] ?? '—') ?></p></div>
        <div><p class="text-slate-400 text-xs">Période réglée</p><p class="text-slate-800"><?= $h($paiement['plan_nom']) ?> · <?= $h($cycles[$paiement['cycle']] ?? $paiement['cycle']) ?> · <?= $h($paiement['date_debut']) ?> → <?= $paiement['date_fin'] ? $h($paiement['date_fin']) : 'sans échéance' ?></p></div>
        <div><p class="text-slate-400 text-xs">Prix de la période</p><p class="text-slate-800"><?= $paiement['prix_applique'] !== null ? $montant($paiement['prix_applique']) . ' ' . $h($devise) : 'inconnu' ?></p></div>
        <div><p class="text-slate-400 text-xs">Saisi par</p><p class="text-slate-800"><?= $h($paiement['saisi_par_email'] ?? '—') ?> le <?= $h($paiement['created_at']) ?></p></div>
        <?php if ($paiement['note']): ?><div><p class="text-slate-400 text-xs">Note</p><p class="text-slate-800"><?= $h($paiement['note']) ?></p></div><?php endif; ?>
    </div>
    <?php $coord = \Core\Platform\PlatformSettings::valeurSiConnecte(\Core\Platform\PlatformSettings::PAIEMENT); if ($coord !== ''): ?>
    <div class="text-xs text-slate-500 border-t border-slate-100 pt-3"><p class="font-semibold">Coordonnées de paiement</p><p class="whitespace-pre-line"><?= $h($coord) ?></p></div>
    <?php endif; ?>
    <?php if ($annule): ?>
    <div class="text-sm text-slate-700">Annulé par <?= $h($paiement['annule_par_email'] ?? '—') ?> le <?= $h($paiement['cancelled_at']) ?> — motif : <?= $h($paiement['cancel_reason']) ?></div>
    <?php else: ?>
    <form method="POST" action="<?= BASE_URL ?>/platform/paiements/<?= (int)$paiement['id'] ?>/annuler" class="flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4 print:hidden">
        <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
        <input type="text" name="motif" maxlength="255" placeholder="Motif d'annulation (obligatoire)" class="form-input flex-1" required>
        <button type="submit" class="btn btn-secondary">Annuler ce paiement</button>
    </form>
    <?php endif; ?>
</div>
