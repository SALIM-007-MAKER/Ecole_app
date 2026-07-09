<?php
/**
 * Finance V2 — Impression facture (vue A4 sans layout)
 * Rendu sans sidebar/navbar
 */
$facture    = $facture    ?? null;
$lignes     = $lignes     ?? [];
$remises    = $remises    ?? [];
$echeancier = $echeancier ?? null;

$fmt = fn(float $v): string => number_format($v, 0, ',', ' ');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture <?= htmlspecialchars($facture->numero ?? '') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 12px; color: #1e293b; background: white; }
        @page { size: A4; margin: 15mm 15mm 15mm 15mm; }
        @media print { .no-print { display: none; } body { font-size: 11px; } }

        .page { width: 100%; max-width: 210mm; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
        .school-name { font-size: 20px; font-weight: 700; color: #7c3aed; }
        .school-info { font-size: 11px; color: #64748b; margin-top: 4px; }
        .invoice-header { text-align: right; }
        .invoice-number { font-size: 18px; font-weight: 700; font-family: monospace; color: #1e293b; }
        .invoice-statut { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600;
            background: #f1f5f9; color: #475569; }

        .divider { border: none; border-top: 2px solid #7c3aed; margin: 16px 0; }

        .parties { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .partie { flex: 1; }
        .partie + .partie { margin-left: 30px; }
        .partie-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 6px; }
        .partie-name { font-weight: 700; font-size: 13px; color: #1e293b; }
        .partie-info { font-size: 11px; color: #475569; margin-top: 2px; }

        .dates { display: flex; gap: 30px; margin-bottom: 24px; }
        .date-item label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #94a3b8; display: block; margin-bottom: 2px; }
        .date-item span { font-weight: 600; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        thead th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 8px 10px; text-align: left;
            font-size: 9px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.04em; }
        thead th.right { text-align: right; }
        tbody td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tbody td.right { text-align: right; font-weight: 500; }
        tbody tr:last-child td { border-bottom: none; }

        .totaux { width: 250px; margin-left: auto; }
        .totaux td { padding: 4px 10px; }
        .totaux tr.bold td { font-weight: 700; font-size: 13px; border-top: 2px solid #1e293b; }
        .totaux tr.remise td { color: #059669; }
        .totaux tr.penalite td { color: #dc2626; }
        .right { text-align: right; }

        .echeancier { margin-top: 24px; }
        .echeancier h3 { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;
            letter-spacing: 0.04em; margin-bottom: 8px; }

        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; }

        .print-btn { position: fixed; top: 20px; right: 20px; padding: 8px 16px;
            background: #7c3aed; color: white; border: none; border-radius: 8px;
            cursor: pointer; font-size: 13px; font-weight: 600; }
        .print-btn:hover { background: #6d28d9; }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">Imprimer</button>

<div class="page">

    <!-- En-tête -->
    <div class="header">
        <div>
            <div class="school-name">École / Établissement</div>
            <div class="school-info">Système de Gestion Scolaire V2</div>
        </div>
        <div class="invoice-header">
            <div class="invoice-number"><?= htmlspecialchars($facture->numero ?? '') ?></div>
            <div class="invoice-statut"><?= $facture->statut ?? '' ?></div>
        </div>
    </div>

    <hr class="divider">

    <!-- Émetteur & destinataire -->
    <div class="parties">
        <div class="partie">
            <div class="partie-label">Émetteur</div>
            <div class="partie-name">Direction Administrative</div>
            <div class="partie-info">Établissement Scolaire</div>
        </div>
        <div class="partie">
            <div class="partie-label">Destinataire</div>
            <div class="partie-name"><?= htmlspecialchars($facture->eleve_nom ?? '') ?></div>
            <div class="partie-info">
                Matricule : <?= htmlspecialchars($facture->numero_matricule ?? '—') ?><br>
                Classe : <?= htmlspecialchars($facture->classe_nom ?? '—') ?><br>
                Année scolaire : <?= htmlspecialchars($facture->annee_scolaire ?? '') ?>
            </div>
        </div>
    </div>

    <!-- Dates -->
    <div class="dates">
        <div class="date-item">
            <label>Date d'émission</label>
            <span><?= $facture->date_emission ?? '—' ?></span>
        </div>
        <?php if ($facture->date_echeance): ?>
        <div class="date-item">
            <label>Date d'échéance</label>
            <span><?= $facture->date_echeance ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Lignes -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Désignation</th>
                <th class="right">Qté</th>
                <th class="right">Prix unitaire</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lignes as $i => $l): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($l->libelle) ?></td>
                <td class="right"><?= $l->quantite ?></td>
                <td class="right"><?= $fmt((float)$l->montant_unitaire) ?> XOF</td>
                <td class="right"><?= $fmt((float)$l->montant_total) ?> XOF</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totaux -->
    <table class="totaux">
        <tr>
            <td>Sous-total</td>
            <td class="right"><?= $fmt((float)$facture->montant_ht) ?> XOF</td>
        </tr>
        <?php if ((float)$facture->montant_remise > 0): ?>
        <tr class="remise">
            <td>Remises</td>
            <td class="right">- <?= $fmt((float)$facture->montant_remise) ?> XOF</td>
        </tr>
        <?php endif; ?>
        <?php if ((float)$facture->montant_penalite > 0): ?>
        <tr class="penalite">
            <td>Pénalités</td>
            <td class="right">+ <?= $fmt((float)$facture->montant_penalite) ?> XOF</td>
        </tr>
        <?php endif; ?>
        <tr class="bold">
            <td>TOTAL À PAYER</td>
            <td class="right"><?= $fmt((float)$facture->montant_total) ?> XOF</td>
        </tr>
    </table>

    <!-- Remises (détail) -->
    <?php if (!empty($remises)): ?>
    <div style="margin-top:20px;">
        <h3 style="font-size:10px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:6px;">
            Détail des remises
        </h3>
        <table>
            <tbody>
                <?php foreach ($remises as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r->libelle) ?></td>
                    <td class="right" style="color:#059669;">- <?= $fmt((float)$r->montant_calcule) ?> XOF</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Échéancier -->
    <?php if ($echeancier && !empty($echeancier->echeances)): ?>
    <div class="echeancier">
        <h3>Plan de paiement — <?= $echeancier->nb_echeances ?> versement(s)</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date d'échéance</th>
                    <th class="right">Montant dû</th>
                    <th class="right">Payé</th>
                    <th class="right">Reste</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($echeancier->echeances as $ech): ?>
                <tr>
                    <td><?= $ech->numero_ordre ?></td>
                    <td><?= $ech->date_echeance ?></td>
                    <td class="right"><?= $fmt((float)$ech->montant_du) ?> XOF</td>
                    <td class="right"><?= $fmt((float)$ech->montant_paye) ?> XOF</td>
                    <td class="right"><?= $fmt(max(0, (float)$ech->montant_du - (float)$ech->montant_paye)) ?> XOF</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Note -->
    <?php if ($facture->note): ?>
    <div style="margin-top:20px;padding:10px;background:#f8fafc;border-radius:6px;border-left:3px solid #7c3aed;">
        <strong style="font-size:10px;color:#7c3aed;">NOTE :</strong>
        <span style="font-size:11px;color:#475569;"><?= htmlspecialchars($facture->note) ?></span>
    </div>
    <?php endif; ?>

    <!-- Pied de page -->
    <div class="footer">
        <span>Facture générée le <?= date('d/m/Y à H:i') ?></span>
        <span><?= htmlspecialchars($facture->numero ?? '') ?> — Page 1/1</span>
    </div>
</div>

</body>
</html>
