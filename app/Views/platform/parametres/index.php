<?php
/** @var bool $actif; @var array $reglages; @var array $old */
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$sources = ['base' => ['Surcharge enregistrée', 'badge-success'], 'environnement' => ['Fichier .env', 'badge-secondary'], 'defaut' => ['Valeur par défaut', 'badge-secondary']];
?>
<div class="mb-6">
    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2"><i data-lucide="sliders-horizontal" class="w-5 h-5 text-violet-600"></i>Paramètres de la plateforme</h2>
    <p class="text-sm text-slate-500">Priorité : surcharge enregistrée ici, puis fichier <code>.env</code>, puis valeur par défaut. Laissez un champ vide pour supprimer sa surcharge. Chaque changement est inscrit au journal d'audit.</p>
</div>

<?php if (!$actif): ?>
<p class="text-sm text-slate-500">Les paramètres globaux ne sont pas encore activés (migration T071 à appliquer). Les valeurs ci-dessous sont celles du <code>.env</code> et des défauts.</p>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/platform/parametres" class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-5 max-w-3xl">
    <input type="hidden" name="_csrf_token" value="<?= \Core\Session::getCsrfToken() ?>">
    <?php foreach ($reglages as $cle => $r):
        $valeur = array_key_exists($cle, $old) ? (string)$old[$cle] : (string)($r['surcharge'] ?? '');
        [$libSource, $clsSource] = $sources[$r['source']] ?? ['—', 'badge-secondary']; ?>
    <div>
        <div class="flex items-center justify-between gap-2">
            <label class="form-label" for="p_<?= $h($cle) ?>"><?= $h($r['libelle']) ?></label>
            <span class="badge <?= $clsSource ?> text-xs">Actuellement : <?= $r['effective'] !== '' ? $h(mb_strimwidth($r['effective'], 0, 40, '…')) : '—' ?> · <?= $h($libSource) ?></span>
        </div>
        <?php if (str_starts_with($r['type'], 'texte:')): ?>
            <textarea id="p_<?= $h($cle) ?>" name="<?= $h($cle) ?>" rows="3" maxlength="<?= (int)substr($r['type'], 6) ?>" class="form-input" <?= $actif ? '' : 'disabled' ?>><?= $h($valeur) ?></textarea>
        <?php elseif (str_starts_with($r['type'], 'entier:')): [, $min, $max] = explode(':', $r['type']); ?>
            <input type="number" id="p_<?= $h($cle) ?>" name="<?= $h($cle) ?>" min="<?= (int)$min ?>" max="<?= (int)$max ?>" value="<?= $h($valeur) ?>" placeholder="<?= $h($r['defaut']) ?>" class="form-input" <?= $actif ? '' : 'disabled' ?>>
        <?php else: ?>
            <input type="text" id="p_<?= $h($cle) ?>" name="<?= $h($cle) ?>" value="<?= $h($valeur) ?>" placeholder="<?= $h($r['defaut']) ?>" maxlength="64" class="form-input <?= $r['type'] === 'devise' ? 'font-mono uppercase' : '' ?>" <?= $actif ? '' : 'disabled' ?>>
        <?php endif; ?>
        <p class="text-xs text-slate-500 mt-1"><?= $h($r['aide']) ?></p>
        <?php if ($r['modifie_le']): ?><p class="text-xs text-slate-400">Dernière modification : <?= $h($r['modifie_le']) ?> par <?= $h($r['modifie_par'] ?? '—') ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if ($actif): ?><button type="submit" class="btn btn-primary">Enregistrer</button><?php endif; ?>
</form>
