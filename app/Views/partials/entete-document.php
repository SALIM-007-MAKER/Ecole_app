<?php
/**
 * En-tête commun des documents imprimés d'un établissement : logo PROPRE de l'école + identité (nom, adresse, contacts).
 * Le logo EduNova n'apparaît jamais ici (il reste celui de la plateforme). Sans logo propre : nom et coordonnées seuls.
 *
 * Usage : <?php include ROOT_PATH . '/app/Views/partials/entete-document.php'; ?>
 * Variables facultatives : $identite (tableau retourné par Core\Tenant\IdentiteDocument), $enteteTitre (titre du document).
 * Mise en forme en ligne : utilisable dans n'importe quel gabarit d'impression, sans dépendre d'une feuille de style.
 */
$__id = $identite ?? \Core\Tenant\IdentiteDocument::courante();
$__h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="entete-document" style="display:flex;align-items:center;gap:14px;padding-bottom:8px;margin-bottom:12px;border-bottom:2px solid #1e293b;color:#0f172a">
    <?php if (!empty($__id['logo_src'])): ?>
    <img src="<?= $__h($__id['logo_src']) ?>" alt="Logo <?= $__h($__id['nom']) ?>" style="height:64px;max-width:120px;object-fit:contain">
    <?php endif; ?>
    <div style="flex:1;min-width:0">
        <div style="font-weight:700;font-size:14pt;line-height:1.2"><?= $__h($__id['nom']) ?></div>
        <?php if (!empty($__id['adresse'])): ?><div style="font-size:9pt;color:#475569"><?= $__h($__id['adresse']) ?></div><?php endif; ?>
        <?php if (!empty($__id['telephone']) || !empty($__id['email'])): ?>
        <div style="font-size:9pt;color:#475569">
            <?= !empty($__id['telephone']) ? 'Tél. : ' . $__h($__id['telephone']) : '' ?>
            <?= !empty($__id['telephone']) && !empty($__id['email']) ? ' · ' : '' ?>
            <?= !empty($__id['email']) ? $__h($__id['email']) : '' ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($enteteTitre)): ?>
    <div style="text-align:right;font-weight:700;font-size:12pt"><?= $__h($enteteTitre) ?></div>
    <?php endif; ?>
</div>
