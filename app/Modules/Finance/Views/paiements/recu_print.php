<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reçu <?= htmlspecialchars($recu->numero ?? '') ?></title>
<style>
@page { size: A5; margin: 12mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #1e293b; }

.receipt { border: 2px solid #6d28d9; border-radius: 8px; overflow: hidden; max-width: 148mm; margin: 0 auto; }
.receipt-header { background: #6d28d9; color: white; padding: 14px 20px; }
.receipt-header .title { font-size: 14pt; font-weight: bold; letter-spacing: 0.5px; }
.receipt-header .numero { font-size: 9pt; opacity: 0.8; margin-top: 2px; }
.receipt-header .date { font-size: 9pt; text-align: right; }

.receipt-body { padding: 16px 20px; }

.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
.label { font-size: 8pt; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.value { font-size: 10pt; font-weight: bold; color: #1e293b; }
.sub-value { font-size: 9pt; color: #64748b; }

.amount-zone { text-align: center; padding: 14px; border-top: 1px dashed #e2e8f0; border-bottom: 1px dashed #e2e8f0; margin: 14px 0; }
.amount-label { font-size: 9pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
.amount-value { font-size: 22pt; font-weight: 900; color: #6d28d9; margin: 4px 0; }
.amount-details { font-size: 9pt; color: #64748b; }

.footer-zone { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 14px; }
.stamp { border: 2px dashed #10b981; border-radius: 8px; padding: 8px 14px; text-align: center; color: #065f46; }
.stamp-text { font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }

.separator { border: none; border-top: 1px solid #e2e8f0; margin: 10px 0; }

@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
    <button onclick="window.print()" style="padding:8px 20px;background:#6d28d9;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12pt;margin-right:8px;">
        🖨️ Imprimer
    </button>
    <button onclick="window.close()" style="padding:8px 16px;background:#e2e8f0;color:#475569;border:none;border-radius:6px;cursor:pointer;font-size:12pt;">
        Fermer
    </button>
</div>

<?php
$recu     = $recu ?? null;
if (!$recu) { echo '<p style="padding:20px;color:red;">Reçu introuvable.</p>'; exit; }
$branding = $branding ?? \Core\Tenant\BrandingService::forCurrentRequest();
$devise   = $devise   ?? 'XOF';
$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' ' . $devise;
?>

<div style="padding: 10mm;" class="no-print-padding">
<div class="receipt">

    <div class="receipt-header">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <div class="title">REÇU DE PAIEMENT</div>
                <div class="numero">N° <?= htmlspecialchars($recu->numero) ?></div>
            </div>
            <div class="date">
                Émis le<br><strong><?= $recu->date_emission ? date('d/m/Y', strtotime($recu->date_emission)) : '—' ?></strong>
            </div>
        </div>
    </div>

    <div class="receipt-body">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <?php if ($branding->logoUrl): ?>
            <img src="<?= htmlspecialchars($branding->logoUrl, ENT_QUOTES) ?>" alt="" style="height:28px">
            <?php endif; ?>
            <div>
                <div style="font-weight:bold;font-size:11pt;color:#1e293b;"><?= htmlspecialchars($branding->appName, ENT_QUOTES) ?></div>
                <?php if ($branding->contactAddress): ?>
                <div style="font-size:8pt;color:#64748b;"><?= htmlspecialchars($branding->contactAddress, ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <hr class="separator">

        <div class="grid2">
            <div>
                <div class="label">Bénéficiaire</div>
                <div class="value"><?= htmlspecialchars($recu->eleve_nom ?? '—') ?></div>
                <div class="sub-value">Matricule : <?= htmlspecialchars($recu->matricule ?? '—') ?></div>
            </div>
            <div>
                <div class="label">Référence facture</div>
                <div class="value"><?= htmlspecialchars($recu->facture_numero ?? '—') ?></div>
                <div class="sub-value">Année : <?= htmlspecialchars($recu->annee_scolaire ?? '—') ?></div>
            </div>
        </div>

        <hr class="separator">

        <div class="amount-zone">
            <div class="amount-label">Montant encaissé</div>
            <div class="amount-value"><?= $fmtMontant((float)($recu->montant ?? 0)) ?></div>
            <div class="amount-details">
                Mode : <strong><?= htmlspecialchars($recu->mode_nom ?? $recu->mode_code ?? '—') ?></strong>
                &nbsp;·&nbsp;
                Date : <strong><?= isset($recu->date_paiement) ? date('d/m/Y', strtotime($recu->date_paiement)) : '—' ?></strong>
            </div>
        </div>

        <hr class="separator">

        <div class="footer-zone">
            <div>
                <div class="label">Encaissé par</div>
                <div class="value" style="font-size:10pt;">
                    <?= isset($recu->emetteur_prenom) ? htmlspecialchars($recu->emetteur_prenom . ' ' . $recu->emetteur_nom) : '—' ?>
                </div>
                <div style="margin-top:18px;border-top:1px solid #94a3b8;width:80px;"></div>
                <div style="font-size:8pt;color:#94a3b8;margin-top:3px;">Signature</div>
            </div>
            <div class="stamp">
                <div style="font-size:16pt;">✓</div>
                <div class="stamp-text">Payé</div>
            </div>
        </div>

    </div>
</div>
</div>

<script>
// Auto-print si ouvert depuis le bouton print
if (window.name === 'printWindow') {
    window.onload = function() { window.print(); };
}
</script>
</body>
</html>
