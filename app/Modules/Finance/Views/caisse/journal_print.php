<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Journal de caisse — <?= htmlspecialchars($session->numero ?? '') ?></title>
<style>
@page { size: A4; margin: 15mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #1e293b; }
h1 { font-size: 16pt; font-weight: bold; color: #4c1d95; }
h2 { font-size: 11pt; font-weight: bold; color: #374151; margin-bottom: 6px; }
.header { border-bottom: 3px solid #6d28d9; padding-bottom: 10px; margin-bottom: 12px; }
.header-row { display: flex; justify-content: space-between; align-items: flex-start; }
.meta { font-size: 9pt; color: #64748b; margin-top: 4px; }
.section { margin-bottom: 14px; }
.summary-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.summary-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; }
.summary-label { font-size: 8pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
.summary-value { font-size: 12pt; font-weight: bold; }
.emerald { color: #059669; } .rose { color: #e11d48; } .violet { color: #6d28d9; } .slate { color: #475569; }
table { width: 100%; border-collapse: collapse; font-size: 9pt; }
thead { background: #f1f5f9; }
th { padding: 6px 8px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
th.right, td.right { text-align: right; }
td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tr.annule td { color: #94a3b8; text-decoration: line-through; }
tr.credit td.amount { color: #059669; font-weight: bold; }
tr.debit  td.amount { color: #e11d48; font-weight: bold; }
tfoot tr td { font-weight: bold; background: #f8fafc; border-top: 2px solid #e2e8f0; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 8pt; font-weight: 600; }
.badge-recette { background: #d1fae5; color: #065f46; }
.badge-decaissement { background: #ffe4e6; color: #9f1239; }
.badge-correction { background: #fef3c7; color: #92400e; }
.badge-ouverture { background: #dbeafe; color: #1e40af; }
.badge-fermeture { background: #f3e8ff; color: #6b21a8; }
.badge-annulation { background: #f1f5f9; color: #64748b; }
.footer { margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 9pt; color: #94a3b8; display: flex; justify-content: space-between; }
.stamp { border: 2px dashed <?= ($session->statut ?? '') === 'fermee' ? '#059669' : '#e2e8f0' ?>; border-radius: 6px; padding: 6px 14px; text-align: center; font-size: 9pt; color: <?= ($session->statut ?? '') === 'fermee' ? '#065f46' : '#94a3b8' ?>; font-weight: bold; }
@media print { .no-print { display: none !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<?php
$session    = $session    ?? null;
$mouvements = $mouvements ?? [];
$totaux     = $totaux     ?? null;
$journal    = $journal    ?? null;
$types      = $types      ?? [];
$fmtMontant = fn(float $v): string => number_format($v, 0, ',', ' ') . ' XOF';
$soldeInitial   = (float)($session->solde_initial ?? 0);
$totalCredits   = (float)($totaux->total_credits ?? 0);
$totalDebits    = (float)($totaux->total_debits ?? 0);
$soldeTheorique = (float)($session->solde_theorique ?? round($soldeInitial + $totalCredits - $totalDebits, 2));
?>

<div class="no-print" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:10px;text-align:center;">
    <button onclick="window.print()" style="padding:7px 18px;background:#6d28d9;color:white;border:none;border-radius:6px;cursor:pointer;margin-right:8px;">🖨️ Imprimer</button>
    <button onclick="window.close()" style="padding:7px 14px;background:#e2e8f0;color:#475569;border:none;border-radius:6px;cursor:pointer;">Fermer</button>
</div>

<div style="padding: 0;">
<div class="header">
    <div class="header-row">
        <div>
            <h1>JOURNAL DE CAISSE</h1>
            <div class="meta">Session : <strong><?= htmlspecialchars($session->numero ?? '—') ?></strong></div>
            <div class="meta">Caissier : <strong><?= htmlspecialchars($session->caissier_nom ?? '—') ?></strong></div>
        </div>
        <div style="text-align:right;">
            <div class="meta">Ouverture : <strong><?= date('d/m/Y H:i', strtotime(($session->date_ouverture ?? '') . ' ' . ($session->heure_ouverture ?? ''))) ?></strong></div>
            <?php if ($session->date_fermeture): ?>
            <div class="meta">Fermeture : <strong><?= date('d/m/Y H:i', strtotime($session->date_fermeture . ' ' . ($session->heure_fermeture ?? ''))) ?></strong></div>
            <?php endif; ?>
            <div class="meta">Imprimé le : <?= date('d/m/Y à H:i') ?></div>
        </div>
    </div>
</div>

<div class="summary-grid">
    <div class="summary-card">
        <div class="summary-label">Solde initial</div>
        <div class="summary-value slate"><?= $fmtMontant($soldeInitial) ?></div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Total recettes</div>
        <div class="summary-value emerald"><?= $fmtMontant((float)($session->total_recettes ?? 0)) ?></div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Total décaissements</div>
        <div class="summary-value rose"><?= $fmtMontant((float)($session->total_decaissements ?? 0)) ?></div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Solde théorique</div>
        <div class="summary-value violet"><?= $fmtMontant($soldeTheorique) ?></div>
    </div>
</div>

<?php if ($session->statut === 'fermee' && $session->solde_reel !== null): ?>
<div style="display:flex;gap:12px;margin-bottom:12px;">
    <div class="summary-card" style="flex:1;">
        <div class="summary-label">Solde réel compté</div>
        <div class="summary-value slate"><?= $fmtMontant((float)$session->solde_reel) ?></div>
    </div>
    <div class="summary-card" style="flex:1;">
        <div class="summary-label">Écart</div>
        <?php $ecart = (float)$session->ecart; ?>
        <div class="summary-value <?= $ecart < 0 ? 'rose' : ($ecart > 0 ? '' : 'emerald') ?>"
             style="<?= $ecart > 0 ? 'color:#d97706;' : '' ?>">
            <?= ($ecart >= 0 ? '+' : '') . $fmtMontant(abs($ecart)) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="section">
    <h2>Détail des mouvements</h2>
    <table>
        <thead>
            <tr>
                <th>Heure</th>
                <th>Type</th>
                <th>Libellé</th>
                <th>Référence</th>
                <th class="right">Entrée (+)</th>
                <th class="right">Sortie (−)</th>
                <th>Source</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $runningBalance = $soldeInitial;
        foreach ($mouvements as $m):
            $annule = $m->statut === 'annule';
            $trClass = $annule ? 'annule' : ($m->sens === 'credit' ? 'credit' : 'debit');
        ?>
        <tr class="<?= $trClass ?>">
            <td><?= date('H:i', strtotime($m->created_at)) ?></td>
            <td>
                <span class="badge badge-<?= $m->type ?>"><?= $types[$m->type] ?? $m->type ?></span>
            </td>
            <td><?= htmlspecialchars($m->libelle) ?></td>
            <td><?= htmlspecialchars($m->reference ?? '—') ?></td>
            <td class="right amount"><?= (!$annule && $m->sens === 'credit') ? $fmtMontant((float)$m->montant) : '—' ?></td>
            <td class="right amount"><?= (!$annule && $m->sens === 'debit') ? $fmtMontant((float)$m->montant) : '—' ?></td>
            <td><?= $m->source ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">TOTAUX</td>
                <td class="right emerald"><?= $fmtMontant((float)($session->total_recettes ?? 0)) ?></td>
                <td class="right rose"><?= $fmtMontant((float)($session->total_decaissements ?? 0)) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="footer">
    <div>
        <div>Journal : <?= $journal ? ucfirst($journal->statut) : '—' ?></div>
        <?php if ($session->note_fermeture): ?>
        <div>Note : <?= htmlspecialchars($session->note_fermeture) ?></div>
        <?php endif; ?>
    </div>
    <div class="stamp">
        <?= match ($session->statut ?? '') {
            'fermee'      => '✓ CAISSE FERMÉE',
            'en_activite' => '⚡ EN ACTIVITÉ',
            'ouverte'     => '⚡ OUVERTE',
            default       => $session->statut ?? '',
        } ?>
    </div>
</div>
</div>
</body>
</html>
