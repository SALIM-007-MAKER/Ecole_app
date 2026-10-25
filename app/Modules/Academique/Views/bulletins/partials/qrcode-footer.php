<?php
// Usage : include avec $qrSvg (string SVG autonome, Core\QrCode\QrEncoder).
// Le QR de vérification n'existe QUE pour un bulletin officiel (publié/archivé, jeton valide) :
// pour un brouillon, $qrSvg est vide et aucun bloc n'est rendu.
if (!empty($qrSvg)): ?>
<div class="qr-block">
    <?= $qrSvg ?>
</div>
<?php endif; ?>