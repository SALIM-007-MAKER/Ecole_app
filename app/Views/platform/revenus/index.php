<?php
/** @var bool $actif; @var array $periode; @var ?array $synthese; @var array $paiements, $modes */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$montant = static fn($v, string $d): string => in_array($d, ['XOF', 'XAF'], true) ? number_format((float)$v, 0, ',', ' ') : number_format((float)$v, 2, ',', ' ');
$query = http_build_query(['du' => $periode['du'], 'au' => $periode['au']]);
?>
<div class="flex items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i data-lucide="coins" class="w-5 h-5 text-violet-600"></i>Revenus</h2>
        <p class="text-sm text-slate-500">Encaissements manuels enregistrés. Les totaux sont toujours donnés par devise : aucune conversion n'est faite.</p>
    </div>
    <?php if ($actif): ?><a href="<?= BASE_URL ?>/platform/revenus/export?<?= $h($query) ?>" class="btn btn-secondary"><i data-lucide="download" class="w-4 h-4"></i>Exporter en CSV</a><?php endif; ?>
</div>

<?php if (!$actif): ?>
<p class="text-sm text-slate-500">Le suivi des paiements n'est pas encore activé (migration T070 à appliquer).</p>
<?php else: ?>
<form method="GET" action="<?= BASE_URL ?>/platform/revenus" class="rounded-xl border border-slate-200 bg-white shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3">
    <div><label class="form-label" for="du">Du</label><input type="date" id="du" name="du" class="form-input" value="<?= $h($periode['du']) ?>"></div>
    <div><label class="form-label" for="au">Au</label><input type="date" id="au" name="au" class="form-input" value="<?= $h($periode['au']) ?>"></div>
    <button type="submit" class="btn btn-primary">Filtrer</button>
</form>

<?php if ($synthese['totaux'] === []): ?>
<p class="text-sm text-slate-500 mb-6">Aucun encaissement sur cette période.</p>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <?php foreach ($synthese['totaux'] as $devise => $t): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <p class="text-xs text-slate-400 mb-1">Encaissé (<?= $h($devise) ?>)</p>
        <p class="text-2xl font-bold text-violet-700"><?= $montant($t['total'], $devise) ?> <span class="text-sm font-normal text-slate-400"><?= $h($devise) ?></span></p>
        <p class="text-xs text-slate-500 mt-1"><?= (int)$t['nb'] ?> paiement(s)<?php if (isset($synthese['annules'][$devise])): ?> · <?= (int)$synthese['annules'][$devise]['nb'] ?> annulé(s) non comptés<?php endif; ?></p>
    </div>
    <?php endforeach; ?>
    <?php foreach ($synthese['a_encaisser'] as $devise => $reste): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 shadow-sm p-5">
        <p class="text-xs text-amber-700 mb-1">Reste à encaisser — périodes en cours (<?= $h($devise) ?>)</p>
        <p class="text-2xl font-bold text-amber-800"><?= $montant($reste, $devise) ?> <span class="text-sm font-normal"><?= $h($devise) ?></span></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8 text-sm">
    <?php foreach (['mensuel' => ['Par mois', null], 'par_mode' => ['Par mode de paiement', $modes], 'par_plan' => ['Par plan', null]] as $cle => [$titre, $libs]): ?>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
        <p class="font-semibold text-slate-900 mb-3"><?= $h($titre) ?></p>
        <?php if ($synthese[$cle] === []): ?><p class="text-slate-400 text-xs">—</p><?php endif; ?>
        <?php foreach ($synthese[$cle] as $devise => $lignes): ?>
            <p class="text-xs text-slate-400 mt-2"><?= $h($devise) ?></p>
            <ul class="space-y-1">
            <?php foreach ($lignes as $k => $v): ?><li class="flex justify-between"><span class="text-slate-600"><?= $h($libs[$k] ?? $k) ?></span><span class="text-slate-800"><?= $montant($v, $devise) ?></span></li><?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
    <p class="text-sm font-semibold text-slate-900 mb-3">Paiements de la période</p>
    <?php if ($paiements === []): ?><p class="text-sm text-slate-500">Aucun paiement.</p><?php else: ?>
    <table class="w-full text-xs">
        <thead><tr class="text-left text-slate-400"><th>Référence</th><th>Date</th><th>Établissement</th><th>Plan</th><th>Montant</th><th>Mode</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($paiements as $p): ?>
            <tr class="border-t border-slate-100 <?= $p['statut'] === 'cancelled' ? 'text-slate-400 line-through' : '' ?>">
                <td><a class="text-violet-700 hover:underline" href="<?= BASE_URL ?>/platform/paiements/<?= (int)$p['id'] ?>"><?= $h($p['reference']) ?></a></td>
                <td><?= $h($p['date_paiement']) ?></td>
                <td><a class="hover:underline" href="<?= BASE_URL ?>/platform/etablissements/<?= (int)$p['etablissement_id'] ?>"><?= $h($p['etablissement_nom']) ?></a></td>
                <td><?= $h($p['plan_nom']) ?></td>
                <td><?= $montant($p['montant'], $p['devise']) ?> <?= $h($p['devise']) ?></td>
                <td><?= $h($modes[$p['mode']] ?? $p['mode']) ?></td>
                <td><?= $p['statut'] === 'cancelled' ? 'Annulé' : 'Enregistré' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>
