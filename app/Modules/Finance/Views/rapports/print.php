<?php
/**
 * Vue d'impression générique — toutes les données du rapport sont disponibles via $data flatten
 * @var string $type
 * @var \App\Modules\Finance\DTO\ReportFiltersDTO $filters
 * @var array  $user
 */
$fmt = fn(float $v) => number_format($v, 0, ',', ' ') . ' XOF';
$titles = [
    'dashboard'  => 'Tableau de bord financier',
    'paiements'  => 'Rapport des paiements',
    'factures'   => 'Rapport des factures',
    'impayes'    => 'Rapport des impayés',
    'caisse'     => 'Rapport de caisse',
    'analytique' => 'Rapport analytique',
];
$title = $titles[$type] ?? 'Rapport financier';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 11px; }
        body { padding: 20px; color: #1e293b; }
        header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #7c3aed; }
        header .school { font-size: 14px; font-weight: bold; color: #7c3aed; }
        header .meta   { text-align: right; color: #64748b; font-size: 10px; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 6px; }
        .filters { font-size: 10px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f1f5f9; color: #475569; font-size: 9px; text-transform: uppercase; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; }
        td { padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        tfoot td { background: #f1f5f9; font-weight: bold; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 9999px; font-size: 9px; font-weight: bold; }
        .badge-emerald { background: #d1fae5; color: #065f46; }
        .badge-red     { background: #fee2e2; color: #991b1b; }
        .badge-amber   { background: #fef3c7; color: #92400e; }
        .badge-blue    { background: #dbeafe; color: #1e40af; }
        .badge-slate   { background: #f1f5f9; color: #475569; }
        footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 9px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="no-print" style="margin-bottom:12px;">
    <button onclick="window.print()" style="background:#7c3aed;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:12px;">Imprimer / Enregistrer PDF</button>
    <button onclick="window.close()" style="margin-left:8px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:12px;">Fermer</button>
</div>

<header>
    <div>
        <div class="school">École — Finance V2</div>
        <h1><?= htmlspecialchars($title) ?></h1>
    </div>
    <div class="meta">
        Généré le <?= date('d/m/Y à H:i') ?><br>
        Par <?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?><br>
        <?php if (!empty($filters->anneeScolaire)): ?>Année scolaire : <?= htmlspecialchars($filters->anneeScolaire) ?><br><?php endif; ?>
        <?php if (!empty($filters->dateDebut) || !empty($filters->dateFin)): ?>
        Période : <?= htmlspecialchars($filters->dateDebut ?: '…') ?> → <?= htmlspecialchars($filters->dateFin ?: '…') ?>
        <?php endif; ?>
    </div>
</header>

<?php if ($type === 'paiements' && !empty($pagination['items'])): ?>
    <table>
        <thead><tr>
            <th>N° Paiement</th><th>Élève</th><th>Matricule</th><th>Classe</th>
            <th>Mode</th><th class="text-right">Montant</th><th>Date</th><th>Statut</th>
        </tr></thead>
        <tbody>
        <?php $total = 0; foreach ($pagination['items'] as $p): $total += $p->montant_applique; ?>
        <tr>
            <td><?= htmlspecialchars($p->numero) ?></td>
            <td><?= htmlspecialchars($p->eleve_nom) ?></td>
            <td><?= htmlspecialchars($p->eleve_matricule) ?></td>
            <td><?= htmlspecialchars($p->classe_nom) ?></td>
            <td><?= htmlspecialchars($p->mode_code) ?></td>
            <td class="text-right"><?= $fmt((float)$p->montant_applique) ?></td>
            <td><?= date('d/m/Y', strtotime($p->date_paiement)) ?></td>
            <td><span class="badge badge-emerald"><?= ucfirst($p->statut) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="5"><strong>Total</strong></td>
            <td class="text-right"><strong><?= $fmt($total) ?></strong></td>
            <td colspan="2"></td>
        </tr></tfoot>
    </table>

<?php elseif ($type === 'impayes' && !empty($impayes)): ?>
    <div style="margin-bottom:12px;padding:8px 12px;background:#fee2e2;border-radius:6px;">
        <strong style="color:#991b1b;">Total impayé : <?= $fmt($totalDu) ?></strong>
        — <?= count($impayes) ?> facture(s)
    </div>
    <table>
        <thead><tr>
            <th>Élève</th><th>Matricule</th><th>Classe</th><th>Facture</th>
            <th class="text-right">Total</th><th class="text-right">Payé</th>
            <th class="text-right">Restant</th><th>Tranche</th><th class="text-right">Retard (j)</th>
        </tr></thead>
        <tbody>
        <?php foreach ($impayes as $i): ?>
        <tr>
            <td><?= htmlspecialchars($i->eleve_nom) ?></td>
            <td><?= htmlspecialchars($i->eleve_matricule) ?></td>
            <td><?= htmlspecialchars($i->classe_nom) ?></td>
            <td><?= htmlspecialchars($i->numero) ?></td>
            <td class="text-right"><?= $fmt((float)$i->montant_total) ?></td>
            <td class="text-right"><?= $fmt((float)$i->montant_paye) ?></td>
            <td class="text-right" style="color:#991b1b;font-weight:bold;"><?= $fmt((float)$i->montant_restant) ?></td>
            <td><?= $i->tranche_age ?></td>
            <td class="text-right"><?= max(0,(int)$i->jours_retard) ?>j</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="6"><strong>Total impayé</strong></td>
            <td class="text-right" style="color:#991b1b;"><strong><?= $fmt($totalDu) ?></strong></td>
            <td colspan="2"></td>
        </tr></tfoot>
    </table>

<?php elseif ($type === 'factures' && !empty($pagination['items'])): ?>
    <table>
        <thead><tr>
            <th>N° Facture</th><th>Élève</th><th>Classe</th><th>Année</th>
            <th class="text-right">Total</th><th class="text-right">Payé</th>
            <th class="text-right">Restant</th><th>Statut</th>
        </tr></thead>
        <tbody>
        <?php foreach ($pagination['items'] as $f): ?>
        <tr>
            <td><?= htmlspecialchars($f->numero) ?></td>
            <td><?= htmlspecialchars($f->eleve_nom) ?></td>
            <td><?= htmlspecialchars($f->classe_nom) ?></td>
            <td><?= htmlspecialchars($f->annee_scolaire) ?></td>
            <td class="text-right"><?= $fmt((float)$f->montant_total) ?></td>
            <td class="text-right"><?= $fmt((float)$f->montant_paye) ?></td>
            <td class="text-right"><?= $fmt((float)$f->montant_restant) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$f->statut)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($type === 'dashboard'): ?>
    <table>
        <thead><tr><th>Indicateur</th><th class="text-right">Valeur</th></tr></thead>
        <tbody>
        <tr><td>Recettes aujourd'hui</td><td class="text-right"><?= $fmt((float)$stats->recettes_jour) ?> (<?= (int)$stats->nb_paiements_jour ?> paiements)</td></tr>
        <tr><td>Recettes ce mois</td><td class="text-right"><?= $fmt((float)$stats->recettes_mois) ?> (<?= (int)$stats->nb_paiements_mois ?> paiements)</td></tr>
        <tr><td>Impayés total</td><td class="text-right"><?= $fmt((float)$stats->impayes_total) ?> (<?= (int)$stats->impayes_nb ?> factures)</td></tr>
        <tr><td>Trésorerie caisse</td><td class="text-right"><?= $fmt((float)$stats->tresorerie_caisse) ?></td></tr>
        <tr><td>Taux de recouvrement</td><td class="text-right"><?= $stats->taux_recouvrement ?>%</td></tr>
        </tbody>
    </table>
    <?php if (!empty($topDebiteurs)): ?>
    <h2 style="font-size:13px;margin:16px 0 8px;">Top débiteurs</h2>
    <table>
        <thead><tr><th>Élève</th><th>Matricule</th><th>Classe</th><th class="text-right">Montant dû</th><th class="text-right">Factures</th></tr></thead>
        <tbody>
        <?php foreach ($topDebiteurs as $d): ?>
        <tr>
            <td><?= htmlspecialchars($d->nom . ' ' . $d->prenom) ?></td>
            <td><?= htmlspecialchars($d->matricule) ?></td>
            <td><?= htmlspecialchars($d->classe_nom) ?></td>
            <td class="text-right"><?= $fmt((float)$d->montant_du) ?></td>
            <td class="text-right"><?= (int)$d->nb_factures ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php elseif ($type === 'caisse' && !empty($pagination['items'])): ?>
    <table>
        <thead><tr>
            <th>Ouverture</th><th>Fermeture</th><th>Caissier</th>
            <th class="text-right">Fond initial</th><th class="text-right">Recettes</th>
            <th class="text-right">Décaiss.</th><th class="text-right">Solde théo.</th>
            <th class="text-right">Écart</th><th>Statut</th>
        </tr></thead>
        <tbody>
        <?php foreach ($pagination['items'] as $s): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($s->date_ouverture)) ?></td>
            <td><?= $s->date_fermeture ? date('d/m/Y H:i', strtotime($s->date_fermeture)) : '—' ?></td>
            <td><?= htmlspecialchars($s->caissier_nom ?? '—') ?></td>
            <td class="text-right"><?= $fmt((float)($s->fond_initial ?? 0)) ?></td>
            <td class="text-right"><?= $fmt((float)($s->total_recettes ?? 0)) ?></td>
            <td class="text-right"><?= $fmt((float)($s->total_decaissements ?? 0)) ?></td>
            <td class="text-right"><?= $fmt((float)($s->solde_theorique ?? 0)) ?></td>
            <td class="text-right"><?= $fmt((float)($s->ecart ?? 0)) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$s->statut)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($type === 'analytique'): ?>
    <?php if (!empty($comparatifAnnuel)): ?>
    <h2 style="font-size:13px;margin:0 0 8px;">Comparatif annuel</h2>
    <table>
        <thead><tr><th>Année</th><th class="text-right">Recettes</th></tr></thead>
        <tbody>
        <?php foreach ($comparatifAnnuel as $ca): ?>
        <tr><td><?= htmlspecialchars((string)$ca->annee) ?></td><td class="text-right"><?= $fmt((float)$ca->recettes) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if (!empty($comparatifClasse)): ?>
    <h2 style="font-size:13px;margin:16px 0 8px;">Recouvrement par classe<?= !empty($anneeSco) ? ' — ' . htmlspecialchars($anneeSco) : '' ?></h2>
    <table>
        <thead><tr>
            <th>Classe</th><th>Niveau</th><th class="text-right">Élèves</th>
            <th class="text-right">Total émis</th><th class="text-right">Payé</th>
            <th class="text-right">Restant</th><th class="text-right">Taux</th>
        </tr></thead>
        <tbody>
        <?php foreach ($comparatifClasse as $cl): ?>
        <tr>
            <td><?= htmlspecialchars($cl->classe_nom) ?></td>
            <td><?= htmlspecialchars($cl->niveau) ?></td>
            <td class="text-right"><?= (int)$cl->nb_eleves ?></td>
            <td class="text-right"><?= $fmt((float)$cl->montant_total) ?></td>
            <td class="text-right"><?= $fmt((float)$cl->montant_paye) ?></td>
            <td class="text-right"><?= $fmt((float)$cl->montant_restant) ?></td>
            <td class="text-right"><?= $cl->taux_paiement ?>%</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

<?php else: ?>
    <p style="color:#94a3b8;text-align:center;padding:40px 0;">Aucune donnée à afficher pour ce rapport.</p>
<?php endif; ?>

<footer>
    Finance V2 — Rapport généré automatiquement le <?= date('d/m/Y à H:i:s') ?>
</footer>
</body>
</html>
