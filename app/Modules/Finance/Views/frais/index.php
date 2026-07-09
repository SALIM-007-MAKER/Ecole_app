<?php
/**
 * Finance V2 — Référentiel des frais
 * GET /v2/finance/frais
 */
?>
<div class="space-y-6">

    <!-- En-tête -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Référentiel des frais</h1>
            <p class="text-sm text-slate-500 mt-1">Catégories, types, tarifs et statuts des frais scolaires</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/v2/finance/frais/categories"
               class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                <i data-lucide="tag" class="inline w-4 h-4 mr-1"></i> Catégories
            </a>
            <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/v2/finance/frais/create"
               class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition-colors">
                <i data-lucide="plus" class="inline w-4 h-4 mr-1"></i> Nouveau frais
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cartes stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-2xl font-bold text-slate-800"><?= $stats['total'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Total types de frais</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-2xl font-bold text-emerald-600"><?= $stats['actifs'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Actifs</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-2xl font-bold text-amber-500"><?= $stats['obligatoires'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Obligatoires</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="text-2xl font-bold text-violet-600"><?= $stats['nb_categories'] ?></div>
            <div class="text-xs text-slate-500 mt-1">Catégories actives</div>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <input type="text" name="q" value="<?= htmlspecialchars($filters->q) ?>"
                   placeholder="Rechercher..."
                   class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">

            <select name="statut" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Tous les statuts</option>
                <option value="actif"    <?= $filters->statut === 'actif'    ? 'selected' : '' ?>>Actifs</option>
                <option value="inactif"  <?= $filters->statut === 'inactif'  ? 'selected' : '' ?>>Inactifs</option>
                <option value="archive"  <?= $filters->statut === 'archive'  ? 'selected' : '' ?>>Archivés</option>
            </select>

            <select name="periodicite" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Toutes périodicités</option>
                <?php foreach ($periodicites as $val => $label): ?>
                <option value="<?= $val ?>" <?= $filters->periodicite === $val ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="categorie_id" class="px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat->id ?>" <?= (int)$filters->categorieId === (int)$cat->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat->nom) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700">
                <i data-lucide="search" class="inline w-4 h-4 mr-1"></i> Filtrer
            </button>
            <a href="<?= BASE_URL ?>/v2/finance/frais" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Réinitialiser</a>
        </div>
    </form>

    <!-- Flash messages -->
    <?php if ($flash = \Core\Session::getFlash('success')): ?>
    <div class="flex items-center gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200">
        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($flash = \Core\Session::getFlash('error')): ?>
    <div class="flex items-center gap-3 p-4 bg-red-50 text-red-800 rounded-xl border border-red-200">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
        <span><?= htmlspecialchars($flash) ?></span>
    </div>
    <?php endif; ?>

    <!-- Tableau -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200">
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Code / Nom</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Catégorie</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Montant défaut</th>
                    <th class="px-4 py-3 text-left font-medium text-slate-600">Périodicité</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Obligatoire</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Statut</th>
                    <th class="px-4 py-3 text-center font-medium text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                        <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                        <p>Aucun type de frais trouvé.</p>
                        <?php if ($canCreate): ?>
                        <a href="<?= BASE_URL ?>/v2/finance/frais/create" class="mt-2 inline-block text-violet-600 hover:underline">
                            Créer le premier frais →
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($result['data'] as $frais): ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3">
                        <a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>"
                           class="font-medium text-slate-800 hover:text-violet-700">
                            <?= htmlspecialchars($frais->nom) ?>
                        </a>
                        <div class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($frais->code) ?></div>
                        <?php if ($frais->annee_scolaire): ?>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($frais->annee_scolaire) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($frais->categorie_nom): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium text-white"
                              style="background-color:<?= htmlspecialchars($frais->categorie_couleur ?? '#6366f1') ?>">
                            <?= htmlspecialchars($frais->categorie_nom) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 font-mono text-right text-slate-700">
                        <?= number_format($frais->montant_defaut, 0, ',', ' ') ?>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars($frais->devise) ?></span>
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        <?= \App\Modules\Finance\DTO\FraisTypeDTO::PERIODICITES[$frais->periodicite] ?? $frais->periodicite ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($frais->est_obligatoire): ?>
                        <span class="inline-block w-5 h-5 rounded-full bg-amber-400 text-white text-xs flex items-center justify-center" title="Obligatoire">✓</span>
                        <?php else: ?>
                        <span class="inline-block w-5 h-5 rounded-full bg-slate-200 text-slate-400 text-xs flex items-center justify-center" title="Optionnel">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php
                        $badgeClass = match($frais->statut) {
                            'actif'   => 'bg-emerald-100 text-emerald-700',
                            'inactif' => 'bg-amber-100 text-amber-700',
                            'archive' => 'bg-slate-100 text-slate-500',
                            default   => 'bg-slate-100 text-slate-500',
                        };
                        $badgeLabel = match($frais->statut) {
                            'actif'   => 'Actif',
                            'inactif' => 'Inactif',
                            'archive' => 'Archivé',
                            default   => $frais->statut,
                        };
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>">
                            <?= $badgeLabel ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>"
                               class="p-1.5 text-slate-400 hover:text-violet-600 rounded" title="Détail">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <?php if ($canUpdate && $frais->statut !== 'archive'): ?>
                            <a href="<?= BASE_URL ?>/v2/finance/frais/<?= $frais->id ?>/edit"
                               class="p-1.5 text-slate-400 hover:text-violet-600 rounded" title="Modifier">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canArchive && $frais->statut !== 'archive'): ?>
                            <button onclick="archiverFrais(<?= $frais->id ?>)"
                                    class="p-1.5 text-slate-400 hover:text-amber-600 rounded" title="Archiver">
                                <i data-lucide="archive" class="w-4 h-4"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-500">
                <?= $result['total'] ?> résultat(s) — page <?= $result['page'] ?> / <?= $result['total_pages'] ?>
            </span>
            <div class="flex gap-1">
                <?php if ($result['page'] > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] - 1])) ?>"
                   class="px-3 py-1 text-sm rounded border border-slate-200 hover:bg-slate-50">←</a>
                <?php endif; ?>
                <?php if ($result['page'] < $result['total_pages']): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] + 1])) ?>"
                   class="px-3 py-1 text-sm rounded border border-slate-200 hover:bg-slate-50">→</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal archivage -->
<div id="modal-archiver" class="hidden fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Archiver ce frais</h3>
        <p class="text-sm text-slate-600 mb-4">
            Le frais archivé restera consultable dans l'historique mais ne sera plus
            utilisable pour de nouvelles factures.
        </p>
        <form method="POST" id="form-archiver">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Session::getCsrfToken()) ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Motif d'archivage *</label>
                <textarea name="motif" rows="3" required
                          class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500"
                          placeholder="Ex: Frais remplacé par un nouveau tarif 2026-2027"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-archiver').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700">
                    Archiver
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function archiverFrais(id) {
    document.getElementById('form-archiver').action = `<?= BASE_URL ?>/v2/finance/frais/${id}/archiver`;
    document.getElementById('modal-archiver').classList.remove('hidden');
}
</script>
