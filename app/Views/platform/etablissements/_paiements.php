<?php
/** @var array $etab, $historique, $paiements ['liste','situation'] */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$modes = \Core\Platform\PlatformPaymentService::MODES;
$montant = static fn($v, string $devise): string => in_array($devise, ['XOF', 'XAF'], true) ? number_format((float)$v, 0, ',', ' ') : number_format((float)$v, 2, ',', ' ');
$etatLib = ['impaye' => ['Impayé', 'badge-danger'], 'partiel' => ['Partiel', 'badge-warning'], 'solde' => ['Soldé', 'badge-success'], 'inconnu' => ['Prix inconnu', 'badge-secondary'], 'essai' => ['Essai', 'badge-secondary']];
$cycles = ['trial' => 'Essai', 'monthly' => 'Mensuel', 'yearly' => 'Annuel', 'legacy' => 'Repris'];
$situation = $paiements['situation'];
$payables = array_values(array_filter($historique, static fn(array $s): bool => $s['cycle'] !== 'trial'));
$token = \Core\Session::getCsrfToken();
?>
<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
    <p class="text-sm font-semibold text-slate-900">Paiements (saisie manuelle)</p>

    <?php $coord = \Core\Platform\PlatformSettings::valeurSiConnecte(\Core\Platform\PlatformSettings::PAIEMENT); if ($coord !== ''): ?>
    <p class="text-xs text-slate-500 whitespace-pre-line"><span class="font-semibold">Coordonnées de paiement :</span> <?= $h($coord) ?></p>
    <?php endif; ?>
    <?php if ($payables !== []): ?>
    <table class="w-full text-xs">
        <thead><tr class="text-left text-slate-400"><th>Période</th><th>Prix figé</th><th>Encaissé</th><th>Reste</th><th>Règlement</th></tr></thead>
        <tbody>
        <?php foreach ($payables as $s): $sit = $situation[(int)$s['id']] ?? ['paye' => '0.00', 'reste' => null, 'etat' => 'inconnu']; [$lib, $cls] = $etatLib[$sit['etat']] ?? ['—', 'badge-secondary']; ?>
            <tr class="border-t border-slate-100">
                <td>#<?= (int)$s['id'] ?> · <?= $h($cycles[$s['cycle']] ?? $s['cycle']) ?> · <?= $h($s['date_debut']) ?> → <?= $s['date_fin'] ? $h($s['date_fin']) : '∞' ?></td>
                <td><?= $s['prix_applique'] !== null ? $montant($s['prix_applique'], $s['devise']) . ' ' . $h($s['devise']) : '—' ?></td>
                <td><?= $montant($sit['paye'], $s['devise']) ?> <?= $h($s['devise']) ?></td>
                <td><?= $sit['reste'] !== null ? $montant($sit['reste'], $s['devise']) . ' ' . $h($s['devise']) : '—' ?></td>
                <td><span class="badge <?= $cls ?> text-xs"><?= $h($lib) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="POST" action="<?= BASE_URL ?>/platform/etablissements/<?= (int)$etab['id'] ?>/paiements" class="grid gap-3 md:grid-cols-3 text-sm items-end">
        <input type="hidden" name="_csrf_token" value="<?= $token ?>">
        <div>
            <label class="form-label" for="pay_sub">Période</label>
            <select id="pay_sub" name="subscription_id" class="form-input" required>
                <?php foreach ($payables as $s): ?><option value="<?= (int)$s['id'] ?>">#<?= (int)$s['id'] ?> · <?= $h($s['date_debut']) ?> → <?= $s['date_fin'] ? $h($s['date_fin']) : '∞' ?> (<?= $h($s['devise']) ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="form-label" for="pay_montant">Montant (devise de la période)</label><input id="pay_montant" type="text" inputmode="decimal" name="montant" class="form-input" required></div>
        <div>
            <label class="form-label" for="pay_mode">Mode</label>
            <select id="pay_mode" name="mode" class="form-input"><?php foreach ($modes as $k => $lib): ?><option value="<?= $h($k) ?>"><?= $h($lib) ?></option><?php endforeach; ?></select>
        </div>
        <div><label class="form-label" for="pay_ref">Référence de l'opération</label><input id="pay_ref" type="text" name="reference_externe" maxlength="100" class="form-input" placeholder="N° de virement / transaction"></div>
        <div><label class="form-label" for="pay_date">Date du paiement</label><input id="pay_date" type="date" name="date_paiement" class="form-input" value="<?= date('Y-m-d') ?>" required></div>
        <div><label class="form-label" for="pay_note">Note</label><input id="pay_note" type="text" name="note" maxlength="255" class="form-input"></div>
        <div class="md:col-span-3"><button type="submit" class="btn btn-primary">Enregistrer le paiement</button></div>
    </form>
    <?php else: ?>
        <p class="text-sm text-slate-500">Aucune période payante : souscrivez à un abonnement avant d'enregistrer un paiement (un essai ne se paie pas).</p>
    <?php endif; ?>

    <?php if ($paiements['liste'] !== []): ?>
    <table class="w-full text-xs">
        <thead><tr class="text-left text-slate-400"><th>Référence</th><th>Date</th><th>Montant</th><th>Mode</th><th>Opération</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($paiements['liste'] as $p): ?>
            <tr class="border-t border-slate-100 <?= $p['statut'] === 'cancelled' ? 'text-slate-400 line-through' : '' ?>">
                <td><a class="text-violet-700 hover:underline" href="<?= BASE_URL ?>/platform/paiements/<?= (int)$p['id'] ?>"><?= $h($p['reference']) ?></a></td>
                <td><?= $h($p['date_paiement']) ?></td>
                <td><?= $montant($p['montant'], $p['devise']) ?> <?= $h($p['devise']) ?></td>
                <td><?= $h($modes[$p['mode']] ?? $p['mode']) ?></td>
                <td><?= $h($p['reference_externe'] ?? '—') ?></td>
                <td><?= $p['statut'] === 'cancelled' ? 'Annulé' : 'Enregistré' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
