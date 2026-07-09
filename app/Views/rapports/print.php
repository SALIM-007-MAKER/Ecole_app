<?php
$annee             = $annee             ?? '';
$periode           = $periode           ?? null;
$kpiGlobal         = $kpiGlobal         ?? [];
$kpiFinance        = $kpiFinance        ?? [];
$kpiReussite       = $kpiReussite       ?? [];
$elevesParClasse   = $elevesParClasse   ?? [];
$reussiteParClasse = $reussiteParClasse ?? [];
$mentionsDistrib   = $mentionsDistrib   ?? [];
$depensesCat       = $depensesCat       ?? [];
$financeParMois    = $financeParMois    ?? [];
$absencesParClasse = $absencesParClasse ?? [];

$f  = fn($n) => number_format((float)$n, 0, ',', ' ');
$fc = fn($n) => $f($n) . ' F';
$n2 = fn($n) => number_format((float)$n, 2, ',', '');
?>

<style>
    body { font-size: 11pt; color: #212529; }
    h2 { font-size: 16pt; border-bottom: 2px solid #0d6efd; padding-bottom: 6px; margin-top: 24px; }
    h3 { font-size: 12pt; margin-top: 16px; color: #0d6efd; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10pt; }
    th, td { border: 1px solid #dee2e6; padding: 5px 8px; }
    th { background: #e9ecef; font-weight: bold; }
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 12px 0; }
    .kpi-box { border: 1px solid #dee2e6; padding: 8px 12px; border-radius: 4px; }
    .kpi-val { font-size: 14pt; font-weight: bold; }
    .kpi-lbl { font-size: 9pt; color: #6c757d; }
    .text-center { text-align: center; }
    .text-right  { text-align: right; }
    .page-break  { page-break-before: always; }
    .header-block { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 16px; }
    .bar { background: #e9ecef; border-radius: 3px; height: 8px; display: inline-block; width: 80px; }
    .bar-inner { background: #0d6efd; height: 8px; border-radius: 3px; display: inline-block; }
</style>

<!-- En-tête -->
<div class="header-block">
    <div>
        <h1 style="font-size:18pt;margin:0">Rapport général — <?= htmlspecialchars($annee, ENT_QUOTES) ?></h1>
        <?php if ($periode): ?>
        <p style="margin:4px 0 0;color:#6c757d">Période : <?= htmlspecialchars($periode->nom, ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>
    <div style="text-align:right;color:#6c757d;font-size:9pt">
        Généré le <?= date('d/m/Y à H:i') ?><br>
        <em>Ecole App — Rapport confidentiel</em>
    </div>
</div>

<!-- 1. Effectifs -->
<h2>1. Effectifs scolaires</h2>

<div class="kpi-grid">
    <div class="kpi-box">
        <div class="kpi-val"><?= $f($kpiGlobal['eleves_actifs'] ?? 0) ?></div>
        <div class="kpi-lbl">Élèves actifs</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $f($kpiGlobal['classes'] ?? 0) ?></div>
        <div class="kpi-lbl">Classes</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $f($kpiGlobal['professeurs'] ?? 0) ?></div>
        <div class="kpi-lbl">Enseignants</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $f($kpiGlobal['garcons'] ?? 0) ?></div>
        <div class="kpi-lbl">Garçons</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $f($kpiGlobal['filles'] ?? 0) ?></div>
        <div class="kpi-lbl">Filles</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $f($kpiGlobal['total_eleves'] ?? 0) ?></div>
        <div class="kpi-lbl">Total inscrits</div>
    </div>
</div>

<?php if ($elevesParClasse): ?>
<h3>Effectifs par classe</h3>
<table>
    <thead>
        <tr><th>Classe</th><th>Niveau</th><th class="text-center">Total</th><th class="text-center">Garçons</th><th class="text-center">Filles</th></tr>
    </thead>
    <tbody>
    <?php foreach ($elevesParClasse as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r->nom, ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r->niveau ?? '', ENT_QUOTES) ?></td>
        <td class="text-center"><strong><?= (int)$r->nb_eleves ?></strong></td>
        <td class="text-center"><?= (int)$r->garcons ?></td>
        <td class="text-center"><?= (int)$r->filles ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="background:#e9ecef;font-weight:bold">
        <td colspan="2">TOTAL</td>
        <td class="text-center"><?= $f($kpiGlobal['eleves_actifs'] ?? 0) ?></td>
        <td class="text-center"><?= $f($kpiGlobal['garcons'] ?? 0) ?></td>
        <td class="text-center"><?= $f($kpiGlobal['filles'] ?? 0) ?></td>
    </tr>
    </tbody>
</table>
<?php endif; ?>

<!-- 2. Finance -->
<div class="page-break"></div>
<h2>2. Situation financière</h2>

<div class="kpi-grid">
    <div class="kpi-box">
        <div class="kpi-val"><?= $fc($kpiFinance['recettes'] ?? 0) ?></div>
        <div class="kpi-lbl">Recettes totales</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $fc($kpiFinance['depenses'] ?? 0) ?></div>
        <div class="kpi-lbl">Dépenses totales</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $fc($kpiFinance['solde'] ?? 0) ?></div>
        <div class="kpi-lbl">Solde net</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $fc($kpiFinance['frais_total'] ?? 0) ?></div>
        <div class="kpi-lbl">Frais prévisionnels</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= $fc($kpiFinance['impayes'] ?? 0) ?></div>
        <div class="kpi-lbl">Impayés</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= ($kpiFinance['taux_recouvrement'] ?? 0) ?>%</div>
        <div class="kpi-lbl">Taux de recouvrement</div>
    </div>
</div>

<?php if ($financeParMois): ?>
<h3>Flux mensuels (<?= htmlspecialchars($annee, ENT_QUOTES) ?>)</h3>
<table>
    <thead><tr><th>Mois</th><th class="text-right">Recettes</th><th class="text-right">Dépenses</th><th class="text-right">Solde</th></tr></thead>
    <tbody>
    <?php foreach ($financeParMois as $r):
        $sol = $r['recettes'] - $r['depenses'];
    ?>
    <tr>
        <td><?= htmlspecialchars($r['label'], ENT_QUOTES) ?></td>
        <td class="text-right"><?= $fc($r['recettes']) ?></td>
        <td class="text-right"><?= $fc($r['depenses']) ?></td>
        <td class="text-right" style="color:<?= $sol >= 0 ? '#198754' : '#dc3545' ?>"><?= $fc($sol) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($depensesCat): ?>
<h3>Dépenses par catégorie</h3>
<table>
    <thead><tr><th>Catégorie</th><th class="text-right">Montant</th></tr></thead>
    <tbody>
    <?php foreach ($depensesCat as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r->nom, ENT_QUOTES) ?></td>
        <td class="text-right"><?= $fc($r->total) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- 3. Réussite -->
<div class="page-break"></div>
<h2>3. Résultats scolaires</h2>

<div class="kpi-grid">
    <div class="kpi-box">
        <div class="kpi-val"><?= (int)($kpiReussite['total'] ?? 0) ?></div>
        <div class="kpi-lbl">Bulletins évalués</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= (int)($kpiReussite['reussis'] ?? 0) ?></div>
        <div class="kpi-lbl">Élèves admis</div>
    </div>
    <div class="kpi-box">
        <div class="kpi-val"><?= ($kpiReussite['taux_reussite'] ?? 0) ?>%</div>
        <div class="kpi-lbl">Taux de réussite</div>
    </div>
    <div class="kpi-box" style="grid-column:span 3">
        <div class="kpi-val"><?= $n2($kpiReussite['moy_generale'] ?? 0) ?>/20</div>
        <div class="kpi-lbl">Moyenne générale globale</div>
    </div>
</div>

<?php if ($reussiteParClasse): ?>
<h3>Réussite par classe</h3>
<table>
    <thead><tr><th>Classe</th><th>Niveau</th><th class="text-center">Total</th><th class="text-center">Réussis</th><th class="text-center">Taux</th><th class="text-center">Moy.</th><th class="text-center">Max</th><th class="text-center">Min</th></tr></thead>
    <tbody>
    <?php foreach ($reussiteParClasse as $r):
        $t2 = $r->total > 0 ? round($r->reussis / $r->total * 100) : 0;
    ?>
    <tr>
        <td><?= htmlspecialchars($r->classe, ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r->niveau ?? '', ENT_QUOTES) ?></td>
        <td class="text-center"><?= (int)$r->total ?></td>
        <td class="text-center"><?= (int)$r->reussis ?></td>
        <td class="text-center"><?= $t2 ?>%</td>
        <td class="text-center"><?= $n2($r->moy_classe) ?></td>
        <td class="text-center"><?= $n2($r->moy_max) ?></td>
        <td class="text-center"><?= $n2($r->moy_min) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($mentionsDistrib): ?>
<h3>Répartition des mentions</h3>
<table>
    <thead><tr><th>Mention</th><th class="text-center">Nombre d'élèves</th></tr></thead>
    <tbody>
    <?php foreach ($mentionsDistrib as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r->mention ?? 'N/A', ENT_QUOTES) ?></td>
        <td class="text-center"><?= (int)$r->nb ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- 4. Présences -->
<?php if ($absencesParClasse): ?>
<div class="page-break"></div>
<h2>4. Absences &amp; Présences</h2>
<table>
    <thead><tr><th>Classe</th><th>Niveau</th><th class="text-center">Élèves</th><th class="text-center">Absences</th><th class="text-center">Retards</th><th class="text-center">Moy/élève</th></tr></thead>
    <tbody>
    <?php foreach ($absencesParClasse as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r->classe, ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($r->niveau ?? '', ENT_QUOTES) ?></td>
        <td class="text-center"><?= (int)$r->nb_eleves ?></td>
        <td class="text-center"><?= (int)$r->nb_seches ?></td>
        <td class="text-center"><?= (int)$r->nb_retards ?></td>
        <td class="text-center"><?= $r->moy_par_eleve ?? '0' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<hr style="margin-top:24px">
<p style="font-size:9pt;color:#6c757d;text-align:center">
    Document généré automatiquement par Ecole App — <?= date('d/m/Y H:i') ?>
</p>
