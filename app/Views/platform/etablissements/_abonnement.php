<?php
/** @var array $etab, $historique, $plans; @var ?array $abonnement; @var bool $canSubscribe */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$libelle = ['trial' => 'Essai', 'active' => 'Actif', 'grace' => 'Période de grâce', 'expired' => 'Expiré', 'cancelled' => 'Annulé', 'superseded' => 'Remplacé'];
$cycles = ['trial' => 'Essai', 'monthly' => 'Mensuel', 'yearly' => 'Annuel', 'legacy' => 'Repris de l\'existant'];
$ligne = $abonnement['ligne'] ?? null;
$etat = $abonnement['etat'] ?? null;
$suspAuto = ($etab['statut'] ?? '') === 'suspended' && ($etab['suspension_origine'] ?? null) === 'subscription';
$suspManuelle = ($etab['statut'] ?? '') === 'suspended' && !$suspAuto;
$token = \Core\Session::getCsrfToken();
$base = BASE_URL . '/platform/etablissements/' . (int)$etab['id'] . '/abonnement';
?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
    <p class="text-sm font-semibold text-slate-900">Abonnement</p>
    <?php if ($ligne === null): ?>
        <p class="text-sm text-slate-500">Aucun abonnement. Assignez un plan pour démarrer l'essai ou l'abonnement.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-slate-400 text-xs">État</p><p class="text-slate-800"><?= $h($libelle[$etat['etat']] ?? $etat['etat']) ?> <?= $etat['acces'] ? '' : '— accès coupé' ?></p></div>
            <div><p class="text-slate-400 text-xs">Cycle</p><p class="text-slate-800"><?= $h($cycles[$ligne['cycle']] ?? $ligne['cycle']) ?></p></div>
            <div><p class="text-slate-400 text-xs">Période</p><p class="text-slate-800"><?= $h($ligne['date_debut']) ?> → <?= $ligne['date_fin'] ? $h($ligne['date_fin']) : 'sans échéance' ?></p></div>
            <div><p class="text-slate-400 text-xs">Accès jusqu'au</p><p class="text-slate-800"><?= $etat['acces_jusqua'] ? $h($etat['acces_jusqua']) : '—' ?><?= $etat['jours_restants'] !== null ? ' (' . (int)$etat['jours_restants'] . ' j)' : '' ?></p></div>
            <div><p class="text-slate-400 text-xs">Conditions figées</p><p class="text-slate-800"><?= $ligne['prix_applique'] !== null ? $h($ligne['prix_applique']) . ' ' . $h($ligne['devise']) : 'inconnues (' . $h($ligne['devise']) . ')' ?>, grâce <?= (int)$ligne['jours_grace_appliques'] ?> j</p></div>
            <div><p class="text-slate-400 text-xs">Renouvellement automatique</p><p class="text-slate-800">Inactif (les paiements sont saisis manuellement, ils ne renouvellent pas l'abonnement)</p></div>
        </div>
        <?php if ($etat['en_grace']): ?><p class="text-sm text-amber-700">Période de grâce : l'accès est maintenu jusqu'au <?= $h($etat['acces_jusqua']) ?>.</p><?php endif; ?>
        <?php if ($suspAuto): ?><p class="text-sm text-red-700">Suspendu automatiquement pour expiration d'abonnement (levée automatique au renouvellement).</p><?php endif; ?>
        <?php if ($suspManuelle): ?><p class="text-sm text-red-700">Suspension décidée par un opérateur : elle reste en vigueur même si l'abonnement est valide.</p><?php endif; ?>
    <?php endif; ?>

    <?php if ($canSubscribe): ?>
    <div class="grid gap-3 md:grid-cols-2 text-sm">
        <form method="POST" action="<?= $base ?>/souscrire" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <select name="plan_id" class="form-input"><?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$etab['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= $h($p['nom']) ?> (<?= $h($p['devise']) ?>)</option><?php endforeach; ?></select>
            <select name="cycle" class="form-input"><option value="monthly">Mensuel</option><option value="yearly">Annuel</option></select>
            <button type="submit" class="btn btn-secondary">Souscrire / réactiver</button>
        </form>
        <form method="POST" action="<?= $base ?>/renouveler" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <select name="cycle" class="form-input"><option value="">Même cycle</option><option value="monthly">Mensuel</option><option value="yearly">Annuel</option></select>
            <label class="text-xs text-slate-600"><input type="checkbox" name="conserver_conditions" value="1"> conserver les conditions précédentes</label>
            <button type="submit" class="btn btn-secondary">Renouveler</button>
        </form>
        <form method="POST" action="<?= $base ?>/prolonger-essai" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <input type="number" name="jours" min="1" max="90" placeholder="jours" class="form-input w-24" required>
            <input type="text" name="motif" placeholder="Motif (obligatoire)" class="form-input" required>
            <button type="submit" class="btn btn-secondary">Prolonger l'essai</button>
        </form>
        <form method="POST" action="<?= $base ?>/annuler" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="_csrf_token" value="<?= $token ?>">
            <input type="text" name="motif" placeholder="Motif (obligatoire)" class="form-input" required>
            <button type="submit" class="btn btn-secondary">Annuler l'abonnement</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($historique): ?>
    <table class="w-full text-xs">
        <thead><tr class="text-left text-slate-400"><th>#</th><th>Plan</th><th>Statut</th><th>Cycle</th><th>Période</th><th>Prix figé</th></tr></thead>
        <tbody>
        <?php foreach ($historique as $s): ?>
            <tr class="border-t border-slate-100"><td><?= (int)$s['id'] ?></td><td><?= $h($s['plan_nom']) ?></td><td><?= $h($libelle[$s['statut']] ?? $s['statut']) ?></td><td><?= $h($cycles[$s['cycle']] ?? $s['cycle']) ?></td>
            <td><?= $h($s['date_debut']) ?> → <?= $s['date_fin'] ? $h($s['date_fin']) : '∞' ?></td><td><?= $s['prix_applique'] !== null ? $h($s['prix_applique']) . ' ' . $h($s['devise']) : '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
