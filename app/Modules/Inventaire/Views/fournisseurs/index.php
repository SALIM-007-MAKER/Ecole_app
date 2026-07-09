<?php /** @var array $fournisseurs */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fournisseurs — Inventaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Fournisseurs</h1>
        <a href="/v2/inventaire/fournisseurs/creer"
           class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg hover:bg-violet-700 text-sm font-medium">
            <i data-lucide="plus" class="w-4 h-4"></i> Nouveau fournisseur
        </a>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Nom</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Email</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Téléphone</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Délai livraison</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Statut</th>
                    <th class="text-right px-4 py-3 font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($fournisseurs as $f): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($f['nom']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($f['email'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($f['telephone'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= $f['delai_livraison_j'] ?> j</td>
                    <td class="px-4 py-3">
                        <?php $colors=['actif'=>'green','inactif'=>'slate','bloque'=>'red']; $col=$colors[$f['statut']]??'slate'; ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-<?=$col?>-100 text-<?=$col?>-700">
                            <?= ucfirst($f['statut']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <a href="/v2/inventaire/fournisseurs/<?=$f['id']?>" class="text-violet-600 hover:underline text-xs">Voir</a>
                        <a href="/v2/inventaire/fournisseurs/<?=$f['id']?>/modifier" class="text-slate-500 hover:underline text-xs">Modifier</a>
                        <?php if ($f['statut'] !== 'bloque'): ?>
                        <form method="POST" action="/v2/inventaire/fournisseurs/<?=$f['id']?>/bloquer" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="text-red-500 hover:underline text-xs" onclick="return confirm('Bloquer ce fournisseur ?')">Bloquer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($fournisseurs)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucun fournisseur.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
