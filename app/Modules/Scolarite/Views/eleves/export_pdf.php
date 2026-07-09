<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des élèves</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; }
.header { text-align: center; padding: 16px 0 8px; border-bottom: 2px solid #7c3aed; margin-bottom: 12px; }
.header h1 { font-size: 16px; font-weight: bold; color: #7c3aed; }
.header p  { font-size: 10px; color: #64748b; margin-top: 2px; }
table { width: 100%; border-collapse: collapse; }
thead th { background: #f1f5f9; padding: 6px 8px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; border-bottom: 1px solid #e2e8f0; }
tbody td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tbody tr:nth-child(even) td { background: #f8fafc; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 9999px; font-size: 9px; font-weight: 600; }
.badge-m { background: #e0f2fe; color: #0369a1; }
.badge-f { background: #fee2e2; color: #b91c1c; }
.badge-actif { background: #d1fae5; color: #065f46; }
.badge-inactif { background: #f1f5f9; color: #64748b; }
.footer { margin-top: 12px; text-align: right; font-size: 9px; color: #94a3b8; }
@media print { .no-print { display: none; } }
</style>
</head>
<body>
<div class="no-print" style="padding:12px;background:#f8fafc;margin-bottom:12px;display:flex;gap:8px;align-items:center">
    <button onclick="window.print()" style="background:#7c3aed;color:#fff;border:none;padding:6px 16px;border-radius:6px;cursor:pointer;font-size:12px">
        Imprimer / PDF
    </button>
    <a href="<?= BASE_URL ?>/v2/scolarite/eleves" style="font-size:12px;color:#64748b;text-decoration:none">
        ← Retour à la liste
    </a>
</div>

<div class="header">
    <h1>Liste des élèves</h1>
    <p>Généré le <?= date('d/m/Y à H:i') ?> — <?= count($eleves) ?> élève(s)</p>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Matricule</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Sexe</th>
            <th>Naissance</th>
            <th>Classe</th>
            <th>Téléphone</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($eleves as $i => $e): ?>
    <tr>
        <td style="color:#94a3b8"><?= $i + 1 ?></td>
        <td style="font-family:monospace;font-size:10px;color:#64748b"><?= htmlspecialchars($e->matricule, ENT_QUOTES) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($e->nom, ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($e->prenom, ENT_QUOTES) ?></td>
        <td>
            <span class="badge <?= $e->sexe === 'M' ? 'badge-m' : 'badge-f' ?>">
                <?= $e->sexe === 'M' ? 'M' : 'F' ?>
            </span>
        </td>
        <td><?= $e->date_naissance ? date('d/m/Y', strtotime($e->date_naissance)) : '—' ?></td>
        <td><?= htmlspecialchars($e->classe_nom ?? '—', ENT_QUOTES) ?></td>
        <td><?= htmlspecialchars($e->telephone ?? '—', ENT_QUOTES) ?></td>
        <td>
            <span class="badge <?= $e->actif ? 'badge-actif' : 'badge-inactif' ?>">
                <?= $e->actif ? 'Actif' : 'Inactif' ?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($eleves)): ?>
    <tr><td colspan="9" style="text-align:center;padding:20px;color:#94a3b8">Aucun élève</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="footer">EduNova — <?= date('Y') ?></div>
</body>
</html>
