<?php
$anneeNum  = $anneeNum  ?? (int)date('Y');
$mois      = $mois      ?? 0;
$type      = $type      ?? 'annuel';
$paiements = $paiements ?? [];
$depenses  = $depenses  ?? [];
$catsDep   = $catsDep   ?? [];
$totRec    = $totRec    ?? 0;
$totDep    = $totDep    ?? 0;
$solde     = $solde     ?? 0;
$parPeriode= $parPeriode?? [];

function fmtRPP(float $n): string {
    return number_format($n, 2, ',', ' ') . ' FCFA';
}
$moisLabels = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$periodeLabel = $mois ? $moisLabels[$mois] . ' ' . $anneeNum : 'Année ' . $anneeNum;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport financier — <?= $periodeLabel ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #111; background: #fff; padding: 20mm; }
  h1 { font-size: 18pt; margin-bottom: 4px; }
  .subtitle { color: #555; font-size: 10pt; margin-bottom: 20px; }
  .kpi-row { display: flex; gap: 15px; margin-bottom: 20px; }
  .kpi { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 12px; text-align: center; }
  .kpi .label { font-size: 9pt; color: #666; margin-bottom: 4px; }
  .kpi .val { font-size: 15pt; font-weight: bold; }
  .kpi .val.green { color: #198754; }
  .kpi .val.red   { color: #dc3545; }
  .kpi .val.blue  { color: #0d6efd; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9.5pt; }
  th { background: #333; color: #fff; padding: 6px 8px; text-align: left; }
  td { padding: 5px 8px; border-bottom: 1px solid #eee; }
  tr:nth-child(even) td { background: #f9f9f9; }
  tfoot td { background: #f0f0f0 !important; font-weight: bold; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .section-title { font-size: 12pt; font-weight: bold; margin: 20px 0 8px; border-left: 4px solid #0d6efd; padding-left: 8px; }
  .footer { margin-top: 30px; font-size: 8pt; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 10px; }
  .green-text { color: #198754; }
  .red-text   { color: #dc3545; }
  @media print {
    @page { margin: 15mm; }
    body { padding: 0; }
    button { display: none !important; }
  }
</style>
</head>
<body>
<button onclick="window.print()" style="margin-bottom:15px;padding:6px 18px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:10pt;">
    Imprimer / Enregistrer PDF
</button>

<h1>Rapport financier — <?= $periodeLabel ?></h1>
<div class="subtitle">
    Généré le <?= date('d/m/Y à H:i') ?> — <?= $type === 'mensuel' ? 'Rapport mensuel' : 'Rapport annuel' ?>
</div>

<div class="kpi-row">
    <div class="kpi">
        <div class="label">Total recettes</div>
        <div class="val green"><?= fmtRPP((float)$totRec) ?></div>
        <div style="font-size:8pt;color:#888"><?= count($paiements) ?> paiement(s)</div>
    </div>
    <div class="kpi">
        <div class="label">Total dépenses</div>
        <div class="val red"><?= fmtRPP((float)$totDep) ?></div>
        <div style="font-size:8pt;color:#888"><?= count($depenses) ?> dépense(s)</div>
    </div>
    <div class="kpi">
        <div class="label">Solde net</div>
        <div class="val <?= $solde >= 0 ? 'green' : 'red' ?>">
            <?= ($solde >= 0 ? '+' : '') . fmtRPP((float)$solde) ?>
        </div>
    </div>
</div>

<?php if (!empty($parPeriode)): ?>
<div class="section-title">Détail par <?= $type === 'mensuel' ? 'jour' : 'mois' ?></div>
<table>
    <thead><tr><th>Période</th><th class="text-right">Recettes</th><th class="text-right">Dépenses</th><th class="text-right">Solde</th></tr></thead>
    <tbody>
    <?php
    $cumRec = 0; $cumDep = 0;
    foreach ($parPeriode as $periode => $vals):
        $rec = (float)($vals['recettes'] ?? 0);
        $dep = (float)($vals['depenses'] ?? 0);
        $sol = $rec - $dep;
        $cumRec += $rec; $cumDep += $dep;
        $label = $type === 'mensuel' ? date('d/m/Y', strtotime($periode)) : ($vals['label'] ?? $periode);
    ?>
    <tr>
        <td><?= htmlspecialchars($label, ENT_QUOTES) ?></td>
        <td class="text-right green-text"><?= $rec > 0 ? fmtRPP($rec) : '—' ?></td>
        <td class="text-right red-text"><?= $dep > 0 ? fmtRPP($dep) : '—' ?></td>
        <td class="text-right <?= $sol >= 0 ? 'green-text' : 'red-text' ?>"><?= ($sol >= 0 ? '+' : '') . fmtRPP($sol) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="text-right green-text"><?= fmtRPP($cumRec) ?></td>
            <td class="text-right red-text"><?= fmtRPP($cumDep) ?></td>
            <td class="text-right <?= ($cumRec-$cumDep)>=0?'green-text':'red-text' ?>"><?= (($cumRec-$cumDep)>=0?'+':'').fmtRPP($cumRec-$cumDep) ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<div class="section-title">Paiements reçus</div>
<table>
    <thead><tr><th>Date</th><th>Élève</th><th>Frais</th><th>Mode</th><th class="text-right">Montant</th></tr></thead>
    <tbody>
    <?php foreach ($paiements as $p): ?>
    <tr>
        <td><?= date('d/m/Y', strtotime($p->date_paiement)) ?></td>
        <td><?= htmlspecialchars($p->eleve_nom ?? '', ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($p->frais_nom ?? 'Divers', ENT_QUOTES) ?></td>
        <td><?= ucfirst($p->mode_paiement) ?></td>
        <td class="text-right green-text"><?= fmtRPP((float)$p->montant) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($paiements)): ?>
    <tr><td colspan="5" class="text-center" style="color:#888">Aucun paiement</td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="4" class="text-right">Total :</td><td class="text-right green-text"><?= fmtRPP((float)$totRec) ?></td></tr>
    </tfoot>
</table>

<div class="section-title">Dépenses</div>
<table>
    <thead><tr><th>Date</th><th>Libellé</th><th>Catégorie</th><th>Mode</th><th class="text-right">Montant</th></tr></thead>
    <tbody>
    <?php foreach ($depenses as $d): ?>
    <tr>
        <td><?= date('d/m/Y', strtotime($d->date_depense)) ?></td>
        <td><?= htmlspecialchars($d->libelle, ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($d->categorie_nom ?? 'Divers', ENT_QUOTES) ?></td>
        <td><?= ucfirst($d->mode_paiement) ?></td>
        <td class="text-right red-text"><?= fmtRPP((float)$d->montant) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($depenses)): ?>
    <tr><td colspan="5" class="text-center" style="color:#888">Aucune dépense</td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="4" class="text-right">Total :</td><td class="text-right red-text"><?= fmtRPP((float)$totDep) ?></td></tr>
    </tfoot>
</table>

<div class="footer">
    Rapport généré par ecole_app — <?= date('d/m/Y H:i:s') ?>
</div>
</body>
</html>
