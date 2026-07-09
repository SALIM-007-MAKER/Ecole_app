<?php
$enfants   = $enfants   ?? [];
$enfant    = $enfant    ?? null;
$frais     = $frais     ?? [];
$paiements = $paiements ?? [];
$totaux    = $totaux    ?? ['total_frais' => 0, 'total_paye' => 0, 'total_reste' => 0];
$annee     = $annee     ?? '';
$annees    = $annees    ?? [];

$modes = ['especes' => 'Espèces', 'cheque' => 'Chèque', 'virement' => 'Virement', 'mobile' => 'Mobile Money', 'autre' => 'Autre'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold"><i class="bi bi-credit-card me-2 text-success"></i>Scolarité et paiements</h4>
    <a href="<?= BASE_URL ?>/parent/dashboard" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<!-- Filtres ────────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Enfant</label>
                <select name="eleve_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($enfants as $e): ?>
                    <option value="<?= $e->id ?>" <?= $enfant && (int)$enfant->id === (int)$e->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e->prenom . ' ' . $e->nom . ' (' . $e->classe_nom . ')', ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Année scolaire</label>
                <select name="annee" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($annees as $a): ?>
                    <option value="<?= $a ?>" <?= $a === $annee ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($enfant): ?>

<!-- Résumé financier ───────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold fs-4 text-primary"><?= number_format((float)$totaux['total_frais'], 0, ',', ' ') ?></div>
            <div class="small text-muted">Total frais (FCFA)</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold fs-4 text-success"><?= number_format((float)$totaux['total_paye'], 0, ',', ' ') ?></div>
            <div class="small text-muted">Total payé (FCFA)</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3" style="background:<?= (float)$totaux['total_reste'] > 0 ? '#fff3cd' : '#f0fff4' ?>">
            <div class="fw-bold fs-4 <?= (float)$totaux['total_reste'] > 0 ? 'text-warning' : 'text-success' ?>">
                <?= number_format((float)$totaux['total_reste'], 0, ',', ' ') ?>
            </div>
            <div class="small text-muted">Solde impayé (FCFA)</div>
        </div>
    </div>
</div>

<!-- Frais scolaires ─────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 fw-semibold">
        <i class="bi bi-list-check me-2 text-primary"></i>Frais scolaires — <?= htmlspecialchars($annee, ENT_QUOTES) ?>
    </div>
    <?php if (empty($frais)): ?>
    <div class="card-body text-center text-muted py-4">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
        Aucun frais enregistré pour cette année.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.87rem">
            <thead class="table-light">
                <tr>
                    <th>Frais</th>
                    <th class="text-end">Montant</th>
                    <th class="text-end">Payé</th>
                    <th class="text-end">Reste</th>
                    <th class="text-center">Échéance</th>
                    <th class="text-center">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($frais as $f):
                $reste  = (float)$f->reste;
                $pctPay = (float)$f->montant > 0 ? min(100, round((float)$f->total_paye / (float)$f->montant * 100)) : 0;
                $isLate = !empty($f->date_echeance) && strtotime($f->date_echeance) < time() && $reste > 0;
            ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($f->frais_nom, ENT_QUOTES) ?></td>
                <td class="text-end"><?= number_format((float)$f->montant, 0, ',', ' ') ?></td>
                <td class="text-end text-success"><?= number_format((float)$f->total_paye, 0, ',', ' ') ?></td>
                <td class="text-end <?= $reste > 0 ? 'text-danger fw-semibold' : 'text-success' ?>">
                    <?= number_format($reste, 0, ',', ' ') ?>
                </td>
                <td class="text-center small <?= $isLate ? 'text-danger' : 'text-muted' ?>">
                    <?= $f->date_echeance ? date('d/m/Y', strtotime($f->date_echeance)) : '—' ?>
                    <?= $isLate ? '<i class="bi bi-exclamation-triangle-fill ms-1"></i>' : '' ?>
                </td>
                <td class="text-center">
                    <div class="progress" style="height:8px;min-width:80px">
                        <div class="progress-bar bg-<?= $pctPay >= 100 ? 'success' : ($pctPay > 0 ? 'warning' : 'danger') ?>"
                             style="width:<?= $pctPay ?>%"></div>
                    </div>
                    <small class="text-muted"><?= $pctPay ?>%</small>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Historique des paiements ─────────────────────────────────────────────── -->
<?php if (!empty($paiements)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 fw-semibold">
        <i class="bi bi-clock-history me-2 text-success"></i>Historique des paiements
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.87rem">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Frais</th>
                    <th>Référence</th>
                    <th>Mode</th>
                    <th class="text-end">Montant</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paiements as $p): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($p->date_paiement)) ?></td>
                <td><?= htmlspecialchars($p->frais_nom, ENT_QUOTES) ?></td>
                <td class="text-muted small font-monospace"><?= htmlspecialchars($p->reference ?? '—', ENT_QUOTES) ?></td>
                <td>
                    <span class="badge bg-secondary"><?= $modes[$p->mode_paiement] ?? $p->mode_paiement ?></span>
                </td>
                <td class="text-end fw-semibold text-success">
                    <?= number_format((float)$p->montant, 0, ',', ' ') ?> FCFA
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="alert alert-warning">Aucun enfant sélectionné.</div>
<?php endif; ?>
