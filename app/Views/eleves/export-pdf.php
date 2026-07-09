<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liste des élèves — Ecole App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-size: 13px; }

        

        @media print {
            .no-print   { display: none !important; }
            body        { font-size: 11px; }
            table       { font-size: 10px; }

        }
        @page { size: A4 landscape; margin: 15mm; }
    </style>
</head>
<body>

<!-- Boutons d'impression (masqués à l'impression) -->
<div class="no-print sticky top-0 flex items-center gap-2 border-b border-slate-200 bg-white p-3">
    <button onclick="window.print()" class="btn btn-danger">
        Imprimer / Enregistrer en PDF
    </button>
    <a href="<?= BASE_URL ?>/eleves" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
        Retour
    </a>
    <span class="ml-auto self-center text-xs text-slate-500">
        <?= count($eleves) ?> élève(s) — Généré le <?= date('d/m/Y à H:i') ?>
    </span>
</div>

<div class="p-4">
    <!-- En-tête du document -->
    <div class="mb-4 flex items-end justify-between border-b-4 border-violet-600 pb-3">
        <div>
            <h4 class="mb-1 text-lg font-bold text-violet-700">
                Ecole App
            </h4>
            <h5 class="m-0 text-base font-semibold">Liste des élèves</h5>
            <?php if (!empty($filters['classe_id'])): ?>
                <small class="text-slate-500">Filtrée par classe</small>
            <?php endif; ?>
        </div>
        <div class="text-right text-xs text-slate-500">
            <div>Année scolaire : <?= date('Y') . '-' . (date('Y') + 1) ?></div>
            <div>Date d'impression : <?= date('d/m/Y') ?></div>
            <div class="font-semibold">Total : <?= $total ?> élève(s)</div>
        </div>
    </div>

    <!-- Résumé -->
    <div class="mb-3 flex gap-6 text-xs text-slate-500">
        <?php
        $garcons = count(array_filter($eleves, fn($e) => $e->sexe === 'M'));
        $filles  = count($eleves) - $garcons;
        ?>
        <span><strong><?= $total ?></strong> total</span>
        <span><strong><?= $garcons ?></strong> garçons</span>
        <span><strong><?= $filles ?></strong> filles</span>
    </div>

    <!-- Tableau -->
    <table class="w-full border-collapse border border-slate-300 text-xs">
        <thead class="bg-violet-100 text-violet-900 border border-slate-300 px-2 py-1 text-left font-semibold">
            <tr>
                <th style="width:30px" class="border border-slate-300 px-2 py-1 text-left font-semibold">#</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Matricule</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Nom et Prénom</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Sexe</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Date de naissance</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Classe</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Téléphone</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Email</th>
                <th class="border border-slate-300 px-2 py-1 text-left font-semibold">Adresse</th>
                <th style="width:60px" class="text-center border border-slate-300 px-2 py-1 font-semibold">Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($eleves)): ?>
            <tr><td colspan="10" class="py-4 text-center text-slate-500 border border-slate-300 px-2 py-1">Aucun élève trouvé.</td></tr>
        <?php else: ?>
            <?php foreach ($eleves as $i => $e): ?>
            <tr>
                <td class="text-slate-500 border border-slate-300 px-2 py-1"><?= $i + 1 ?></td>
                <td class="font-mono text-xs border border-slate-300 px-2 py-1"><?= htmlspecialchars($e->matricule, ENT_QUOTES) ?></td>
                <td class="font-semibold border border-slate-300 px-2 py-1"><?= htmlspecialchars($e->nom . ' ' . $e->prenom, ENT_QUOTES) ?></td>
                <td class="border border-slate-300 px-2 py-1">
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap <?= $e->sexe === 'M' ? 'bg-sky-100 text-sky-700' : 'bg-rose-100 text-rose-700' ?>">
                        <?= $e->sexe === 'M' ? 'M' : 'F' ?>
                    </span>
                </td>
                <td class="border border-slate-300 px-2 py-1"><?= $e->date_naissance ? date('d/m/Y', strtotime($e->date_naissance)) : '—' ?></td>
                <td class="border border-slate-300 px-2 py-1">
                    <?= $e->classe_nom
                        ? htmlspecialchars($e->classe_niveau . ' ' . $e->classe_nom, ENT_QUOTES)
                        : '<span class="text-slate-500">—</span>' ?>
                </td>
                <td class="border border-slate-300 px-2 py-1"><?= htmlspecialchars($e->telephone ?? '—', ENT_QUOTES) ?></td>
                <td class="border border-slate-300 px-2 py-1"><?= htmlspecialchars($e->email ?? '—', ENT_QUOTES) ?></td>
                <td class="border border-slate-300 px-2 py-1"><?= htmlspecialchars($e->adresse ?? '—', ENT_QUOTES) ?></td>
                <td class="text-center border border-slate-300 px-2 py-1">
                    <?= $e->actif
                        ? '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-emerald-100 text-emerald-700">Actif</span>'
                        : '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5 whitespace-nowrap bg-slate-100 text-slate-600">Inactif</span>' ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot class="bg-slate-50">
            <tr>
                <td colspan="10" class="py-2 text-right text-xs text-slate-500 border border-slate-300 px-2 py-1">
                    Total : <strong><?= $total ?></strong> élève(s) &mdash;
                    <strong><?= $garcons ?></strong> garçons &mdash;
                    <strong><?= $filles ?></strong> filles
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Zone de signatures -->
    <div class="no-print mt-12 grid grid-cols-3 gap-8" style="break-inside:avoid">
        <div class="text-center">
            <div class="mx-3 mt-10 border-t border-slate-900 pt-2">
                <small class="text-slate-500">Signature du Directeur</small>
            </div>
        </div>
        <div class="text-center">
            <div class="mx-3 mt-10 border-t border-slate-900 pt-2">
                <small class="text-slate-500">Cachet de l'établissement</small>
            </div>
        </div>
        <div class="text-center">
            <div class="mx-3 mt-10 border-t border-slate-900 pt-2">
                <small class="text-slate-500">Secrétaire général(e)</small>
            </div>
        </div>
    </div>

    <div class="no-print mt-4 text-center text-xs text-slate-500">
        Document généré par Ecole App &mdash; <?= date('d/m/Y à H:i:s') ?>
    </div>
</div>


<script>
// Auto-print optionnel — décommentez si vous souhaitez l'impression automatique
// window.addEventListener('load', () => setTimeout(() => window.print(), 500));
</script>
</body>
</html>
