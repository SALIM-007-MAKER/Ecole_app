<?php
/**
 * Page publique de vérification d'authenticité d'un bulletin (accédée via
 * le QR code imprimé). Aucune authentification requise — voir
 * BulletinController::verifier() et BulletinPolicy::canVerify().
 *
 * @var bool          $trouve
 * @var ?BulletinData $bulletin
 */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vérification de bulletin</title>
<style>
body { font-family: Arial, sans-serif; background: #f1f5f9; display: flex; align-items: center;
       justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
.card { background: #fff; border-radius: 12px; padding: 28px; max-width: 420px; width: 100%;
        box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.status { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 700; margin-bottom: 16px; }
.status.ok { color: #059669; }
.status.ko { color: #dc2626; }
.row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
.row:last-child { border-bottom: none; }
.label { color: #64748b; }
.value { font-weight: 600; color: #1e293b; }
</style>
</head>
<body>
<div class="card">
    <?php if ($trouve && $bulletin): ?>
        <div class="status ok">✔ Bulletin authentique</div>
        <div class="row"><span class="label">Élève</span><span class="value"><?= e($bulletin->eleveNom . ' ' . $bulletin->elevePrenom) ?></span></div>
        <div class="row"><span class="label">Classe</span><span class="value"><?= e($bulletin->classeNom) ?></span></div>
        <div class="row"><span class="label">Période</span><span class="value"><?= e($bulletin->periodeNom) ?></span></div>
        <div class="row"><span class="label">Année scolaire</span><span class="value"><?= e($bulletin->anneeScolaire) ?></span></div>
        <div class="row"><span class="label">Moyenne</span><span class="value"><?= number_format($bulletin->moyennePeriode, 2) ?>/20</span></div>
        <div class="row"><span class="label">Statut</span><span class="value"><?= e(ucfirst($bulletin->statut)) ?></span></div>
    <?php else: ?>
        <div class="status ko">✘ Bulletin introuvable</div>
        <p style="color:#64748b; font-size: 14px;">Ce code de vérification ne correspond à aucun bulletin connu. Le document présenté pourrait être invalide ou falsifié.</p>
    <?php endif; ?>
</div>
</body>
</html>
