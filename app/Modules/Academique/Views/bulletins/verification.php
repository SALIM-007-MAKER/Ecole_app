<?php
/**
 * Page publique de vérification d'authenticité d'un bulletin (accédée via le QR code imprimé).
 * Aucune authentification requise — voir BulletinController::verifier().
 *
 * Elle n'affiche QUE le résultat de la vérification : aucun nom d'élève, classe, période, moyenne, statut ni
 * nom d'établissement. Un résultat négatif est identique pour un jeton inconnu, ancien, invalide ou un bulletin
 * non publié (aucune information sur l'existence d'un bulletin).
 *
 * @var bool $trouve   true = bulletin officiel authentique
 * @var bool $limite   true = trop de requêtes (réponse 429)
 * @var bool $indisponible true = service momentanément indisponible (configuration serveur, réponse 503)
 */
$limite = $limite ?? false;
$indisponible = $indisponible ?? false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>Vérification de bulletin</title>
<style>
body { font-family: Arial, sans-serif; background: #f1f5f9; display: flex; align-items: center;
       justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 28px; max-width: 420px; width: 100%;
        box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.status { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 700; margin-bottom: 16px; }
.status.ok { color: #059669; }
.status.ko { color: #b91c1c; }
.status.wait { color: #b45309; }
p { color: #64748b; font-size: 14px; line-height: 1.5; margin: 0; }
</style>
</head>
<body>
<div class="card">
<?php if ($indisponible): ?>
    <div class="status wait">Service momentanément indisponible</div>
    <p>La vérification n'est pas possible pour le moment. Veuillez réessayer plus tard.</p>
<?php elseif ($limite): ?>
    <div class="status wait">Trop de demandes</div>
    <p>Veuillez réessayer dans quelques instants.</p>
<?php elseif ($trouve): ?>
    <div class="status ok">✔ Authenticité confirmée</div>
    <p>Ce code correspond à un bulletin officiel émis par l'établissement. Comparez toujours le document présenté avec l'original ou demandez confirmation à l'établissement.</p>
<?php else: ?>
    <div class="status ko">Vérification impossible</div>
    <p>Ce code n'est pas reconnu. Si vous détenez un bulletin ancien, demandez une copie à l'établissement.</p>
<?php endif; ?>
</div>
</body>
</html>