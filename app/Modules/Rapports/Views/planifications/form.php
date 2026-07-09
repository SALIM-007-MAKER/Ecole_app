<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $planif ? 'Modifier la planification' : 'Nouvelle planification' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="/v2/rapports/planifications" class="text-slate-400 hover:text-violet-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="text-xl font-bold text-slate-800">
            <?= $planif ? 'Modifier la planification' : 'Nouvelle planification' ?>
        </h1>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <form method="post"
              action="<?= $planif ? '/v2/rapports/planifications/' . $planif['id'] . '/modifier' : '/v2/rapports/planifications' ?>"
              class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nom de la planification *</label>
                <input type="text" name="nom" required value="<?= htmlspecialchars($planif['nom'] ?? '') ?>"
                       class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Domaine *</label>
                    <select name="domaine" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                        <?php foreach (['scolarite','academique','finance','vie_scolaire','rh','bibliotheque','inventaire'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($planif['domaine'] ?? '') === $d ? 'selected' : '' ?>>
                            <?= str_replace('_', ' ', ucwords($d)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Format *</label>
                    <select name="type_export" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                        <?php foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'csv' => 'CSV'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($planif['type_export'] ?? 'pdf') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fréquence *</label>
                    <select name="frequence" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                        <option value="quotidien"    <?= ($planif['frequence'] ?? '') === 'quotidien'    ? 'selected' : '' ?>>Quotidien</option>
                        <option value="hebdomadaire" <?= ($planif['frequence'] ?? '') === 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire</option>
                        <option value="mensuel"      <?= ($planif['frequence'] ?? 'mensuel') === 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Heure d'exécution</label>
                    <input type="time" name="heure_execution" value="<?= htmlspecialchars(substr($planif['heure_execution'] ?? '06:00:00', 0, 5)) ?>"
                           class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="actif" value="1" <?= ($planif['actif'] ?? 1) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                    <span class="text-sm font-medium text-slate-700">Activer cette planification</span>
                </label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <?= $planif ? 'Enregistrer les modifications' : 'Créer la planification' ?>
                </button>
                <a href="/v2/rapports/planifications"
                   class="px-4 py-2 border border-slate-200 text-slate-700 rounded-lg text-sm hover:bg-slate-50">Annuler</a>
            </div>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
