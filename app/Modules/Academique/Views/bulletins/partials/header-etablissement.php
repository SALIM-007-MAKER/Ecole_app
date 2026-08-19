<?php
// Usage : include avec $etablissement = {nom, adresse, telephone, email, logo}
$logoUrl = $etablissement['logo'] ?? '';
if ($logoUrl !== '' && !preg_match('#^(https?:)?//#i', $logoUrl)) {
    $logoUrl = BASE_URL . $logoUrl; // chemin racine-relatif (ex: /assets/img/logo.png)
}
$logoHtml = $logoUrl !== ''
    ? '<img src="' . e($logoUrl) . '" alt="logo">'
    : '<span class="logo-placeholder">Logo</span>';
?>
<table class="entete">
    <tr>
        <td class="logo-cell"><?= $logoHtml ?></td>
        <td>
            <div class="h-ministere">Ministère de l'Education Nationale</div>
            <div class="h-dren">D.R.E.N NIAMEY / D.D.E.N NIAMEY III</div>
            <div class="h-etab">Complexe Scolaire Privé « <?= e(mb_strtoupper($etablissement['nom'], 'UTF-8')) ?> »</div>
            <?php if (!empty($etablissement['telephone'])): ?>
                <div class="h-contact">Tel : <?= e($etablissement['telephone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($etablissement['email'])): ?>
                <div class="h-contact">Email : <?= e($etablissement['email']) ?></div>
            <?php endif; ?>
        </td>
        <td class="logo-cell"><?= $logoHtml ?></td>
    </tr>
</table>
