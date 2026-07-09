<?php
$paiement = $paiement ?? null;
$modes    = $modes    ?? [];
$ecole    = $ecole    ?? ['nom' => 'Établissement Scolaire', 'adresse' => '', 'telephone' => ''];

if (!$paiement) { echo '<p>Paiement introuvable.</p>'; return; }
$m = $modes[$paiement->mode_paiement] ?? ['icon' => 'cash', 'label' => ucfirst($paiement->mode_paiement)];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Reçu — <?= htmlspecialchars($paiement->reference ?? '#' . $paiement->id, ENT_QUOTES) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; background: #fff; color: #111; }
  .page { width: 148mm; min-height: 105mm; margin: 10mm auto; padding: 10mm; border: 1px solid #ccc; }
  .header { text-align: center; margin-bottom: 12px; border-bottom: 2px solid #111; padding-bottom: 8px; }
  .header h1 { font-size: 14pt; }
  .header p  { font-size: 9pt; color: #555; }
  .recu-title { font-size: 13pt; font-weight: bold; text-align: center; margin: 10px 0; letter-spacing: 2px; }
  .row { display: flex; gap: 8px; margin-bottom: 6px; font-size: 10pt; }
  .label { width: 120px; color: #555; flex-shrink: 0; }
  .value { font-weight: bold; }
  .amount-box { border: 2px solid #333; text-align: center; padding: 8px 14px; margin: 12px 0; }
  .amount-box .da { font-size: 18pt; font-weight: bold; color: #198754; }
  .footer { margin-top: 16px; display: flex; justify-content: space-between; font-size: 9pt; border-top: 1px solid #ccc; padding-top: 8px; }
  .stamp { border: 2px solid #198754; border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; font-size: 7pt; color: #198754; text-align: center; font-weight: bold; }
  @media print {
    @page { margin: 0; size: A6 landscape; }
    body { margin: 0; }
    button { display: none !important; }
    .page { border: none; margin: 0; width: 100%; }
  }
</style>
</head>
<body>

<button onclick="window.print()"
        style="display:block;margin:10px auto;padding:6px 20px;background:#198754;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:10pt;">
    Imprimer
</button>

<div class="page">
    <div class="header">
        <h1><?= htmlspecialchars($ecole['nom'], ENT_QUOTES) ?></h1>
        <?php if ($ecole['adresse']): ?>
        <p><?= htmlspecialchars($ecole['adresse'], ENT_QUOTES) ?></p>
        <?php endif; ?>
        <?php if ($ecole['telephone']): ?>
        <p>Tél. : <?= htmlspecialchars($ecole['telephone'], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>

    <div class="recu-title">REÇU DE PAIEMENT</div>

    <div class="row">
        <span class="label">N° Reçu :</span>
        <span class="value font-monospace"><?= htmlspecialchars($paiement->reference ?? '#' . $paiement->id, ENT_QUOTES) ?></span>
    </div>
    <div class="row">
        <span class="label">Date :</span>
        <span class="value"><?= date('d/m/Y', strtotime($paiement->date_paiement)) ?></span>
    </div>
    <div class="row">
        <span class="label">Reçu de :</span>
        <span class="value"><?= htmlspecialchars($paiement->eleve_nom ?? '—', ENT_QUOTES) ?></span>
    </div>
    <?php if (!empty($paiement->classe_nom)): ?>
    <div class="row">
        <span class="label">Classe :</span>
        <span class="value"><?= htmlspecialchars(($paiement->classe_niveau ?? '') . ' ' . $paiement->classe_nom, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>
    <div class="row">
        <span class="label">Pour :</span>
        <span class="value"><?= htmlspecialchars($paiement->frais_nom ?? 'Paiement divers', ENT_QUOTES) ?></span>
    </div>
    <div class="row">
        <span class="label">Mode :</span>
        <span class="value"><?= htmlspecialchars($m['label'], ENT_QUOTES) ?></span>
    </div>

    <div class="amount-box">
        <div style="font-size:9pt;color:#555;margin-bottom:2px">Montant perçu</div>
        <div class="da"><?= number_format((float)$paiement->montant, 2, ',', ' ') ?> FCFA</div>
    </div>

    <?php if (!empty($paiement->observations)): ?>
    <div class="row">
        <span class="label">Observations :</span>
        <span><?= htmlspecialchars($paiement->observations, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div>
            <div style="font-size:9pt;color:#555;">Saisi par</div>
            <div style="font-weight:bold;font-size:9pt;"><?= htmlspecialchars($paiement->saisi_par_nom ?? 'Système', ENT_QUOTES) ?></div>
        </div>
        <div style="text-align:right">
            <div style="font-size:8pt;color:#555;margin-bottom:4px">Cachet et signature</div>
            <div class="stamp">REÇU</div>
        </div>
    </div>
</div>

</body>
</html>
