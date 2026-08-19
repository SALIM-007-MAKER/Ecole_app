<?php
// Usage : include avec $qrSvg (string SVG), $verificationToken (string),
// $appreciationDirecteur (?string) — texte saisi par la direction, imprimé ;
// seuls la signature et le cachet restent manuscrits après impression.
// $eleveId/$periodeId/$peutModifierAppreciation (bool) : lien de saisie,
// affiché uniquement à l'écran (masqué à l'impression, cf. .no-print-inline —
// classe distincte de .no-print, réservée à la barre d'outils fixe en haut
// de page, pour ne pas hériter de son positionnement).
$peutModifierAppreciation = $peutModifierAppreciation ?? false;
$eleveId   = $eleveId   ?? 0;
$periodeId = $periodeId ?? 0;
?>
<div class="box-title">Appreciation et visa du chef d'etablissement</div>
<?php if (!empty($appreciationDirecteur)): ?>
<div class="direction-appreciation"><?= e($appreciationDirecteur) ?></div>
<?php elseif ($peutModifierAppreciation): ?>
<div class="direction-appreciation no-print-inline" style="font-style: italic; color: #94a3b8;">Aucune appréciation saisie.</div>
<?php endif; ?>
<?php if ($peutModifierAppreciation): ?>
<div class="no-print-inline" style="margin-top:4px;">
    <a href="<?= BASE_URL ?>/v2/academique/bulletins/<?= $eleveId ?>/<?= $periodeId ?>/appreciation-directeur"
       style="font-size:11px;color:#6d28d9;text-decoration:underline;">
        <?= !empty($appreciationDirecteur) ? 'Modifier l\'appréciation' : 'Saisir l\'appréciation' ?>
    </a>
</div>
<?php endif; ?>
<div class="visa-zone">
    <?php include __DIR__ . '/qrcode-footer.php'; ?>
</div>
